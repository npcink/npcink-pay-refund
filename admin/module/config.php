<?php
//退款配置菜单
//添加顶级菜单
function sandbox_create_menu_page()
{
    add_submenu_page(
        'options-general.php',
        '退款配置',
        '退款配置', // 此菜单对应页面上显示的标题
        'administrator', // 哪种类型的用户可以看到此菜单
        'sandbox_id', // The unique ID - that is, the slug - for this menu item 此菜单项的唯一ID（即段塞）
        'sandbox_menu_page_displays', // 呈现此页面的菜单时要调用的函数的名称
        '90.1', //顺序
    );
} // end sandbox_create_menu_page
add_action('admin_menu', 'sandbox_create_menu_page');

//vite
function sandbox_menu_page_displays()
{
?>
    <div class="wrap npc_style">
        <!--标题-->
        <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
        <span>完成下方信息配置，才可使用对应退款功能</span>
        <ul style="list-style-type: auto;padding: 0 1em;">
            <li>统计数据通过JSON格式保存在 /inc/data/ 文件夹下，文件名随机生成</li>
            <li>因为技术问题，微信退款是在点击退款按钮后进行记录的，可能有重复，还请注意</li>
            <li>因为安全问题，仅支持7天内的订单进行退款操作，还请注意</li>
            <li>退款原因仅自己可见</li>
            <li>请勿关闭 REST API 功能</li>
        </ul>
        <p>退款操作界面在“仪表盘” -> “订单退款”中操作</p>
        <div id="app_refund"></div>
    <?php
}




//将选项中的微信KEY内容写入文件中
//function npcink_write_file(){
//    
//    //获取路径
//    $path = npc_refund_key()["path"];
//    // 读取 PEM 文件中的内容
//$pem_content = file_get_contents($path);
////拿到的秘钥内容写入文件中
//    //优化数据
//    $pem_data = get_option('npc_wx_cert_key');
//    $pem = trim($pem_data);
//    $pem = str_replace(array("-----BEGIN PRIVATE KEY-----", "-----END PRIVATE KEY-----"), '', $pem);
//    $pem = preg_replace('/\s+/', '', $pem);
//    $pem = chunk_split($pem, 64, "\r\n");
//    $pem = "-----BEGIN PRIVATE KEY-----\r\n" . $pem . "-----END PRIVATE KEY-----\r\n";
//
//   
//
// //撰写函数，对比两个字符串的区别
//  if($pem_content !== $pem) {
//      //有不同，写入文件中
//     
//       // 写入 PEM 数据到文件中
//   
//    file_put_contents($path , $pem);
//      
//  }
//}
