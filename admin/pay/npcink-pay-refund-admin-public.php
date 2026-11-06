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
            require_once plugin_dir_path(dirname(__DIR__)) . 'includes/class-npcink-pay-refund-activator.php';
            if (get_option('npcink_pay_refund_schema_version', '') === Npcink_Pay_Refund_Activator::SCHEMA_VERSION) {
                return;
            }
            if (!Npcink_Pay_Refund_Activator::create_refund_order()) {
                add_action('admin_notices', static function () {
                    if (current_user_can('manage_options')) {
                        echo '<div class="notice notice-error"><p>' . esc_html__('退款记录表升级未完成，请检查数据库权限和表结构；修复后会自动重试。', 'npcink-pay-refund') . '</p></div>';
                    }
                });
                return;
            }
            update_option('npcink_pay_refund_schema_version', Npcink_Pay_Refund_Activator::SCHEMA_VERSION, false);
		}





		/**
		 * 添加数据进数据库文件中
		 */
		public static function add_data($time, $user, $amount, $order, $reason, $type, $user_id = 0)
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
				'n_user_id' => absint($user_id) ?: (function_exists('get_current_user_id') ? get_current_user_id() : 0),
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

        private static $claims = array();
        private static $mutexes = array();

        /** Hold a connection-scoped mutex until shutdown, including wp_send_json/exit. */
        public static function lock_operation($order, $type)
        {
            global $wpdb;
            $name = 'npcink:' . substr(hash('sha256', $wpdb->prefix . '|' . $type . '|' . $order), 0, 56);
            if (isset(self::$mutexes[$name])) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A reconnect loses named locks; fail closed instead of trusting request memory.
                return '1' === (string) $wpdb->get_var($wpdb->prepare('SELECT IF(IS_USED_LOCK(%s) = CONNECTION_ID(), 1, 0)', $name));
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Connection mutex must bypass caches.
            if ('1' !== (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $name))) {
                return false;
            }
            self::$mutexes[$name] = true;
            register_shutdown_function(static function () use ($wpdb, $name) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Release the connection mutex at shutdown.
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
            });
            return true;
        }

        public static function require_order_operation($order, $type)
        {
            if ('' === $order) {
                wp_send_json_error(array('message' => __('订单号不能为空。', 'npcink-pay-refund')), 400);
            }
            if (!self::lock_operation($order, $type)) {
                wp_send_json_error(array('message' => __('该订单正在处理，请稍后重试。', 'npcink-pay-refund')), 409);
            }
            // Historical rows without an owner stay visible only to administrators.
            if (!current_user_can('manage_options')) {
                $contexts = array(self::get_refund_uncertain($order, $type), self::get_refund_reconciliation($order, $type));
                if ('微信' === $type) {
                    $contexts[] = Npcink_Pay_Refund_Admin_Wx::get_pending_refund($order);
                }
                foreach ($contexts as $context) {
                    if ($context && (int) ($context['n_user_id'] ?? 0) !== get_current_user_id()) {
                        wp_send_json_error(array('message' => __('该订单的待处理退款属于其他操作者，请联系管理员。', 'npcink-pay-refund')), 403);
                    }
                }
            }
        }

        /** Compare the persisted bytes, never delete another request's replacement. */
        public static function compare_option($name, $expected, $replacement = null)
        {
            global $wpdb;
            $table = esc_sql($wpdb->options);
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery -- WP 6.0 lacks %i; escaped core table name and prepare() in both branches. CAS cannot use the option cache.
            if (null === $replacement) {
                $sql = $wpdb->prepare("DELETE FROM {$table} WHERE option_name = %s AND BINARY option_value = %s", $name, maybe_serialize($expected));
            } else {
                $sql = $wpdb->prepare("UPDATE {$table} SET option_value = %s, autoload = 'no' WHERE option_name = %s AND BINARY option_value = %s", maybe_serialize($replacement), $name, maybe_serialize($expected));
            }
            $result = $wpdb->query($sql);
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
            wp_cache_delete($name, 'options');
            wp_cache_delete('alloptions', 'options');
            wp_cache_delete('notoptions', 'options');
            return 1 === $result;
        }

        public static function claim_refund($order, $type)
        {
            if (!self::lock_operation($order, $type) || self::has_refund_record($order, $type)
                || self::get_refund_reconciliation($order, $type) || self::get_refund_uncertain($order, $type)) {
                return false;
            }
            $name = self::refund_lock_name($order, $type);
            $old = get_option($name, false);
            $locked_at = is_array($old) ? (int) ($old['time'] ?? 0) : (int) $old;
            if (false !== $old && time() - $locked_at < 10 * MINUTE_IN_SECONDS) {
                return false;
            }
            $claim = array('time' => time(), 'token' => bin2hex(random_bytes(16)));
            $acquired = false === $old ? add_option($name, $claim, '', 'no') : self::compare_option($name, $old, $claim);
            if ($acquired) {
                self::$claims[$name] = $claim;
            }
            return $acquired;
        }

        public static function release_refund_claim($order, $type)
        {
            $name = self::refund_lock_name($order, $type);
            if (isset(self::$claims[$name])) {
                self::compare_option($name, self::$claims[$name]);
                unset(self::$claims[$name]);
            }
        }

        /** Retire a crashed request's claim only after a fenced terminal transition. */
        public static function retire_refund_claim($order, $type)
        {
            if (!self::lock_operation($order, $type)) {
                return false;
            }
            $name = self::refund_lock_name($order, $type);
            $old = get_option($name, false);
            return false === $old || self::compare_option($name, $old);
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
				'n_user_id' => isset($data['n_user_id']) ? absint($data['n_user_id']) : 0,
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
			if (isset($data['n_user_id'])) {
				$data['n_user_id'] = absint($data['n_user_id']);
			}
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
				, isset($data['n_user_id']) ? $data['n_user_id'] : 0
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
            $counts = array('uncertain' => 0, 'wechat_pending' => 0, 'reconciliation' => 0);
            foreach (self::refund_operational_state_rows() as $row) {
                ++$counts[$row['kind']];
            }
            return $counts;
        }

        public static function refund_operational_state_rows($limit = 100)
        {
            return self::refund_operational_state_page($limit)['rows'];
        }

        /** One globally ordered keyset page; batch values to avoid option N+1 reads. */
        public static function refund_operational_state_page($limit = 100, $cursor = 0)
        {
            global $wpdb;
            $limit = min(100, max(1, (int) $limit));
            $cursor = max(0, (int) $cursor);
            $empty = array('rows' => array(), 'next_cursor' => 0);
            if (!Npcink_Pay_Refund_Admin_Authority::current_user_can_refund()) {
                return $empty;
            }
            $table = esc_sql($wpdb->options);
            $prefixes = array(
                'npcink_pay_refund_uncertain_' => array('uncertain', '支付宝待确认'),
                'npcink_pay_refund_pending_wx_' => array('wechat_pending', '微信待查询'),
                'npcink_pay_refund_reconcile_' => array('reconciliation', '本地记录待补记'),
            );
            $names = array_keys($prefixes);
            $owner_like = '%' . $wpdb->esc_like('s:9:"n_user_id";i:' . get_current_user_id() . ';') . '%';
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fresh paginated state; escaped core table, all predicates prepared; WP 6.0 lacks %i.
            $candidates = $wpdb->get_results($wpdb->prepare(
                "SELECT option_id, option_name, option_value FROM {$table} WHERE option_id > %d AND (option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s) AND (%d = 1 OR option_value LIKE %s) ORDER BY option_id ASC LIMIT 201",
                $cursor, $wpdb->esc_like($names[0]) . '%', $wpdb->esc_like($names[1]) . '%', $wpdb->esc_like($names[2]) . '%',
                current_user_can('manage_options') ? 1 : 0, $owner_like
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = array();
            $last = $cursor;
            foreach (array_slice((array) $candidates, 0, 200) as $option) {
                $last = (int) $option->option_id;
                $data = maybe_unserialize($option->option_value);
                if (!is_array($data) || empty($data['n_order']) || !empty($data['terminal_status'])
                    || (!current_user_can('manage_options') && (int) ($data['n_user_id'] ?? 0) !== get_current_user_id())) {
                    continue;
                }
                foreach ($prefixes as $prefix => $label) {
                    if (0 !== strpos($option->option_name, $prefix)) { continue; }
                    $rows[] = array(
                        'kind' => $label[0], 'state' => $label[1],
                        'channel' => sanitize_text_field($data['n_type'] ?? ('wechat_pending' === $label[0] ? '微信' : '支付宝')),
                        'order' => sanitize_text_field($data['n_order']),
                        'request_id' => sanitize_text_field($data['request_id'] ?? ''),
                        'amount' => (float) ($data['n_amount'] ?? $data['amount'] ?? 0),
                    );
                    break;
                }
                if (count($rows) >= $limit) { break; }
            }
            $has_more = $candidates && $last < (int) end($candidates)->option_id;
            return array('rows' => $rows, 'next_cursor' => $has_more ? $last : 0);
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
			$message_format = __('当前待处理分页提醒（每页最多 100 项）：支付宝结果待确认 %1$d 项，微信待查询或补记 %2$d 项，本地记录待补记 %3$d 项。状态项可能对应同一订单；为避免重复退款，请先查询核对。', 'npcink-pay-refund');
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
		public static function contrast_time($time, $source_timezone = null)
		{
			$timezone = null !== $source_timezone ? new DateTimeZone($source_timezone) : (function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC'));
			if (!is_string($time) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $time)) {
				return false;
			}
			$timeObj = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $time, $timezone);
			$errors = DateTimeImmutable::getLastErrors();
			if (!$timeObj || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
				return false;
			}

			$age = time() - $timeObj->getTimestamp();
			$day_seconds = defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400;
			return $age >= 0 && $age <= self::refund_window_days() * $day_seconds;
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
