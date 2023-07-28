<?php
//删除插件时执行
//删除时执行的类
if (!class_exists('Mare_Admin_Uninstall')) {
    class Mare_Admin_Uninstall
    {
        //执行
        public static function run()
        {
            /**
             * 引入核心类
             */
            require_once plugin_dir_path(dirname(__DIR__)) . 'admin/class-mare-admin.php';
            //获取选项值
            //选项值
            $config = Mare_Admin::npcConfig('config');
            //数据库状态
            $mySql =  Mare_Admin::get_options($config, 'mysql');
            if ($mySql === 1) {
                self::delete_sql();
            }

            //选项状态
            $myConfig =  Mare_Admin::get_options($config, 'config');
            if ($myConfig === 1) {
                self::delete_option();
            }
            //登录时，限定指定ID人员仅可访问指定页面
            //add_action('admin_init', array(__CLASS__, 'restrict_access'));
        }
        /**
         * 删除数据库
         */
        public static function delete_sql()
        {
            // 删除数据库表
            // 获取 $wpdb 全局对象
            global $wpdb;

            // 定义要删除的数据表名
            $table_name = $wpdb->prefix . 'npc_refund_order';

            // 判断数据表是否存在
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                //数据表不存在！
                return "";
            } else {
                // 执行删除数据表操作
                $wpdb->query("DROP TABLE IF EXISTS $table_name");

                //return "数据表删除成功！";
            }
        }
        /**
         * 删除选项
         */
        public static function delete_option()
        {
            // 删除插件设置
            delete_option('npc_refund_config');
        }
    }
}
