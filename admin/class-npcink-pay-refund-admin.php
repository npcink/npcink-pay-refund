<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.npc.ink
 * @since      1.0.0
 *
 * @package    Npcink_Pay_Refund
 * @subpackage Npcink_Pay_Refund/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Npcink_Pay_Refund
 * @subpackage Npcink_Pay_Refund/admin
 * @author     Muze <1355471563@qq.com>
 */
class Npcink_Pay_Refund_Admin
{

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;


	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		$this->load(); //加载所需文件
		$this->run();
	}

	/**
	 * 载入文件用
	 */
	public function load()
	{
		//查询菜单用文件
		require_once plugin_dir_path(__FILE__) . 'partials/npcink-pay-refund-admin-query.php';
		//选项配置用文件
		require_once plugin_dir_path(__FILE__) . 'partials/npcink-pay-refund-admin-config.php';
		//权限控制文件
		require_once plugin_dir_path(__FILE__) . 'partials/npcink-pay-refund-admin-authority.php';
		//微信支付文件
		//require_once plugin_dir_path(__FILE__) . 'pay/npcink-pay-refund-admin-wxs.php';
		//支付宝支付文件
		require_once plugin_dir_path(__FILE__) . 'pay/npcink-pay-refund-admin-zfb.php';
		//公用函数文件
		require_once plugin_dir_path(__FILE__) . 'pay/npcink-pay-refund-admin-public.php';
	}

	/**
	 * 加载文件中
	 */
	public function run()
	{
		//查询菜单
		Npcink_Pay_Refund_Admin_Query::run($this->plugin_name, $this->version);
		//配置菜单
		Npcink_Pay_Refund_Admin_Config::run($this->plugin_name, $this->version);
		//权限控制
		Npcink_Pay_Refund_Admin_Authority::run();

		//微信支付接口
		//Npcink_Pay_Refund_Admin_Wx::run();

		//支付宝支付接口
		Npcink_Pay_Refund_Admin_Zfb::run();

		//公用函数文件
		Npcink_Pay_Refund_Admin_Public::run();
	}

	/**
	 * 从对象中提供全局设置
	 */
	public static function npcConfig($option)
	{
		$config = self::object_to_array(get_option('npcink_pay_refund_config', array()));
		$secrets = self::object_to_array(get_option('npcink_pay_refund_secrets', array()));
		$value = self::object_to_array(self::get_options($config, $option, array()));
		$secret_value = self::object_to_array(self::get_options($secrets, $option, array()));

		if (is_array($value) && is_array($secret_value)) {
			return array_merge($value, $secret_value);
		}

		return $value;
	}

	/**
	 * 从对象中获取属性值
	 *
	 * @param object $config 对象
	 * @param string $property 从对象中获取的属性名
	 * @param string $defaultValue 默认值（可选）
	 * @return mixed 属性值或默认值
	 */
	public static function get_options($config, $property, $defaultValue = '')
	{
		/**
		 * 是否是对象
		 * 对象中是否有此键名
		 * 在对象中的此值是否为空
		 */
		if (is_object($config) && property_exists($config, $property) && !empty($config->$property)) {
			return $config->$property;
		} elseif (is_array($config) && isset($config[$property]) && !empty($config[$property])) {
			return $config[$property];
		} else {
			return $defaultValue;
		}
	}

	public static function sanitize_textarea_value($value)
	{
		if (function_exists('sanitize_textarea_field')) {
			return sanitize_textarea_field($value);
		}

		$value = wp_check_invalid_utf8((string) $value);
		$value = wp_strip_all_tags($value, false);
		$value = str_replace(array("\r\n", "\r"), "\n", $value);
		$value = preg_replace('/[ \t]+/', ' ', $value);
		return trim($value);
	}

	public static function object_to_array($value)
	{
		if (is_object($value)) {
			$value = get_object_vars($value);
		}

		if (is_array($value)) {
			foreach ($value as $key => $item) {
				$value[$key] = self::object_to_array($item);
			}
		}

		return $value;
	}
}
