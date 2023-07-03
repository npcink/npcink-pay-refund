<?php
/*
Plugin Name: 魔法退款
Description: 提供微信和支付宝退款功能，使用官方提供的SDK，未使用第三方框架，联系方式：qq - 1355471563
Version: 1.0.5
Author: Muze
*/

//添加新权限
require_once plugin_dir_path(__FILE__) . 'admin/module/interface.php';
//支付宝
require_once plugin_dir_path(__FILE__) . 'admin/zfb.php';
//微信
require_once plugin_dir_path(__FILE__) . 'admin/wx.php';


//载入查询菜单
require_once plugin_dir_path(__FILE__) . 'admin/module/query.php';
//载入配置菜单
require_once plugin_dir_path(__FILE__) . 'admin/module/config.php';

//载入css和JS文件
function magick_load_vue()
{


    wp_enqueue_style('pay',  plugin_dir_url(__FILE__) . 'admin/css/pay.css', array(), '1.0.2', false);
    wp_enqueue_script('pay', plugin_dir_url(__FILE__) . 'admin/js/pay.js', array(), '1.0.6', false);
    //传递一些变量给JS
    wp_localize_script('pay', 'public', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        //data.json文件位置
        'json' =>   npc_refund_install()["url"],



    ));
}
add_action('admin_enqueue_scripts', 'magick_load_vue');

// 在开始时运行
register_activation_hook(__FILE__, 'npc_refund_install');

//输出数组，第一个值是文件目录，第二个值是网络目录
function npc_refund_install()
{
    // 文件名前缀
    $prefix = 'data_';
    // json 文件后缀名
    $suffix = '.json';
    // 目录路径
    $dir_path = plugin_dir_path(__FILE__) . 'inc/data/';
    //网络路径
    $dir_url = plugin_dir_url(__FILE__) . 'inc/data/';

    // 获取符合条件的 json 文件名
    $file_array = glob($dir_path . $prefix . '*' . $suffix);

    if (!empty($file_array)) {
        // 输出第一个符合条件的文件名
        $filename = basename($file_array[0]);
    } else {
        // 生成一个唯一的文件名
        $filename = $prefix . uniqid() . $suffix;

        // 生成文件路径
        $filepath = $dir_path . $filename;

        // 初始数据
        $data = array('data' => []);

        // 将数据转换为 JSON 格式，并写入文件
        $json_data = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents($filepath, $json_data);
    }

    // 准备目录
    $path =  $dir_path . $filename;
    $url = $dir_url . $filename;

    // 返回结果
    return array("path" => $path, "url" => $url);
}




//function npc_refund_key()
//{
//    // 文件名前缀
//    $prefix = 'key_';
//    // json 文件后缀名
//    $suffix = '.pem';
//    // 目录路径
//    $dir_path = plugin_dir_path(__FILE__) . 'inc/cert/';
//    //网络路径
//    $dir_url = plugin_dir_url(__FILE__) . 'inc/cert/';
//
//    // 获取符合条件的 json 文件名
//    $file_array = glob($dir_path . $prefix . '*' . $suffix);
//
//    if (!empty($file_array)) {
//        // 输出第一个符合条件的文件名
//        $filename = basename($file_array[0]);
//    } else {
//        // 生成一个唯一的文件名
//        $filename = $prefix . uniqid() . $suffix;
//
//        // 生成文件路径
//        $filepath = $dir_path . $filename;
//
//        touch($filepath);
//    }
//
//    // 准备目录
//    $path =  $dir_path . $filename;
//    $url = $dir_url . $filename;
//
//    // 返回结果
//    return array("path" => $path, "url" => $url);
//}
















//添加数据进json文件中
//输入时间等
function npc_add_json($time, $user, $amount, $order, $reason, $type)
{

    //读取JSON文件
    $filepath = npc_refund_install()["path"];



    // 读取数据文件并解码为数组
    $json_file = file_get_contents($filepath);
    $data_array = json_decode($json_file, true);

    // 获取最后一个对象的 ID 值
    $last_id = count($data_array['data']) > 0 ? end($data_array['data'])['id'] : 0;

    //有的退款金额（支付宝）可能为字符串，这里统一处理成数字
    $amount = $amount * 1;
    // 新增的对象
    $new_data = array(
        "id" => $last_id + 1,
        "amount" => $amount,
        "time" => $time,
        "order" => $order,
        "user" => $user,
        "type" => $type,
        "reason" => $reason,
    );

    // 将新增对象添加到 data 数组中
    $data_array['data'][] = $new_data;

    // 将修改后的数组编码为 JSON 数据，并写入到文件中

    $json_data = json_encode($data_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($filepath, $json_data);
}


//时间对比
//若输入的时间与当前时间对比超过7天，则输出false
function magick_refund_contrast($time)
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

//权限管理
add_action('admin_init', 'mqzj_restrict_access');

function mqzj_restrict_access()
{
    $user = wp_get_current_user();
    $users = get_option('npc_refund_user'); // 设置允许访问的用户ID


    // 创建一个空数组用于存储结果

    $a = array();

    // 如果 $a 为空或为字符串，则将其赋值为空数组
    if (empty($users) || is_string($users)) {
        $a = array();
    } else {
        //类型正常，排除掉ID为1的管理员
        foreach ($users as $value) {

            // 如果值不是1,则将其添加到结果数组中

            if ($value !== 1) {

                $a[] = $value;
            }
        }
    }

    //if (in_array($user->ID, $a)) {

    //    if ((isset($_GET['page']) && $_GET['page'] == 'refund_querys') || (isset($_GET['page']) && $_GET['page'] == 'b2_orders_list')) {
    //        return;
    //    } elseif (preg_match('/^\/wp-admin\/(admin-ajax\.php|admin-post\.php)/', $_SERVER['PHP_SELF'])) {
    //        // 如果是 admin-ajax.php 或 admin-post.php，则不拦截
    //        return;
    //    } else {
    //        //跳转
    //        //wp_safe_redirect(admin_url('index.php?page=refund_querys'));
    //        wp_die('您暂无授权访问此页面，请联系管理员授权！
    //        <ul>
    //        <li><a href="https://dongbd.com/wp-admin/admin.php?page=b2_orders_list&order_state=f">订单管理</a></li>
    //        <li><a href="https://dongbd.com/wp-admin/index.php?page=refund_querys">订单退款</a></li>
    //        </ul>
    //        ');
    //        exit;
    //    }
    //}

    //是限定 ID 
    if (in_array($user->ID, $a)) {
        //在访问限定菜单
        if ((isset($_GET['page']) && $_GET['page'] == 'refund_querys') || (isset($_GET['page']) && $_GET['page'] == 'b2_orders_list')) {
            return;
        } elseif (
            // 如果是 admin-ajax.php 或 admin-post.php，则不拦截
            preg_match('/^\/wp-admin\/(admin-ajax\.php|admin-post\.php)/', $_SERVER['PHP_SELF'])
        ) {

            return;
        } else {
            //跳转
            $adminPage = get_admin_url() . 'admin.php';
            $refundPage = get_admin_url() . 'index.php';
            $message = '
        您暂无授权访问此页面，请联系管理员授权！ 
        <ul> 
        <li>
        <a href="' . $adminPage . '?page=b2_orders_list">订单管理</a>
        </li> 
        <li>
        <a href="' . $refundPage . '?page=refund_querys">订单退款</a>
        </li> 
        </ul>
        ';
            wp_die($message);
            exit;
        }
    }
}



















//设置按钮
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $links[] = '<a href="' . get_admin_url(null, 'options-general.php?page=sandbox_id') . '">' . __('设置', 'n') . '</a>';
    return $links;
});
