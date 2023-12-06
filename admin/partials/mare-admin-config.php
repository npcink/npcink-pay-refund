<?php
//配置菜单
if (!class_exists('Mare_Admin_Config')) {
    class Mare_Admin_Config
    {

        public static $plugin_name;
        public static $plugin_version;

        public static function run($name, $version)
        {

            self::$plugin_name = $name;
            self::$plugin_version = $version;
            //添加配置菜单
            add_action('admin_menu', array(__CLASS__, 'config_menu'));
            //加载 CSS 和 JS 资源
            add_action('admin_enqueue_scripts', array(__CLASS__, 'load_admin_script'));


            //添加接口以供下载表单
            add_action('wp_ajax_download_data', array(__CLASS__, 'download_data'));
        }
        public static function config_menu()
        {
            add_submenu_page(
                'options-general.php',
                '退款配置',
                '退款配置',
                'administrator',
                'refun_config',
                array(__CLASS__, 'menu_displays'),
                '90.1', //顺序
            );
        }

        //菜单回调函数
        public static function menu_displays()
        {
?>
            <div class="wrap npc_style">
                <!--标题-->
                <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
                <span>完成下方信息配置，才可使用对应退款功能</span>
                <ul style="list-style-type: auto;padding: 0 1em;">
                    <li>统计数据保存在数据库表 - npc_refund_order中</li>
                    <li>因为技术问题，微信退款是在点击退款按钮后进行记录的，可能有重复，还请注意</li>
                    <li>因为安全问题，仅支持7天内的订单进行退款操作，还请注意</li>
                    <li>退款原因仅自己可见</li>
                    <li>请勿关闭 REST API 功能</li>
                    <li>不会限制ID为1的人员</li>
                </ul>
                <p>退款操作界面在“仪表盘” -> “订单退款”中操作</p>
                <p>
                    <button id="button_download" class="button button-primary">下载全部退款记录表格</button>
                </p>

                <div id="app_refund"></div>
                <!--
                    最后加载
            -->
                <script>
                    window.addEventListener('DOMContentLoaded', function() {
                        // 创建一个 script 元素
                        var script = document.createElement('script');

                        // 设置 script 的 src 属性为外部脚本的 URL
                        script.src = 'https://unpkg.com/file-saver@2.0.5/dist/FileSaver.min.js';

                        // 将 script 元素添加到页面的 head 或 body 中
                        document.head.appendChild(script);
                    });
                </script>


    <?php
        }

        /**
         * 加载资源
         */
        public static function load_admin_script($hook)
        {
            $ver = self::$plugin_version;
            $name = self::$plugin_name;
            //是否是指定页面
            if ('settings_page_refun_config' != $hook) {
                return;
            }

            //对js文件进行module接入
            add_filter('script_loader_tag', array(__CLASS__, 'refund_type_script'), 10, 2);

            //准备地址
            $index_css = plugin_dir_url(dirname(__DIR__)) . 'vite/dist/index.css';
            $index_js = plugin_dir_url(dirname(__DIR__)) . 'vite/dist/index.js';
            $download_js = plugin_dir_url(dirname(__DIR__)) . 'admin/js/mare-download.js';

            wp_enqueue_style($name, $index_css, array(), $ver, false);
            wp_enqueue_script($name, $index_js, array(), $ver, true);
            wp_enqueue_script($name . '-download',  $download_js, array(), $ver, true);


            $pf_api_translation_array = array(
                'route' => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
                'user' => self::get_user_meat(),
            );
            wp_localize_script($name, 'dataLocal', $pf_api_translation_array); //传给vite项目

            //准备传的值
            $download_api = array(
                'ajaxurl' => admin_url('admin-ajax.php'),
            );
            wp_localize_script($name, 'api', $download_api); //传给download项目

        }

        //对js文件进行module接入
        public static function refund_type_script($tag, $handle)
        {
            // 在这里判断需要添加 type 属性的 JS 文件，比如文件名包含 xxx.js
            if (strpos($tag, 'index.js') !== false) {
                // 在 script 标签中添加 type 属性
                $tag = str_replace('<script', '<script type="module"', $tag);
            }
            return $tag;
        }

        //提供用户信息
        public static function get_user_meat()
        {

            $editable_roles = get_editable_roles();
            $roles = array_keys($editable_roles);
            $subscriber_key = array_search('subscriber', $roles);
            if (false !== $subscriber_key) {
                $roles = array_slice($roles, 0, $subscriber_key);
            }
            $users = get_users(array('role__in' => $roles));
            $user_data = array();

            foreach ($users as $user) {
                $user_data[] = array(
                    'id'   => $user->ID,
                    'name' => $user->display_name,
                );
            }
            return $user_data;
        }


        /**
         * 从数据库获取所有文件以供下载
         */
        public static function download_data()
        {
            global $wpdb;
            $table_name = $wpdb->prefix . 'npc_refund_order';
            $data = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
            wp_send_json($data);
            wp_die();
        }
    } //end
}
