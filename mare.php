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
 * @package           Mare
 *
 * @wordpress-plugin
 * Plugin Name:       魔法退款 - 新架构
 * Plugin URI:        https://www.npc.ink/277376.html
 * Description:       支持支付宝官方和微信官方退款功能，使用官方提供的SDK，带权限控制，删除此插件会同时删除退款记录表，请谨慎。
 * Version:           1.0.8
 * Author:            Muze
 * Author URI:        https://www.npc.ink
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       mare
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
define('MARE_VERSION', '1.0.8');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-mare-activator.php
 */
function activate_mare()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-mare-activator.php';
	Mare_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-mare-deactivator.php
 */
function deactivate_mare()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-mare-deactivator.php';
	Mare_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_mare');
register_deactivation_hook(__FILE__, 'deactivate_mare');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-mare.php';


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_mare()
{

	$plugin = new Mare();

	$plugin->run();
}
run_mare();






require_once plugin_dir_path(__FILE__) . 'index.php';


/**加载微信支付 */
require_once plugin_dir_path(__FILE__) . 'admin/pay/mare-admin-wx.php';
function npcink_run_wx()
{

	$plugin = new Mare_Admin_Wx();
	$plugin->run();
}
npcink_run_wx();

//设置按钮
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
	$links[] = '<a href="' . get_admin_url(null, 'options-general.php?page=refun_config') . '">' . __('设置', 'n') . '</a>';
	return $links;
});
