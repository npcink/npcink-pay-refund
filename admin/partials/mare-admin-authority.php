<?php
//权限控制
if (!class_exists('Mare_Admin_Authority')) {
    class Mare_Admin_Authority
    {

        public static function run()
        {
            //限定指定ID人员仅可访问指定页面
            add_action('admin_init', array(__CLASS__, 'restrict_access'));
        }

        /**
         * 权限判断，指定ID的人员仅能访问指定页面
         */

        public static function restrict_access()
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
    } //end
}
