<?php
/** Read-only compatibility smoke for an existing site; no provider requests or fixtures. */
if (!defined('WP_CLI') || !WP_CLI || !defined('ABSPATH')) {
    throw new RuntimeException('Run with wp eval-file bin/check-installed-site.php --path=/explicit/site.');
}
(static function () {
    global $wpdb;
    $plugin = WP_PLUGIN_DIR . '/npcink-pay-refund/';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    if (!is_plugin_active('npcink-pay-refund/npcink-pay-refund.php')) {
        throw new RuntimeException('The installed refund plugin must be active.');
    }
    foreach (array('admin/partials/npcink-pay-refund-admin-config.php', 'admin/partials/npcink-pay-refund-admin-authority.php',
        'admin/partials/npcink-pay-refund-admin-query.php', 'admin/pay/npcink-pay-refund-admin-public.php',
        'admin/pay/npcink-pay-refund-admin-wx.php', 'admin/pay/npcink-pay-refund-admin-zfb.php',
        'includes/class-npcink-pay-refund-activator.php') as $file) { require_once $plugin . $file; }
    $report = array('php' => PHP_VERSION, 'wordpress' => get_bloginfo('version'), 'checks' => array());
    $assert = static function ($condition, $label) use (&$report) {
        if (!$condition) { throw new RuntimeException('Installed-site check failed: ' . $label); }
        $report['checks'][] = $label;
    };
    $before = array(get_option('npcink_pay_refund_config', false), get_option('npcink_pay_refund_secrets', false));
    $user = get_current_user_id(); $request = $_REQUEST;
    $ajax_filter = static function () { return true; };
    $die_handler = static function () { return static function () { throw new RuntimeException('npcink-readonly-ajax-exit'); }; };
    try {
        $metadata = get_plugin_data($plugin . 'npcink-pay-refund.php', false, false);
        $assert($metadata['Version'] === NPCINK_PAY_REFUND_VERSION, 'installed header and loaded version agree');
        $assert(Npcink_Pay_Refund_Activator::schema_is_current(), 'installed audit schema and indexes verified');
        $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
        $assert(!empty($admins), 'existing administrator available; no test user created');
        wp_set_current_user((int) $admins[0]);
        $assert(Npcink_Pay_Refund_Admin_Authority::current_user_can_refund(), 'administrator can access refund pages');
        foreach (array('Npcink_Pay_Refund_Admin_Config' => 'npcink-pay-refund-tab-authority',
            'Npcink_Pay_Refund_Admin_Query' => 'npcink-pay-refund-records') as $class => $needle) {
            ob_start();
            try { $class::menu_displays(); $html = ob_get_contents(); }
            finally { ob_end_clean(); }
            $assert(false !== strpos($html, $needle), $class . ' renders installed page');
        }
        $page = Npcink_Pay_Refund_Admin_Query::records_page();
        $table = esc_sql($wpdb->prefix . 'npcink_pay_refund_order');
        $assert((int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") === $page['total'], 'administrator record total matches actual audit table');
        $cursor = 0; $pages = 0; $states = 0;
        do {
            $page = Npcink_Pay_Refund_Admin_Public::refund_operational_state_page(100, $cursor);
            $states += count($page['rows']);
            if (++$pages > 10000 || ($page['next_cursor'] && $page['next_cursor'] <= $cursor)) {
                throw new RuntimeException('Pending queue cursor did not advance.');
            }
            $cursor = $page['next_cursor'];
        } while ($cursor);
        $report['visible_pending_states'] = $states; $report['pending_pages_read'] = $pages;
        wp_set_current_user(0);
        $assert(!Npcink_Pay_Refund_Admin_Authority::current_user_can_refund(), 'anonymous user has no refund capability');
        add_filter('wp_doing_ajax', $ajax_filter);
        add_filter('wp_die_ajax_handler', $die_handler);
        foreach (array('Zfb' => array('npcink_pay_refund_zfb_order_query', 'npcink_pay_refund_zfb_order_refund'),
            'Wx' => array('npcink_pay_refund_wx_order_query', 'npcink_pay_refund_wx_order_refund', 'npcink_pay_refund_wx_refund_status')) as $channel => $handlers) {
            foreach ($handlers as $handler) {
                foreach (array(false, true) as $valid_nonce) {
                    $_REQUEST = array('nonce' => $valid_nonce ? wp_create_nonce('npcink_pay_refund_action') : 'invalid');
                    ob_start();
                    try { call_user_func(array('Npcink_Pay_Refund_Admin_' . $channel, $handler)); }
                    catch (RuntimeException $error) { if ('npcink-readonly-ajax-exit' !== $error->getMessage()) { throw $error; } }
                    finally { $body = ob_get_clean(); }
                    $response = json_decode($body, true);
                    $message = $valid_nonce ? __('您没有退款操作权限。', 'npcink-pay-refund') : __('请求校验失败，请刷新页面后重试。', 'npcink-pay-refund');
                    $assert(false === ($response['success'] ?? null) && $message === ($response['data']['message'] ?? null),
                        $handler . ' rejects ' . ($valid_nonce ? 'anonymous permission' : 'invalid nonce') . ' before provider access');
                }
            }
        }
        $assert($before === array(get_option('npcink_pay_refund_config', false), get_option('npcink_pay_refund_secrets', false)), 'configuration and secrets unchanged');
    } finally {
        remove_filter('wp_doing_ajax', $ajax_filter);
        remove_filter('wp_die_ajax_handler', $die_handler);
        wp_set_current_user($user); $_REQUEST = $request;
    }
    $report['result'] = 'passed';
    $report['scope'] = 'Existing installed plugin only; no fixtures, settings writes, gateway calls or lifecycle changes.';
    echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
})();
