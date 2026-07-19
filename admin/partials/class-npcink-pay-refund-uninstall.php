<?php

if (!defined('ABSPATH')) {
    exit;
}

//删除插件时执行
//删除时执行的类
if (!class_exists('Npcink_Pay_Refund_Admin_Uninstall')) {
    class Npcink_Pay_Refund_Admin_Uninstall
    {
        //执行
        public static function run()
        {
            /**
             * 引入核心类
             */
            require_once plugin_dir_path(dirname(__DIR__)) . 'admin/class-npcink-pay-refund-admin.php';
            //获取选项值
            //选项值
            $config = Npcink_Pay_Refund_Admin::npcConfig('config');
            //数据库状态
            $mySql =  Npcink_Pay_Refund_Admin::get_options($config, 'mysql');
            if ($mySql === 1) {
                self::delete_sql();
            }

            //选项状态
            $myConfig =  Npcink_Pay_Refund_Admin::get_options($config, 'config');
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
            $table_name = $wpdb->prefix . 'npcink_pay_refund_order';

            // 判断数据表是否存在
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup for this plugin's custom table.
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) != $table_name) {
                //数据表不存在！
                return "";
            } else {
                // 执行删除数据表操作
                $wpdb->query("DROP TABLE IF EXISTS " . esc_sql($table_name));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

                //return "数据表删除成功！";
            }
        }
        /**
         * 删除选项
         */
        public static function delete_option()
        {
            // 删除插件设置
            delete_option('npcink_pay_refund_config');
            delete_option('npcink_pay_refund_secrets');
            delete_option('npcink_pay_refund_schema_version');
            self::delete_options_by_prefix('npcink_pay_refund_pending_wx_');
            self::delete_options_by_prefix('npcink_pay_refund_lock_');
            self::delete_options_by_prefix('npcink_pay_refund_reconcile_');
			self::delete_options_by_prefix('npcink_pay_refund_uncertain_');
        }

        public static function delete_options_by_prefix($prefix)
        {
            global $wpdb;

            $options_table = esc_sql($wpdb->options);
            $like = $wpdb->esc_like($prefix) . '%';
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup for this plugin's generated options using the known options table.
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$options_table} WHERE option_name LIKE %s",
                    $like
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }
}
