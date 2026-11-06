<?php

/**
 * 在插件激活期间激发
 *
 * @link       https://www.npc.ink
 * @since      1.0.0
 *
 * @package    Npcink_Pay_Refund
 * @subpackage Npcink_Pay_Refund/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Npcink_Pay_Refund
 * @subpackage Npcink_Pay_Refund/includes
 * @author     Muze <1355471563@qq.com>
 */
class Npcink_Pay_Refund_Activator
{
    const SCHEMA_VERSION = '2';

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
			self::add_refund_capability();
		}

		public static function add_refund_capability()
		{
			$role = get_role('administrator');
			if ($role) {
				$role->add_cap('npcink_refund_orders');
			}
		}

	/**
	 * 创建数据库存储退款记录
	 */
	public static function create_refund_order()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'npcink_pay_refund_order';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			n_amount decimal(10,2) NOT NULL,
			n_time datetime NOT NULL,
			n_order varchar(255) NOT NULL,
			n_user varchar(255) NOT NULL,
			n_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			n_type varchar(255) NOT NULL,
			n_reason text NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY n_order_type (n_order(191), n_type(32)),
			KEY n_time (n_time),
			KEY n_user_id (n_user_id)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        return self::schema_is_current();
	}
    public static function schema_is_current()
    {
        global $wpdb;
        $table = esc_sql($wpdb->prefix . 'npcink_pay_refund_order');
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Verify actual DDL, never cached; core-prefixed escaped table name, WP 6.0 lacks %i.
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}");
        $actual = array();
        foreach ((array) $columns as $column) { $actual[$column->Field] = $column; }
        foreach (array('id', 'n_amount', 'n_time', 'n_order', 'n_user', 'n_user_id', 'n_type', 'n_reason') as $name) {
            if (!isset($actual[$name]) || 'NO' !== $actual[$name]->Null) { return false; }
        }
        if ('decimal(10,2)' !== strtolower($actual['n_amount']->Type)
            || 'datetime' !== strtolower($actual['n_time']->Type)
            || !preg_match('/^bigint(?:\(20\))? unsigned$/', strtolower($actual['n_user_id']->Type))) { return false; }
        foreach (array('n_order', 'n_user', 'n_type') as $name) {
            if ('varchar(255)' !== strtolower($actual[$name]->Type)) { return false; }
        }
        if ('text' !== strtolower($actual['n_reason']->Type)
            || !preg_match('/^mediumint(?:\(9\))?$/', strtolower($actual['id']->Type))
            || false === strpos($actual['id']->Extra, 'auto_increment')) { return false; }
        $indexes = array();
        foreach ((array) $wpdb->get_results("SHOW INDEX FROM {$table}") as $index) {
            $indexes[$index->Key_name][(int) $index->Seq_in_index] = array($index->Column_name, (int) $index->Sub_part, (int) $index->Non_unique);
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return isset($indexes['PRIMARY'][1], $indexes['n_order_type'][1], $indexes['n_order_type'][2], $indexes['n_time'][1], $indexes['n_user_id'][1])
            && 2 === count($indexes['n_order_type'])
            && array('id', 0, 0) === $indexes['PRIMARY'][1]
            && array('n_order', 191, 0) === $indexes['n_order_type'][1]
            && array('n_type', 32, 0) === $indexes['n_order_type'][2]
            && array('n_time', 0, 1) === $indexes['n_time'][1]
            && array('n_user_id', 0, 1) === $indexes['n_user_id'][1];
    }

}//end
