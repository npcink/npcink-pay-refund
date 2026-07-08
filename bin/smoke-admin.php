<?php
/**
 * Minimal admin smoke checks for the local WordPress test site.
 *
 * Run with:
 * wp eval-file bin/smoke-admin.php --path=/path/to/wp --url=http://test.local --allow-root
 */

if (!defined('ABSPATH')) {
	exit(1);
}

function npcink_pay_refund_smoke_fail($message)
{
	fwrite(STDERR, 'Smoke check failed: ' . $message . PHP_EOL);
	exit(1);
}

function npcink_pay_refund_smoke_assert($condition, $message)
{
	if (!$condition) {
		npcink_pay_refund_smoke_fail($message);
	}
}

function npcink_pay_refund_smoke_first_user_with_cap($capability)
{
	$users = get_users(array(
		'capability' => $capability,
		'number' => 1,
		'fields' => array('ID'),
	));

	return empty($users) ? 0 : (int) $users[0]->ID;
}

function npcink_pay_refund_smoke_user($login, $role)
{
	$user = get_user_by('login', $login);
	if ($user) {
		$user->set_role($role);
		return array((int) $user->ID, false);
	}

	$user_id = wp_insert_user(array(
		'user_login' => $login,
		'user_pass' => wp_generate_password(24, true),
		'user_email' => $login . '@example.invalid',
		'display_name' => $login,
		'role' => $role,
	));

	if (is_wp_error($user_id)) {
		npcink_pay_refund_smoke_fail($user_id->get_error_message());
	}

	return array((int) $user_id, true);
}

$original_config = get_option('npcink_pay_refund_config', null);
$original_secrets = get_option('npcink_pay_refund_secrets', null);
$created_users = array();

try {
	$plugin_dir = trailingslashit(WP_PLUGIN_DIR) . 'npcink-pay-refund/';
	foreach (array(
		'admin/class-npcink-pay-refund-admin.php',
		'admin/partials/npcink-pay-refund-admin-authority.php',
		'admin/partials/npcink-pay-refund-admin-config.php',
		'admin/partials/npcink-pay-refund-admin-query.php',
		'admin/pay/npcink-pay-refund-admin-public.php',
		'admin/pay/npcink-pay-refund-admin-zfb.php',
		'admin/pay/npcink-pay-refund-admin-wx.php',
	) as $relative_file) {
		$file = $plugin_dir . $relative_file;
		npcink_pay_refund_smoke_assert(file_exists($file), 'missing plugin file ' . $relative_file);
		require_once $file;
	}

	npcink_pay_refund_smoke_assert(class_exists('Npcink_Pay_Refund_Admin_Config'), 'settings class is unavailable');
	npcink_pay_refund_smoke_assert(class_exists('Npcink_Pay_Refund_Admin_Query'), 'refund query class is unavailable');
	npcink_pay_refund_smoke_assert(class_exists('Npcink_Pay_Refund_Admin_Authority'), 'authority class is unavailable');
	npcink_pay_refund_smoke_assert(class_exists('Npcink_Pay_Refund_Admin_Zfb'), 'Alipay class is unavailable');
	npcink_pay_refund_smoke_assert(class_exists('Npcink_Pay_Refund_Admin_Wx'), 'WeChat class is unavailable');

	$admin_id = npcink_pay_refund_smoke_first_user_with_cap('manage_options');
	npcink_pay_refund_smoke_assert($admin_id > 0, 'no administrator user found');

	list($author_id, $author_created) = npcink_pay_refund_smoke_user('npcink_refund_smoke_author', 'author');
	list($subscriber_id, $subscriber_created) = npcink_pay_refund_smoke_user('npcink_refund_smoke_subscriber', 'subscriber');
	if ($author_created) {
		$created_users[] = $author_id;
	}
	if ($subscriber_created) {
		$created_users[] = $subscriber_id;
	}

	update_option('npcink_pay_refund_config', array(
		'zfb' => array(
			'appid' => '',
		),
		'wx' => array(
			'mch_id' => '',
			'cert_api' => '',
			'platform_key_id' => '',
		),
		'user' => array(
			'user' => array($author_id),
			'link' => array(
				array(
					'title' => '订单退款',
					'url' => admin_url('index.php?page=npcink_pay_refund_query'),
				),
			),
		),
		'config' => array(
			'mysql' => 1,
			'config' => 1,
		),
	), false);
	delete_option('npcink_pay_refund_secrets');

	wp_set_current_user($admin_id);

	ob_start();
	Npcink_Pay_Refund_Admin_Config::menu_displays();
	$settings_html = ob_get_clean();
	foreach (array(
		'npcink-pay-refund-tab-zfb',
		'npcink-pay-refund-tab-wx',
		'npcink-pay-refund-tab-authority',
		'npcink-pay-refund-tab-data',
		'npcink-pay-refund-refund-user-search',
		'npcink-pay-refund-selected-refund-users',
		'npcink-pay-refund-download',
	) as $needle) {
		npcink_pay_refund_smoke_assert(false !== strpos($settings_html, $needle), 'settings page missing ' . $needle);
	}

	ob_start();
	Npcink_Pay_Refund_Admin_Query::menu_displays();
	$query_html = ob_get_clean();
	foreach (array(
		'npcink-pay-refund-wx-input',
		'npcink-pay-refund-zfb-input',
		'npcink-pay-refund-records',
	) as $needle) {
		npcink_pay_refund_smoke_assert(false !== strpos($query_html, $needle), 'refund page missing ' . $needle);
	}

	wp_set_current_user($admin_id);
	npcink_pay_refund_smoke_assert(Npcink_Pay_Refund_Admin_Authority::current_user_can_refund(), 'administrator cannot refund');

	wp_set_current_user($author_id);
	npcink_pay_refund_smoke_assert(Npcink_Pay_Refund_Admin_Authority::current_user_can_refund(), 'selected author cannot refund');

	wp_set_current_user($subscriber_id);
	npcink_pay_refund_smoke_assert(!Npcink_Pay_Refund_Admin_Authority::current_user_can_refund(), 'subscriber can refund unexpectedly');

	wp_set_current_user($admin_id);
	$sanitized_users = Npcink_Pay_Refund_Admin_Config::sanitize_refund_user_ids(array($admin_id, $author_id, $subscriber_id));
	npcink_pay_refund_smoke_assert(array($author_id) === $sanitized_users, 'refund user sanitizer accepted invalid roles');

	$zfb_diagnosis = Npcink_Pay_Refund_Admin_Zfb::diagnose_config();
	npcink_pay_refund_smoke_assert('error' === $zfb_diagnosis['status'], 'empty Alipay config should fail diagnosis');
	npcink_pay_refund_smoke_assert(!empty($zfb_diagnosis['items']), 'Alipay diagnosis returned no items');
	npcink_pay_refund_smoke_assert(!Npcink_Pay_Refund_Admin_Zfb::ensure_sdk_ready(), 'empty Alipay config should not initialize SDK');

	$wx_diagnosis = Npcink_Pay_Refund_Admin_Wx::diagnose_config();
	npcink_pay_refund_smoke_assert('error' === $wx_diagnosis['status'], 'empty WeChat config should fail diagnosis');
	npcink_pay_refund_smoke_assert(!empty($wx_diagnosis['items']), 'WeChat diagnosis returned no items');
	Npcink_Pay_Refund_Admin_Wx::$client = null;
	Npcink_Pay_Refund_Admin_Wx::$merchant_config = array();
	npcink_pay_refund_smoke_assert(!Npcink_Pay_Refund_Admin_Wx::ensure_client_ready(), 'empty WeChat config should not initialize client');
} finally {
	wp_set_current_user(0);

	if (null === $original_config) {
		delete_option('npcink_pay_refund_config');
	} else {
		update_option('npcink_pay_refund_config', $original_config, false);
	}

	if (null === $original_secrets) {
		delete_option('npcink_pay_refund_secrets');
	} else {
		update_option('npcink_pay_refund_secrets', $original_secrets, false);
	}

	if (!empty($created_users)) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ($created_users as $user_id) {
			wp_delete_user($user_id);
		}
	}
}

echo "Admin smoke checks passed.\n";
