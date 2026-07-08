<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * 为插件提供管理区域视图
 *
 * 该文件用于标记插件面向管理的方面。
 *
 * @link       https://www.npc.ink
 * @since      1.0.0
 *
 * @package    Mare
 * @subpackage Mare/admin/partials
 */

if (!class_exists('Mare_Admin_Public')) {
	class Mare_Admin_Public
	{
		public static function run()
		{
			add_action('admin_init', array(__CLASS__, 'maybe_upgrade_schema'));



			//获取当前页hook
			//add_action('admin_enqueue_scripts', array(__CLASS__, 'hook'));
		}

		public static function maybe_upgrade_schema()
		{
			$schema_version = get_option('npc_refund_schema_version', '');
			if ($schema_version === MARE_VERSION) {
				return;
			}

			require_once plugin_dir_path(dirname(__DIR__)) . 'includes/class-mare-activator.php';
			Mare_Activator::create_refund_order();

			if (false === get_option('npc_refund_schema_version', false)) {
				add_option('npc_refund_schema_version', MARE_VERSION, '', 'no');
			} else {
				update_option('npc_refund_schema_version', MARE_VERSION);
			}
		}





		/**
		 * 添加数据进数据库文件中
		 */
		public static function add_data($time, $user, $amount, $order, $reason, $type)
		{
			global $wpdb;
			$table_name = esc_sql($wpdb->prefix . 'npc_refund_order');
			$order = sanitize_text_field($order);
			$type = sanitize_text_field($type);

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom refund table lookup with a sanitized table name.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table_name} WHERE n_order = %s AND n_type = %s LIMIT 1",
					$order,
					$type
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ($exists) {
				self::release_refund_claim($order, $type);
				return false;
			}

			$data = array(
				'n_amount' => (float) $amount,
				'n_time' => sanitize_text_field($time),
				'n_order' => $order,
				'n_user' => sanitize_text_field($user),
				'n_type' => $type,
				'n_reason' => Mare_Admin::sanitize_textarea_value($reason)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom refund table insert.
			$result = $wpdb->insert($table_name, $data);
			self::release_refund_claim($order, $type);
			return $result;
		}

		public static function has_refund_record($order, $type)
		{
			global $wpdb;
			$table_name = esc_sql($wpdb->prefix . 'npc_refund_order');
			$order = sanitize_text_field($order);
			$type = sanitize_text_field($type);

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom refund table lookup with a sanitized table name.
			$exists = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table_name} WHERE n_order = %s AND n_type = %s LIMIT 1",
					$order,
					$type
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $exists;
		}

		public static function claim_refund($order, $type)
		{
			$order = sanitize_text_field($order);
			$type = sanitize_text_field($type);

			if (self::has_refund_record($order, $type)) {
				return false;
			}

			$lock_name = self::refund_lock_name($order, $type);
			$locked_at = (int) get_option($lock_name, 0);
			if ($locked_at > 0 && time() - $locked_at < 10 * MINUTE_IN_SECONDS) {
				return false;
			}

			if ($locked_at > 0) {
				delete_option($lock_name);
			}

			return add_option($lock_name, time(), '', 'no');
		}

		public static function release_refund_claim($order, $type)
		{
			delete_option(self::refund_lock_name($order, $type));
		}

		public static function refund_lock_name($order, $type)
		{
			return 'mare_refund_lock_' . md5(sanitize_text_field($type) . '|' . sanitize_text_field($order));
		}


		//时间对比
		//若输入的时间与当前时间对比超过7天，则输出false
		public static function contrast_time($time)
		{
			// 将 $time 转换为 DateTime 对象
			$timeObj = DateTime::createFromFormat('Y-m-d H:i:s', $time);
			if (!$timeObj) {
				return false;
			}

			// 计算时间差
			$interval = $timeObj->diff(new DateTime());

			// 判断时间差是否超过 7 天
			if ($interval->days > 7) {
				// 超过 7 天，返回 false
				$result = false;
			} else {
				// 没有超过 7 天，返回 true
				$result = true;
			}
			return $result;
		}






		/**
		 * 展示当前页hook信息
		 */
		public static function hook($hook)
		{
			echo '<h1 style="color: crimson;text-align: center;">' . esc_html($hook) . '</h1>';
		}
	} //end
}
