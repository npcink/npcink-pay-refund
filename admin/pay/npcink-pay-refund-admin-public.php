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
 * @package    Npcink_Pay_Refund
 * @subpackage Npcink_Pay_Refund/admin/partials
 */

if (!class_exists('Npcink_Pay_Refund_Admin_Public')) {
	class Npcink_Pay_Refund_Admin_Public
	{
		public static function run()
		{
			add_action('admin_init', array(__CLASS__, 'maybe_upgrade_schema'));
			add_action('admin_notices', array(__CLASS__, 'render_reconciliation_notice'));

			//获取当前页hook
			//add_action('admin_enqueue_scripts', array(__CLASS__, 'hook'));
		}

		public static function maybe_upgrade_schema()
		{
			$schema_version = get_option('npcink_pay_refund_schema_version', '');
			if ($schema_version === NPCINK_PAY_REFUND_VERSION) {
				return;
			}

			require_once plugin_dir_path(dirname(__DIR__)) . 'includes/class-npcink-pay-refund-activator.php';
			Npcink_Pay_Refund_Activator::create_refund_order();

			if (false === get_option('npcink_pay_refund_schema_version', false)) {
				add_option('npcink_pay_refund_schema_version', NPCINK_PAY_REFUND_VERSION, '', 'no');
			} else {
				update_option('npcink_pay_refund_schema_version', NPCINK_PAY_REFUND_VERSION);
			}
		}





		/**
		 * 添加数据进数据库文件中
		 */
		public static function add_data($time, $user, $amount, $order, $reason, $type)
		{
			global $wpdb;
			$table_name = esc_sql($wpdb->prefix . 'npcink_pay_refund_order');
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
				self::clear_refund_reconciliation($order, $type);
				self::clear_refund_uncertain($order, $type);
				self::release_refund_claim($order, $type);
				return true;
			}

			$data = array(
				'n_amount' => (float) $amount,
				'n_time' => sanitize_text_field($time),
				'n_order' => $order,
				'n_user' => sanitize_text_field($user),
				'n_type' => $type,
				'n_reason' => Npcink_Pay_Refund_Admin::sanitize_textarea_value($reason)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom refund table insert.
			$result = $wpdb->insert($table_name, $data);
			if (false === $result) {
				$reconciliation_saved = self::save_refund_reconciliation($data);
				if ($reconciliation_saved || !empty(self::get_refund_reconciliation($order, $type))) {
					self::clear_refund_uncertain($order, $type);
				}
				$reference = substr(hash('sha256', $type . '|' . $order), 0, 12);
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Preserve a concise reconciliation signal without logging credentials or gateway payloads.
				error_log('Npcink_Pay_Refund local audit write failed; reconciliation reference ' . $reference . '.');
				return false;
			}

			self::clear_refund_reconciliation($order, $type);
			self::clear_refund_uncertain($order, $type);
			self::release_refund_claim($order, $type);
			return $result;
		}

		public static function has_refund_record($order, $type)
		{
			global $wpdb;
			$table_name = esc_sql($wpdb->prefix . 'npcink_pay_refund_order');
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
				self::clear_refund_reconciliation($order, $type);
				self::clear_refund_uncertain($order, $type);
				return false;
			}

			if (!empty(self::get_refund_reconciliation($order, $type)) || !empty(self::get_refund_uncertain($order, $type))) {
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
			return 'npcink_pay_refund_lock_' . md5(sanitize_text_field($type) . '|' . sanitize_text_field($order));
		}

		public static function refund_reconciliation_name($order, $type)
		{
			return 'npcink_pay_refund_reconcile_' . md5(sanitize_text_field($type) . '|' . sanitize_text_field($order));
		}

		public static function refund_uncertain_name($order, $type)
		{
			return 'npcink_pay_refund_uncertain_' . md5(sanitize_text_field($type) . '|' . sanitize_text_field($order));
		}

		/**
		 * Persist the minimum context required to query a provider before retrying.
		 *
		 * Gateway payloads and credentials must never be stored in this option.
		 */
		public static function save_refund_uncertain($data)
		{
			$required = array('n_time', 'n_user', 'n_amount', 'n_order', 'n_reason', 'n_type', 'request_id');
			foreach ($required as $field) {
				if (!array_key_exists($field, $data)) {
					return false;
				}
			}

			$order = sanitize_text_field($data['n_order']);
			$type = sanitize_text_field($data['n_type']);
			$request_id = sanitize_text_field($data['request_id']);
			if ('' === $order || '' === $type || '' === $request_id) {
				return false;
			}

			$existing = self::get_refund_uncertain($order, $type);
			$stored = array(
				'n_time' => sanitize_text_field($data['n_time']),
				'n_user' => sanitize_text_field($data['n_user']),
				'n_amount' => (float) $data['n_amount'],
				'n_order' => $order,
				'n_reason' => Npcink_Pay_Refund_Admin::sanitize_textarea_value($data['n_reason']),
				'n_type' => $type,
				'request_id' => $request_id,
				'started_at' => isset($existing['started_at']) ? (int) $existing['started_at'] : time(),
				'updated_at' => time(),
			);

			return update_option(self::refund_uncertain_name($order, $type), $stored, false);
		}

		public static function get_refund_uncertain($order, $type)
		{
			$data = get_option(self::refund_uncertain_name($order, $type), array());
			return is_array($data) ? $data : array();
		}

		public static function clear_refund_uncertain($order, $type)
		{
			delete_option(self::refund_uncertain_name($order, $type));
		}

		public static function save_refund_reconciliation($data)
		{
			$order = isset($data['n_order']) ? sanitize_text_field($data['n_order']) : '';
			$type = isset($data['n_type']) ? sanitize_text_field($data['n_type']) : '';
			if ('' === $order || '' === $type) {
				return false;
			}

			$data['n_order'] = $order;
			$data['n_type'] = $type;
			$data['recording_failed_at'] = time();

			return update_option(self::refund_reconciliation_name($order, $type), $data, false);
		}

		public static function get_refund_reconciliation($order, $type)
		{
			$data = get_option(self::refund_reconciliation_name($order, $type), array());
			return is_array($data) ? $data : array();
		}

		/**
		 * Retry only the local audit write for a refund already accepted by a provider.
		 *
		 * This deliberately does not call either payment gateway. The stored
		 * reconciliation payload is the source of truth for the retry.
		 */
		public static function retry_refund_reconciliation($order, $type)
		{
			$data = self::get_refund_reconciliation($order, $type);
			foreach (array('n_time', 'n_user', 'n_amount', 'n_order', 'n_reason', 'n_type') as $field) {
				if (!array_key_exists($field, $data)) {
					return false;
				}
			}

			if (sanitize_text_field($order) !== sanitize_text_field($data['n_order']) || sanitize_text_field($type) !== sanitize_text_field($data['n_type'])) {
				return false;
			}

			return self::add_data(
				$data['n_time'],
				$data['n_user'],
				$data['n_amount'],
				$data['n_order'],
				$data['n_reason'],
				$data['n_type']
			);
		}

		public static function clear_refund_reconciliation($order, $type)
		{
			delete_option(self::refund_reconciliation_name($order, $type));
		}

		public static function count_options_by_prefix($prefix)
		{
			global $wpdb;
			if (!isset($wpdb->options)) {
				return 0;
			}

			$options_table = esc_sql($wpdb->options);
			$like = $wpdb->esc_like($prefix) . '%';
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lightweight operational count over this plugin's generated options.
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$options_table} WHERE option_name LIKE %s",
					$like
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			return max(0, (int) $count);
		}

		public static function refund_operational_state_counts()
		{
			return array(
				'uncertain' => self::count_options_by_prefix('npcink_pay_refund_uncertain_'),
				'wechat_pending' => self::count_options_by_prefix('npcink_pay_refund_pending_wx_'),
				'reconciliation' => self::count_options_by_prefix('npcink_pay_refund_reconcile_'),
			);
		}

		public static function render_reconciliation_notice()
		{
			if (!class_exists('Npcink_Pay_Refund_Admin_Authority') || !Npcink_Pay_Refund_Admin_Authority::current_user_can_refund()) {
				return;
			}

			$counts = self::refund_operational_state_counts();
			if (0 === array_sum($counts)) {
				return;
			}

			/* translators: 1: Alipay uncertain count, 2: WeChat pending count, 3: local audit reconciliation count. */
			$message_format = __('退款对账提醒：支付宝结果待确认 %1$d 项，微信待查询或补记 %2$d 项，本地记录待补记 %3$d 项。状态项可能对应同一订单；为避免重复退款，请先查询核对。', 'npcink-pay-refund');
			$message = sprintf(
				$message_format,
				$counts['uncertain'],
				$counts['wechat_pending'],
				$counts['reconciliation']
			);

			echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Npcink Pay Refund', 'npcink-pay-refund') . '：</strong> ' . esc_html($message) . ' <a href="' . esc_url(admin_url('index.php?page=npcink_pay_refund_query')) . '">' . esc_html__('打开订单退款查询', 'npcink-pay-refund') . '</a></p></div>';
		}


		//时间对比
		//若输入的时间与当前时间对比超过配置的退款时间窗口，则输出false
		public static function contrast_time($time)
		{
			// 将 $time 转换为 DateTime 对象
			$timeObj = DateTime::createFromFormat('Y-m-d H:i:s', $time);
			if (!$timeObj) {
				return false;
			}

			// 计算时间差
			$interval = $timeObj->diff(new DateTime());

			// 判断时间差是否超过配置的退款时间窗口
			if ($interval->days > self::refund_window_days()) {
				// 超过配置天数，返回 false
				$result = false;
			} else {
				// 没有超过配置天数，返回 true
				$result = true;
			}
			return $result;
		}

		public static function refund_window_days()
		{
			$config = Npcink_Pay_Refund_Admin::npcConfig('refund');
			$days = (int) Npcink_Pay_Refund_Admin::get_options($config, 'window_days', 7);
			return min(365, max(1, $days));
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
