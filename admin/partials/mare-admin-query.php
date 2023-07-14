<?php
//查询菜单
if (!class_exists('Mare_Admin_Query')) {


    class Mare_Admin_Query
    {

        public static $plugin_name;
        public static $plugin_version;

        public static function run($name, $version)
        {
            //传值
            self::$plugin_name = $name;
            self::$plugin_version = $version;
            //添加菜单
            add_action('admin_menu', array(__CLASS__, 'query_menu'));
            //加载 CSS 和 JS 资源
            add_action('admin_enqueue_scripts', array(__CLASS__, 'load_admin_script'));
        }



        //创建菜单
        public static function query_menu()
        {
            // 添加一个菜单到 WordPress 后台的“设置”菜单下
            add_submenu_page(
                'index.php',
                '订单退款',
                '订单退款',
                'administrator',
                'refund_querys',
                array(__CLASS__, 'menu_displays'),
                '200.1'
            );
        }



        /**
         * 菜单回调函数
         */
        public static function menu_displays()
        {

            $zfb_icon = '<svg t="1686029655000" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="3536" width="128" height="128"><path d="M1024.0512 701.0304V196.864A196.9664 196.9664 0 0 0 827.136 0H196.864A196.9664 196.9664 0 0 0 0 196.864v630.272A196.9152 196.9152 0 0 0 196.864 1024h630.272a197.12 197.12 0 0 0 193.8432-162.0992c-52.224-22.6304-278.528-120.32-396.4416-176.64-89.7024 108.6976-183.7056 173.9264-325.3248 173.9264s-236.1856-87.2448-224.8192-194.048c7.4752-70.0416 55.552-184.576 264.2944-164.9664 110.08 10.3424 160.4096 30.8736 250.1632 60.5184 23.1936-42.5984 42.496-89.4464 57.1392-139.264H248.064v-39.424h196.9152V311.1424H204.8V267.776h240.128V165.632s2.1504-15.9744 19.8144-15.9744h98.4576V267.776h256v43.4176h-256V381.952h208.8448a805.9904 805.9904 0 0 1-84.8384 212.6848c60.672 22.016 336.7936 106.3936 336.7936 106.3936zM283.5456 791.6032c-149.6576 0-173.312-94.464-165.376-133.9392 7.8336-39.3216 51.2-90.624 134.4-90.624 95.5904 0 181.248 24.4736 284.0576 74.5472-72.192 94.0032-160.9216 150.016-253.0816 150.016z" fill="#009FE8" p-id="3537"></path></svg>';
            $wx_icon = '<svg t="1686029539518" class="icon" viewBox="0 0 1228 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2371" width="128" height="128"><path d="M530.8928 703.1296a41.472 41.472 0 0 1-35.7376-19.8144l-2.7136-5.5808L278.272 394.752a18.7392 18.7392 0 0 1-2.048-8.1408 19.968 19.968 0 0 1 20.48-19.3536c4.608 0 8.8576 1.4336 12.288 3.84l234.3936 139.9296a64.4096 64.4096 0 0 0 54.528 5.9392L1116.2624 204.8C1004.9536 80.896 821.76 0 614.4 0 275.0464 0 0 216.576 0 483.6352c0 145.7152 82.7392 276.8896 212.2752 365.5168a38.1952 38.1952 0 0 1 17.2032 31.488 44.4928 44.4928 0 0 1-2.1504 12.3904l-27.6992 97.4848c-1.3312 4.608-3.328 9.3696-3.328 14.1312 0 10.752 9.216 19.3536 20.48 19.3536 4.4032 0 8.0384-1.536 11.776-3.584l134.5536-73.3184c10.1376-5.5296 20.7872-8.96 32.6144-8.96 6.2976 0 12.288 0.9216 18.0736 2.5088 62.72 17.0496 130.4576 26.5728 200.5504 26.5728C953.7024 967.168 1228.8 750.592 1228.8 483.6352c0-80.9472-25.4464-157.1328-70.0416-224.1024l-604.9792 436.992-4.4544 2.4064a42.1376 42.1376 0 0 1-18.432 4.1984z" fill="#15BA11" p-id="2372"></path></svg>';

?>
            <div class="wrap npc_style">

                <!--标题-->
                <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
                <ul class="ul_meat">
                    <li>每天早上8点账户清零，可尝试下午或有用户支付后，再进行退款操作</li>
                    <li>支付时间超过7天的订单无法使用本功能进行退款</li>
                    <li>若发生异常请联系管理员</li>
                </ul>

                <h2><?php echo $wx_icon; ?>微信订单查询</h2>
                <input type="text" id="npcink-wx-input" placeholder="请输入微信订单号">
                <button id="npcink-wx-button" class="button button-primary">查询</button>
                <div class="table_style">
                    <div id="npcink-wx-data"></div>
                </div>

                <h2><?php echo $zfb_icon; ?>支付宝订单查询</h2>
                <input type="text" id="my-plugin-input" placeholder="请输入支付宝订单号">
                <button id="my-plugin-button" class="button button-primary">查询</button>
                <div class="table_style">
                    <div id="my-plugin-data"></div>

                </div>
                <!--展示数据-->
                <h2>操作记录</h2>
                <table id="dataTable" class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>金额</th>
                            <th>时间</th>
                            <th>订单号</th>
                            <th>操作员</th>
                            <th>类型</th>
                            <th>原因</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- 这里将在 JavaScript 文件中插入数据 -->
                    </tbody>
                </table>



            </div>



            <!--end wrap
        <script src="https://cdn.jsdelivr.net/g/filesaver.js"></script>
        -->
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
            if ('dashboard_page_refund_querys' != $hook) {
                return;
            }

            wp_enqueue_style($name, plugin_dir_url(dirname(__DIR__)) . 'admin/css/mare-admin.css', array(), $ver, false);
            wp_enqueue_script($name, plugin_dir_url(dirname(__DIR__)) . 'admin/js/mare-admin.js', array(), $ver, false);
            wp_localize_script($name, 'public', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                //20条退款记录
                'data' =>   self::get_data(),
            ));
        }

        /**
         * 从数据库获取倒数20条数据文件
         */
        public static function get_data()
        {
            global $wpdb;
            $table_name = $wpdb->prefix . 'npc_refund_order';

            $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 20");

            $dataArray = array();

            if (!empty($results)) {
                foreach ($results as $result) {
                    $data = array(
                        'id' => $result->id,
                        'amount' => $result->n_amount,
                        'time' => $result->n_time,
                        'order' => $result->n_order,
                        'user' => $result->n_user,
                        'type' => $result->n_type,
                        'reason' => $result->n_reason
                    );
                    $dataArray[] = $data;
                }
            } else {
                // 数据库为空时，给出默认值
                $data = array(
                    'id' => '暂无',
                    'amount' => '暂无',
                    'time' => '暂无',
                    'order' => '暂无',
                    'user' => '暂无',
                    'type' => '暂无',
                    'reason' => '暂无'
                );
                $dataArray[] = $data;
            }

            return json_encode($dataArray); // 将数组以 JSON 格式输出
        }
    } //end



}
