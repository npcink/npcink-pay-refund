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
            //登录时，限定指定ID人员仅可访问指定页面
            add_action('admin_init', array(__CLASS__, 'restrict_access'));
        }

        /**
         * 权限判断，指定ID的人员仅能访问指定页面
         */

        public static function restrict_access()
        {
            //获取选项值
            $config = Npcink_Pay_Refund_Admin::npcConfig('user');

            //获取用户ID数组
            $users =  Npcink_Pay_Refund_Admin::get_options($config, 'user');

            //获取链接数组
            $site =  Npcink_Pay_Refund_Admin::get_options($config, 'link');
            if (!is_array($site)) {
                $site = array();
            }

            //当前登录信息
            $logUser = wp_get_current_user();

            if (current_user_can('manage_options')) {
                return;
            }

            // 创建一个空数组用于存储结果

            //处理用户数组，验证有效性
            $array_user = self::handle_id($users);



            //是限定 ID 
            if (in_array($logUser->ID,  $array_user)) {
                $arr_url = array();

                //将网址进行处理，并提取组成数组
                foreach ($site as $obj) {
                    $url = Npcink_Pay_Refund_Admin::get_options($obj, 'url');
                    $arr_url[] = self::get_url($url);
                }

                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check; no state is changed.
                if (isset($_GET['page'])) {
                    //获取当前链接中的页面查询字段
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check; no state is changed.
                    $site_url = sanitize_key(wp_unslash($_GET['page']));
                    // 访问允许的菜单
                    if (in_array($site_url, $arr_url)) {
                        return;
                    }
                }


                // 如果是 admin-ajax.php 或 admin-post.php，则不拦截（点击按钮提交的请求）
                $php_self = isset($_SERVER['PHP_SELF']) ? sanitize_text_field(wp_unslash($_SERVER['PHP_SELF'])) : '';
                if (preg_match('/^\/wp-admin\/(admin-ajax\.php|admin-post\.php)/', $php_self)) {
                    return;
                }

                //展示提示信息
                self::tips($site);
                exit;
            }
        }

        /**
         * 处理数组，验证有效性
         */
        public static function handle_id($users)
        {
            $a = array();

            if (empty($users) || is_string($users)) {
                return $a;
            }

            foreach ($users as $value) {
                $user_id = absint($value);
                if ($user_id > 0) {
                    $a[] = $user_id;
                }
            }

            return array_values(array_unique($a));
        }
        /**
         * 处理当前网址，只保留?page=后的内容
         */
        public static function get_url($url)
        {
            // 解析查询字符串
            $query_string = wp_parse_url($url, PHP_URL_QUERY);
            $query = array();
            if (is_string($query_string)) {
                parse_str($query_string, $query);
            }
            $page = "未找到 page 参数";
            // 获取 "page" 参数的值
            if (isset($query['page'])) {
                $page = $query['page'];
            }
            return $page;
        }

        /**
         * 提示信息
         */
        public static function tips($site)
        {
            $message = '您暂无授权访问此页面，请联系管理员授权！<ul>';

            foreach ($site as $obj) {

                /**
                 * 可能会填空值，这里处理下
                 */
                $url = Npcink_Pay_Refund_Admin::get_options($obj, 'url', "#");
                $title = Npcink_Pay_Refund_Admin::get_options($obj, 'title', "此链接无法点击，请联系管理员处理");

                $message .= '<li><a href="' . esc_url($url) . '">' . esc_html($title) . '</a></li>';
            }

            $message .= '</ul>';
            wp_die(wp_kses_post($message));
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

            $config = Npcink_Pay_Refund_Admin::npcConfig('user');
            $users = Npcink_Pay_Refund_Admin::get_options($config, 'user', array());
            $allowed_ids = array_map('intval', self::handle_id($users));
            if (!in_array((int) $user->ID, $allowed_ids, true)) {
                return false;
            }

            return user_can($user, 'publish_posts') && !user_can($user, 'manage_options');
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
