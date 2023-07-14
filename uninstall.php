<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://www.npc.ink
 * @since      1.0.0
 *
 * @package    Mare
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}







// 执行需要在删除插件时进行的操作

// 删除数据库表
// 获取 $wpdb 全局对象
global $wpdb;

// 定义要删除的数据表名
$table_name = $wpdb->prefix . 'npc_refund_order';

// 判断数据表是否存在
if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
	//数据表不存在！
	return "";
} else {
	// 执行删除数据表操作
	$wpdb->query("DROP TABLE IF EXISTS $table_name");

	//return "数据表删除成功！";
}

// 删除其他数据或执行其他操作

// 删除插件设置
delete_option('your_plugin_options');
