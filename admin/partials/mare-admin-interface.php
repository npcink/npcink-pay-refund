<?php
//接口文件
if (!class_exists('Mare_Admin_Interface')) {
    class Mare_Admin_Interface
    {
        //开始执行咯
        public static function run()
        {

            //添加接口
            add_action('rest_api_init', array(__CLASS__, 'add_interface'));
            add_action('wp_head', array(__CLASS__, 'shouTop'));
        }

        //注册API地址
        public static  function add_interface()
        {
            register_rest_route('pf/v1', '/get_option/', array( // 完整命名空间为：/wp-json/pf/v1/
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'get_option_by_RestAPI'),
            ));
            register_rest_route('pf/v1', '/update_option/', array( // 完整命名空间为：/wp-json/pf/v1/
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'update_option_by_RestAPI'),
                'permission_callback' => function () {
                    return current_user_can('manage_options'); // 只有管理员才有权限修改
                },
            ));
        }



        //读取Option
        public static function get_option_by_RestAPI($data)
        {
            // 将输入数据转换成数组类型 
            $dataArray = json_decode($data->get_body(), true);
            $return = array();
            // 遍历数组，检查每个元素是否为对象
            foreach ($dataArray as $option_name => $value) {

                // 如果当前元素非数组或数组为空，获取指定选项的值
                $return[$option_name] = get_option($option_name, "");
                //$return["data"] = $dataArray;
                //echo $option_name;
            }
            return $return; // 返回所有选项的键值对
        }


        //保存Option
        public static function update_option_by_RestAPI($data)
        {
            // 判断是否是管理员
            if (!current_user_can('manage_options')) {
                // 返回失败信息
                return new WP_Error('save_error', '保存失败！非管理员无法保存', array('status' => 500));
            }

            $dataArray = json_decode($data->get_body());
            $result = new stdClass();

            //循环保存选项
            foreach ($dataArray as $option_name => $value) {


                update_option($option_name, $value);
                //echo $option_name;
                //
                //echo $value;
                //echo "666";


                $result->$option_name = $value;
            }

            //返回成功信息
            //返回成功信息
            return new WP_REST_Response(array(
                'success' => true,
                'message' => "已保存！",
                'data' => $result,
            ), 200);
        }

        public static function shouTop()
        {
            $value = get_option("npc_refund_config", '没有拿到值');
            $content = "666<h1>" . $value->zfb->appid . "</h1>";
            echo $content;
        }
    } //end
}
