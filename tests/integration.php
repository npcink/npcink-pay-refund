<?php
/** Isolated WordPress/MySQL checks. Never run against an existing merchant site. */
if (!defined('ABSPATH') || !is_file(ABSPATH . '.npcink-refund-test-site')) {
    throw new RuntimeException('An isolated .npcink-refund-test-site is required.');
}
(static function () {
global $wpdb;
$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
foreach (array('admin/class-npcink-pay-refund-admin.php', 'admin/partials/npcink-pay-refund-admin-config.php',
    'admin/partials/npcink-pay-refund-admin-authority.php', 'admin/partials/npcink-pay-refund-admin-query.php',
    'admin/pay/npcink-pay-refund-admin-public.php', 'admin/pay/npcink-pay-refund-admin-wx.php',
    'admin/pay/npcink-pay-refund-admin-zfb.php', 'includes/class-npcink-pay-refund-activator.php',
    'admin/partials/class-npcink-pay-refund-uninstall.php') as $file) {
    require_once $root . '/' . $file;
}
function npcink_integration_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo 'PASS: ' . $message . "\n";
}
$prefix = 'audit-' . bin2hex(random_bytes(6));
$original_user = get_current_user_id();
$original_config = get_option('npcink_pay_refund_config', null);
$original_secrets = get_option('npcink_pay_refund_secrets', null);
$original_cap_version = get_option('npcink_pay_refund_capability_version', null);
$users = array();
$option_names = array();
$table = $wpdb->prefix . 'npcink_pay_refund_order';
try {
    foreach (array('author', 'author', 'subscriber') as $index => $role) {
        $id = wp_insert_user(array('user_login' => $prefix . '-' . $index, 'user_pass' => wp_generate_password(), 'role' => $role));
        if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
        $users[] = $id;
    }
    foreach (array_slice($users, 0, 2) as $id) { get_userdata($id)->add_cap('npcink_refund_orders'); }
    $admin = get_users(array('role' => 'administrator', 'number' => 1))[0];
    wp_set_current_user($admin->ID);

    Npcink_Pay_Refund_Admin_Public::maybe_upgrade_schema();
    npcink_integration_assert(Npcink_Pay_Refund_Activator::schema_is_current(), 'real database schema and indexes verified');
    delete_option('npcink_pay_refund_schema_version');
    $wpdb->query("ALTER TABLE {$table} DROP INDEX n_user_id");
    $break_ddl = static function ($sql) use ($table) {
        return str_starts_with($sql, 'ALTER TABLE ' . $table) ? 'NPCINK_INJECTED_DDL_FAILURE' : $sql;
    };
    add_filter('query', $break_ddl);
    $previous_suppression = $wpdb->suppress_errors(true);
    Npcink_Pay_Refund_Admin_Public::maybe_upgrade_schema();
    $wpdb->suppress_errors($previous_suppression);
    remove_filter('query', $break_ddl);
    npcink_integration_assert(false === get_option('npcink_pay_refund_schema_version', false), 'DDL failure cannot advance schema version');
    Npcink_Pay_Refund_Admin_Public::maybe_upgrade_schema();
    npcink_integration_assert(Npcink_Pay_Refund_Activator::SCHEMA_VERSION === get_option('npcink_pay_refund_schema_version'), 'schema upgrade retries after database recovery');

    // One older own record must survive >20 newer records from a different operator.
    $base = array('n_time' => '2026-11-05 10:00:00', 'n_amount' => '7.00', 'n_user' => 'Operator', 'n_reason' => 'test', 'n_type' => '微信');
    $wpdb->insert($table, array_merge($base, array('n_order' => $prefix . '-own', 'n_user_id' => $users[0])));
    for ($i = 0; $i < 25; ++$i) {
        $wpdb->insert($table, array_merge($base, array('n_order' => $prefix . '-other-' . $i, 'n_user_id' => $users[1])));
    }
    wp_set_current_user($users[0]);
    $page = Npcink_Pay_Refund_Admin_Query::records_page(array('keyword' => $prefix));
    npcink_integration_assert(1 === $page['total'] && $prefix . '-own' === $page['rows'][0]->order, 'owner predicate precedes LIMIT and total count');
    wp_set_current_user($admin->ID);
    $page = Npcink_Pay_Refund_Admin_Query::records_page(array('keyword' => $prefix, 'page' => 2));
    npcink_integration_assert(26 === $page['total'] && 6 === count($page['rows']), 'server pagination reaches older records');
    npcink_integration_assert(1 === Npcink_Pay_Refund_Admin_Query::records_page(array('keyword' => $prefix . '-own'))['total'], 'server keyword search reaches older rows');

    // Interleave the channels by insertion ID; all pages must eventually expose every state.
    $expected = array();
    for ($i = 0; $i < 105; ++$i) {
        $order = $prefix . '-queue-' . $i;
        $owner = 0 === $i % 2 ? $users[0] : $users[1];
        if ($i < 100) {
            $key = Npcink_Pay_Refund_Admin_Public::refund_uncertain_name($order, '支付宝');
            $data = array('n_order' => $order, 'n_type' => '支付宝', 'n_user_id' => $owner, 'request_id' => $order, 'n_amount' => 7);
        } elseif ($i < 103) {
            $key = Npcink_Pay_Refund_Admin_Wx::pending_refund_option_name($order);
            $data = array('n_order' => $order, 'n_user_id' => $owner, 'request_id' => $order, 'amount' => 7);
        } else {
            $key = Npcink_Pay_Refund_Admin_Public::refund_reconciliation_name($order, '微信');
            $data = array('n_order' => $order, 'n_type' => '微信', 'n_user_id' => $owner, 'n_amount' => 7);
        }
        update_option($key, $data, false); $option_names[] = $key;
        if ($owner === $users[0]) { $expected[] = $order; }
    }
    wp_set_current_user($users[0]);
    $queue = Npcink_Pay_Refund_Admin_Public::refund_operational_state_page(100);
    npcink_integration_assert(count($expected) === count($queue['rows']), 'queue only contains current operator states across both channels');
    npcink_integration_assert(count($expected) === array_sum(Npcink_Pay_Refund_Admin_Public::refund_operational_state_counts()), 'notice counts are scoped to the visible operator page');
    wp_set_current_user($admin->ID);
    $cursor = 0; $seen = array(); $kinds = array(); $iterations = 0;
    do {
        $queue = Npcink_Pay_Refund_Admin_Public::refund_operational_state_page(20, $cursor);
        foreach ($queue['rows'] as $row) { if (str_starts_with($row['order'], $prefix)) { $seen[] = $row['order']; $kinds[] = $row['kind']; } }
        $cursor = $queue['next_cursor'];
        if (++$iterations > 20) { throw new RuntimeException('Queue cursor did not advance'); }
    } while ($cursor);
    npcink_integration_assert(105 === count(array_unique($seen)), 'global keyset pages do not starve WeChat behind 100 Alipay states');
    npcink_integration_assert(3 === count(array_unique($kinds)), 'global queue reaches uncertain, pending and reconciliation states');

    $order = $prefix . '-cas';
    $key = Npcink_Pay_Refund_Admin_Public::refund_lock_name($order, '微信'); $option_names[] = $key;
    $expired = time() - 601;
    update_option($key, $expired, false);
    npcink_integration_assert(Npcink_Pay_Refund_Admin_Public::claim_refund($order, '微信'), 'real SQL CAS takes over expired claim');
    npcink_integration_assert(!Npcink_Pay_Refund_Admin_Public::compare_option($key, $expired, 'stale-owner'), 'real SQL rejects stale CAS contender');
    $new_owner = array('time' => time(), 'token' => 'replacement');
    update_option($key, $new_owner, false);
    Npcink_Pay_Refund_Admin_Public::release_refund_claim($order, '微信');
    npcink_integration_assert($new_owner === get_option($key), 'real SQL token release preserves replacement claim');
    $second_db = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    $mutex = 'npcink:' . substr(hash('sha256', $wpdb->prefix . '|微信|' . $order), 0, 56);
    npcink_integration_assert('0' === (string) $second_db->get_var($second_db->prepare('SELECT GET_LOCK(%s, 0)', $mutex)), 'independent database connection cannot enter the same order operation');
    $second_db->close();

    require $root . '/tests/lock-integration.php';

    $refund_order = $prefix . '-write-failure';
    foreach (array(Npcink_Pay_Refund_Admin_Wx::pending_refund_option_name($refund_order),
        Npcink_Pay_Refund_Admin_Public::refund_reconciliation_name($refund_order, '微信')) as $key) { $option_names[] = $key; }
    Npcink_Pay_Refund_Admin_Wx::save_pending_refund($refund_order, 'Operator', 7, 'coupon refund', wp_date('Y-m-d H:i:s'), 1000);
    $response = (object) array('status' => 'SUCCESS', 'out_trade_no' => $refund_order,
        'out_refund_no' => Npcink_Pay_Refund_Admin_Wx::active_refund_request_id($refund_order),
        'amount' => (object) array('total' => 1000, 'refund' => 1000, 'payer_refund' => 700, 'currency' => 'CNY'));
    $break_insert = static function ($sql) use ($table) {
        return str_starts_with($sql, 'INSERT INTO') && str_contains($sql, $table) ? 'NPCINK_INJECTED_INSERT_FAILURE' : $sql;
    };
    add_filter('query', $break_insert);
    $previous_suppression = $wpdb->suppress_errors(true);
    $recorded = Npcink_Pay_Refund_Admin_Wx::record_successful_refund($refund_order, $response);
    $wpdb->suppress_errors($previous_suppression);
    remove_filter('query', $break_insert);
    npcink_integration_assert(false === $recorded && Npcink_Pay_Refund_Admin_Wx::get_pending_refund($refund_order)
        && Npcink_Pay_Refund_Admin_Public::get_refund_reconciliation($refund_order, '微信'), 'real audit insert failure preserves recovery context');
    npcink_integration_assert(false !== Npcink_Pay_Refund_Admin_Public::retry_refund_reconciliation($refund_order, '微信'), 'local reconciliation recovers without any gateway client');
    npcink_integration_assert(Npcink_Pay_Refund_Admin_Wx::record_successful_refund($refund_order, $response)
        && !Npcink_Pay_Refund_Admin_Wx::get_pending_refund($refund_order), 'provider re-query clears pending after recovered audit');

    // Exercise the actual five callbacks with real WP nonces/capabilities, without network.
    class Npcink_Integration_Ajax_Exit extends RuntimeException {}
    add_filter('wp_doing_ajax', '__return_true');
    $die_handler = static function () { return static function () { throw new Npcink_Integration_Ajax_Exit(); }; };
    add_filter('wp_die_ajax_handler', $die_handler);
    foreach (array('Zfb' => array('npcink_pay_refund_zfb_order_query', 'npcink_pay_refund_zfb_order_refund'),
        'Wx' => array('npcink_pay_refund_wx_order_query', 'npcink_pay_refund_wx_order_refund', 'npcink_pay_refund_wx_refund_status')) as $channel => $handlers) {
        foreach ($handlers as $handler) {
            foreach (array(false, true) as $valid_nonce) {
                wp_set_current_user($users[2]);
                $_REQUEST['nonce'] = $valid_nonce ? wp_create_nonce('npcink_pay_refund_action') : 'invalid';
                ob_start();
                try { call_user_func(array('Npcink_Pay_Refund_Admin_' . $channel, $handler)); }
                catch (Npcink_Integration_Ajax_Exit $exit) {}
                $response = json_decode(ob_get_clean(), true);
                $message = $valid_nonce ? '您没有退款操作权限。' : '请求校验失败，请刷新页面后重试。';
                npcink_integration_assert(false === ($response['success'] ?? null) && $message === ($response['data']['message'] ?? null), $handler . ' denies ' . ($valid_nonce ? 'unauthorized user' : 'invalid nonce'));
            }
        }
    }
    wp_set_current_user($users[0]);
    ob_start();
    try { Npcink_Pay_Refund_Admin_Public::require_order_operation($prefix . '-queue-1', '支付宝'); }
    catch (Npcink_Integration_Ajax_Exit $exit) {}
    $response = json_decode(ob_get_clean(), true);
    npcink_integration_assert('该订单的待处理退款属于其他操作者，请联系管理员。' === ($response['data']['message'] ?? null), 'operator cannot mutate another operator pending context by guessing its order');
    remove_filter('wp_die_ajax_handler', $die_handler);
    remove_filter('wp_doing_ajax', '__return_true');
    unset($_REQUEST['nonce']);

    // Uninstall-preserve/reinstall must regrant configured users even when the marker existed.
    update_option('npcink_pay_refund_config', array('user' => array('user' => array($users[0])), 'config' => array('mysql' => 0, 'config' => 0)), false);
    update_option('npcink_pay_refund_capability_version', '1', false);
    Npcink_Pay_Refund_Admin_Uninstall::run();
    npcink_integration_assert(false === get_option('npcink_pay_refund_capability_version', false), 'preserve-settings uninstall resets grant migration marker');
    Npcink_Pay_Refund_Activator::activate();
    Npcink_Pay_Refund_Admin_Authority::maybe_sync_refund_capabilities();
    npcink_integration_assert(user_can(get_userdata($users[0]), 'npcink_refund_orders'), 'reinstall restores retained operator grant');

    // The real smoke script must unwind configuration and temporary users on failure.
    wp_set_current_user($admin->ID);
    $config_before = get_option('npcink_pay_refund_config');
    update_option('npcink_pay_refund_secrets', array('zfb' => array('private_key' => 'synthetic-restoration-marker')), false);
    $secrets_before = get_option('npcink_pay_refund_secrets');
    $users_before = get_users(array('fields' => 'ids'));
    putenv('NPCINK_PAY_REFUND_SMOKE_FAIL=after-config');
    $failed = false;
    try { (static function ($smoke_path) { require $smoke_path; })($root . '/bin/smoke-admin.php'); }
    catch (RuntimeException $error) { $failed = str_contains($error->getMessage(), 'injected failure'); }
    putenv('NPCINK_PAY_REFUND_SMOKE_FAIL');
    npcink_integration_assert($failed && $config_before === get_option('npcink_pay_refund_config') && $secrets_before === get_option('npcink_pay_refund_secrets'), 'mid-smoke exception restores exact config and secrets');
    npcink_integration_assert($users_before === get_users(array('fields' => 'ids')), 'mid-smoke exception deletes all temporary users');
} finally {
    wp_set_current_user($original_user);
    foreach ($option_names as $name) { delete_option($name); }
    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE n_order LIKE %s", $wpdb->esc_like($prefix) . '%'));
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($users as $id) { wp_delete_user($id); }
    foreach (array('npcink_pay_refund_config' => $original_config, 'npcink_pay_refund_secrets' => $original_secrets, 'npcink_pay_refund_capability_version' => $original_cap_version) as $name => $value) {
        if (null === $value) { delete_option($name); } else { update_option($name, $value, false); }
    }
}
echo "WordPress/MySQL integration checks passed.\n";

})();
