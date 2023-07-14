<?php

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



			//获取当前页hook
			//add_action('admin_enqueue_scripts', array(__CLASS__, 'hook'));
		}





		/**
		 * 添加数据进数据库文件中
		 */
		public static function add_data($time, $user, $amount, $order, $reason, $type)
		{
			global $wpdb;
			$table_name = $wpdb->prefix . 'npc_refund_order';

			$data = array(
				'n_amount' => $amount,
				'n_time' => $time,
				'n_order' => $order,
				'n_user' => $user,
				'n_type' => $type,
				'n_reason' => $reason
			);

			$wpdb->insert($table_name, $data);
		}


		//时间对比
		//若输入的时间与当前时间对比超过7天，则输出false
		public static function contrast_time($time)
		{
			// 将 $time 转换为 DateTime 对象
			$timeObj = DateTime::createFromFormat('Y-m-d H:i:s', $time);

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
