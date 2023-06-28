<?php
//设置用数据接口
//权限配置
//可进后台
//可进退款菜单
//可进B2订单列表



//注册API地址
add_action('rest_api_init', function () {
    register_rest_route('pf/v1', '/get_option/', array( // 完整命名空间为：/wp-json/pf/v1/
        'methods' => 'POST',
        'callback' => 'get_option_by_RestAPI',
    ));
    register_rest_route('pf/v1', '/update_option/', array( // 完整命名空间为：/wp-json/pf/v1/
        'methods' => 'POST',
        'callback' => 'update_option_by_RestAPI',
        'permission_callback' => function () {
            return current_user_can('manage_options'); // 只有管理员才有权限修改
        },
    ));
});


//读取Option
function get_option_by_RestAPI($data)
{
    // 将输入数据转换成数组类型 
    $dataArray = json_decode($data->get_body(), true);
    $return = array();
    // 遍历数组，检查每个元素是否为对象
    foreach ($dataArray as $option_name => $value) {
        // 初始化当前选项的值数组
        $option_value = array();
        // 如果当前元素是一个非空数组，则遍历其中的每个字段
        if (is_array($value) && !empty($value)) {
            foreach ($value as $field_name => $field_value) {
                // 获取指定选项的值，如果不存在，则使用空字符串代替
                $option_value[$field_name] = get_option($field_name, '');
            }
            // 将当前选项及其值添加到返回数组中
            $return[$option_name] = $option_value;
        } else {
            // 如果当前元素非数组或数组为空，获取指定选项的值
            $return[$option_name] = get_option($option_name, '');
        }
    }
    return $return; // 返回所有选项的键值对
}


//保存Option
function update_option_by_RestAPI($data)
{
    //判断是否是管理员
    if (current_user_can('manage_options')) {

        $dataArray = json_decode($data->get_body());
        $result = new stdClass();

        //循环保存选项
        foreach ($dataArray as $option_name => $value) {

            //判断，是否是非空数组
            if (is_object($value)) {
                //是非空数组
                foreach ($value as $arr => $data) {
                    update_option($arr, $data);
                }
            } else {
                update_option($option_name, $value);
            }
            $result->$option_name = $value;
        }

        //返回成功信息
        $response = new stdClass();
        $response->success = true;
        $response->message = "已保存！";
        $response->data = $result;
        return $response;
    } else {
        //返回失败信息
        $response = new stdClass();
        $response->error = new stdClass();
        $response->error->save_error = "保存失败！";
        $response->error->status = 500;
        return $response;
    }
}



//提供用户信息
function get_user_meat() {
    global $wp_roles;

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
           'name' =>$user->display_name,
        );
    }
    return $user_data;
}


//载入所需资源，并传递值
function magick_load_vues()
{
    $ver = '1';
    wp_enqueue_style('vite', plugin_dir_url( dirname( __DIR__ ) ) . 'vite/dist/index.css', array(), $ver, false);
    wp_enqueue_script('vite', plugin_dir_url( dirname( __DIR__ ) ) . 'vite/dist/index.js', array(), $ver, false);

    $pf_api_translation_array = array(
        'route' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest'),
        'user' => get_user_meat(),
    );
    wp_localize_script('vite', 'dataLocal', $pf_api_translation_array); //传给vite项目
}
add_action('admin_enqueue_scripts', 'magick_load_vues');


//对js文件进行module接入
function magick_refund_add_type_attribute_to_script($tag, $handle)
{
    // 在这里判断需要添加 type 属性的 JS 文件，比如文件名包含 xxx.js
    if (strpos($tag, 'index.js') !== false) {
        // 在 script 标签中添加 type 属性
        $tag = str_replace('<script', '<script type="module"', $tag);
    }
    return $tag;
}
add_filter('script_loader_tag', 'magick_refund_add_type_attribute_to_script', 10, 2);