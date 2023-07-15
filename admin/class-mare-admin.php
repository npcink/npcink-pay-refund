<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.npc.ink
 * @since      1.0.0
 *
 * @package    Mare
 * @subpackage Mare/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Mare
 * @subpackage Mare/admin
 * @author     Muze <1355471563@qq.com>
 */
class Mare_Admin
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
		require_once plugin_dir_path(__FILE__) . 'partials/mare-admin-query.php';
		//选项配置用文件
		require_once plugin_dir_path(__FILE__) . 'partials/mare-admin-config.php';
		//接口文件
		require_once plugin_dir_path(__FILE__) . 'partials/mare-admin-interface.php';
		//权限控制文件
		require_once plugin_dir_path(__FILE__) . 'partials/mare-admin-authority.php';
		//微信支付文件
		//require_once plugin_dir_path(__FILE__) . 'pay/mare-admin-wxs.php';
		//支付宝支付文件
		require_once plugin_dir_path(__FILE__) . 'pay/mare-admin-zfb.php';
		//公用函数文件
		require_once plugin_dir_path(__FILE__) . 'pay/mare-admin-public.php';
	}

	/**
	 * 加载文件中
	 */
	public function run()
	{
		//查询菜单
		Mare_Admin_Query::run($this->plugin_name, $this->version);
		//配置菜单
		Mare_Admin_Config::run($this->plugin_name, $this->version);
		//接口
		Mare_Admin_Interface::run();
		//权限控制
		Mare_Admin_Authority::run();

		//微信支付接口
		//Mare_Admin_Wx::run();

		//支付宝支付接口
		Mare_Admin_Zfb::run();

		//公用函数文件
		Mare_Admin_Public::run();
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	//public function enqueue_styles()
	//{
	//
	//	/**
	//	 * This function is provided for demonstration purposes only.
	//	 *
	//	 * An instance of this class should be passed to the run() function
	//	 * defined in Mare_Loader as all of the hooks are defined
	//	 * in that particular class.
	//	 *
	//	 * The Mare_Loader will then create the relationship
	//	 * between the defined hooks and the functions defined in this
	//	 * class.
	//	 */
	//
	//	wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/mare-admin.css', array(), $this->version, 'all');
	//}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	//public function enqueue_scripts()
	//{
	//
	//	/**
	//	 * This function is provided for demonstration purposes only.
	//	 *
	//	 * An instance of this class should be passed to the run() function
	//	 * defined in Mare_Loader as all of the hooks are defined
	//	 * in that particular class.
	//	 *
	//	 * The Mare_Loader will then create the relationship
	//	 * between the defined hooks and the functions defined in this
	//	 * class.
	//	 */
	//
	//	wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/mare-admin.js', array('jquery'), $this->version, false);
	//}
}
