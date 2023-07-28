<?php
//权限控制
if (!class_exists('Mare_Admin_Authority')) {
    class Mare_Admin_Authority
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
            $config = Mare_Admin::npcConfig('user');

            //获取用户ID数组
            $users =  Mare_Admin::get_options($config, 'user');

            //获取链接数组
            $site =  Mare_Admin::get_options($config, 'link');

            //当前登录信息
            $logUser = wp_get_current_user();

          

            // 创建一个空数组用于存储结果

            //处理用户数组，验证有效性，排除默认管理员
            $array_user = self::handle_id($users);



            //是限定 ID 
            if (in_array($logUser->ID,  $array_user)) {

                //将网址进行处理，并提取组成数组
                foreach ($site as $obj) {
                    $url = $obj->url;
                    $arr_url[] = self::get_url($url);
                }

                if (isset($_GET['page'])) {
                    //获取当前链接中的页面查询字段
                    $site_url = $_GET['page'];
                    // 访问允许的菜单
                    if (in_array($site_url, $arr_url)) {
                        return;
                    }
                }


                // 如果是 admin-ajax.php 或 admin-post.php，则不拦截（点击按钮提交的请求）
                if (preg_match('/^\/wp-admin\/(admin-ajax\.php|admin-post\.php)/', $_SERVER['PHP_SELF'])) {
                    return;
                }

                //展示提示信息
                self::tips($site);
                exit;
            }
        }

        /**
         * 处理数组，验证有效性，排除默认管理员
         */
        public static function handle_id($users)
        {
            $a = array();

            // 如果 $a 为空或为字符串，则将其赋值为空数组
            if (empty($users) || is_string($users)) {
                $a = array();
            } else {
                //类型正常，排除掉ID为1的人员
                foreach ($users as $value) {

                    // 如果值不是1,则将其添加到结果数组中

                    if ($value !== 1) {

                        $a[] = $value;
                    }
                }
            }
            return $a;
        }
        /**
         * 处理当前网址，只保留?page=后的内容
         */
        public static function get_url($url)
        {
            // 解析查询字符串
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
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
            // 跳转
            $message = '您暂无授权访问此页面，请联系管理员授权！<ul>';

            foreach ($site as $obj) {
                $message .= '<li><a href="' . $obj->url . '">' . $obj->title . '</a></li>';
            }

            $message .= '</ul>';
            wp_die($message);
        }
    } //end
}
