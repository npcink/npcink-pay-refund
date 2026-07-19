<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.npc.ink
 * @since             1.0.0
 * @package           Npcink_Pay_Refund
 *
 * @wordpress-plugin
 * Plugin Name:       Npcink Pay Refund
 * Plugin URI:        https://www.npc.ink/277376.html
 * Description:       支持支付宝官方和微信官方退款功能，使用官方提供的SDK，带权限控制。
 * Version:           1.3.5
 * Author:            Muze
 * Author URI:        https://www.npc.ink
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       npcink-pay-refund
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('NPCINK_PAY_REFUND_VERSION', '1.3.5');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-npcink-pay-refund-activator.php
 */
function npcink_pay_refund_activate()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-npcink-pay-refund-activator.php';
	Npcink_Pay_Refund_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-npcink-pay-refund-deactivator.php
 */
function npcink_pay_refund_deactivate()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-npcink-pay-refund-deactivator.php';
	Npcink_Pay_Refund_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'npcink_pay_refund_activate');
register_deactivation_hook(__FILE__, 'npcink_pay_refund_deactivate');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-npcink-pay-refund.php';

if (file_exists(plugin_dir_path(__FILE__) . 'vendor/autoload.php')) {
	require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
}


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function npcink_pay_refund_run()
{

	$plugin = new Npcink_Pay_Refund();

	$plugin->run();
}
npcink_pay_refund_run();






require_once plugin_dir_path(__FILE__) . 'index.php';


/**加载微信支付 */
if (is_admin()) {
	require_once plugin_dir_path(__FILE__) . 'admin/pay/npcink-pay-refund-admin-wx.php';
	function npcink_pay_refund_run_wx()
	{
		$plugin = new Npcink_Pay_Refund_Admin_Wx();
		$plugin->run();
	}
	npcink_pay_refund_run_wx();
}

//设置按钮
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
	$links[] = '<a href="' . esc_url(get_admin_url(null, 'plugins.php?page=npcink_pay_refund_config')) . '">' . esc_html__('设置', 'npcink-pay-refund') . '</a>';
	return $links;
});
