<?php

/**
 * 在插件激活期间激发
 *
 * @link       https://www.npc.ink
 * @since      1.0.0
 *
 * @package    Mare
 * @subpackage Mare/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Mare
 * @subpackage Mare/includes
 * @author     Muze <1355471563@qq.com>
 */
class Mare_Activator
{

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate()
	{

		self::create_refund_order();
	}

	/**
	 * 创建数据库存储退款记录
	 */
	public static function create_refund_order()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'npc_refund_order';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			n_amount decimal(10,2) NOT NULL,
			n_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			n_order varchar(255) NOT NULL,
			n_user varchar(255) NOT NULL,
			n_type varchar(255) NOT NULL,
			n_reason text NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY n_order_type (n_order(191), n_type(32)),
			KEY n_time (n_time)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
}//end
