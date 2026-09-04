<?php

if (!defined('ABSPATH')) {
    exit;
}

//权限控制
if (!class_exists('Npcink_Pay_Refund_Admin_Authority')) {
    class Npcink_Pay_Refund_Admin_Authority
    {

        public static function run()
        {
			// Refund access is expressed as a dedicated capability. The plugin does
			// not intercept unrelated WordPress admin pages.
			add_action('admin_init', array(__CLASS__, 'maybe_sync_refund_capabilities'), 5);
        }

		public static function maybe_sync_refund_capabilities()
		{
			if ('1' === (string) get_option('npcink_pay_refund_capability_version', '')) {
				return;
			}

			$config = Npcink_Pay_Refund_Admin::npcConfig('user');
			foreach ((array) Npcink_Pay_Refund_Admin::get_options($config, 'user', array()) as $user_id) {
				$user = get_userdata(absint($user_id));
				if ($user && Npcink_Pay_Refund_Admin_Config::is_refund_user_assignable($user)) {
					$user->add_cap('npcink_refund_orders');
				}
			}
			update_option('npcink_pay_refund_capability_version', '1', false);
		}

        /**
         * Whether the current user may run refund operations.
         */
        public static function current_user_can_refund()
        {
            $user = wp_get_current_user();
            if (!$user || empty($user->ID)) {
                return false;
            }

            if (current_user_can('manage_options')) {
                return true;
            }

			return user_can($user, 'npcink_refund_orders');
        }

        /**
         * Gate AJAX requests that can inspect or change refund state.
         */
        public static function require_refund_ajax_permission()
        {
            if (!check_ajax_referer('npcink_pay_refund_action', 'nonce', false)) {
                wp_send_json_error(array('message' => __('请求校验失败，请刷新页面后重试。', 'npcink-pay-refund')), 403);
            }

            if (!self::current_user_can_refund()) {
                wp_send_json_error(array('message' => __('您没有退款操作权限。', 'npcink-pay-refund')), 403);
            }
        }
    } //end
}
