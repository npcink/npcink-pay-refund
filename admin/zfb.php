<?php
//研究订单查询
//加载SDK


require_once plugin_dir_path( __DIR__ ) . 'inc/sdk/zfb/autoload.php';

use Alipay\EasySDK\Kernel\Factory;
use Alipay\EasySDK\Kernel\Util\ResponseChecker;
use Alipay\EasySDK\Kernel\Config;


//1. 设置参数（全局只需设置一次）
Factory::setOptions(getOptions());
function getOptions()
{
    $options = new Config();
    $options->protocol = 'https';
    $options->gatewayHost = 'openapi.alipay.com';
    $options->signType = 'RSA2';
    
    //$options->appId = '2021002134609167';
    $options->appId = get_option('npc_zfb_appid');
    
    // 为避免私钥随源码泄露，推荐从文件中读取私钥字符串而不是写入源码中
    $options->merchantPrivateKey =get_option('npc_zfb_private_key');
    

    
    //注：如果采用非证书模式，则无需赋值上面的三个证书路径，改为赋值如下的支付宝公钥字符串即可
     $options->alipayPublicKey = get_option('npc_zfb_public_key');

    //可设置异步通知接收服务地址（可选）
    $options->notifyUrl = "<-- 请填写您的支付类接口异步通知接收服务地址，例如：https://www.test.com/callback -->";

    return $options;
}









//处理查询请求
add_action( 'wp_ajax_my_plugin_request_data', 'my_plugin_request_data' );
function my_plugin_request_data() {
    $param = $_REQUEST['param']; // 获取传递的参数
    try {
        // 2. 发起API调用（以支付能力下的统一收单交易创建接口为例）
        $result = Factory::payment()->common()->query($param);
        $responseChecker = new ResponseChecker();
        // 3. 处理响应或异常
        if ($responseChecker->success($result)) {
            $data = $result;
            $table_html = "<table>";
            $table_html .= "<tr><td>订单号：</td><td>" . $data->outTradeNo . "</td></tr>";
            $table_html .= "<tr><td>时间：</td><td>" . $data->sendPayDate . "</td></tr>";
            $table_html .= "<tr><td>金额：</td><td>" . $data->totalAmount . "</td></tr>";
            $table_html .= "<tr><td>状态：</td><td>";
            switch ($data->tradeStatus) {
                case "WAIT_BUYER_PAY":
                    $table_html .= "等待付款";
                    break;
                case "TRADE_CLOSED":
                    $table_html .= "<b class='green'>已退款</b>";
                    break;
                case "TRADE_SUCCESS":
                    $table_html .= "交易支付成功";
                     

                    break;
                default:
                    $table_html .= "未知状态，请联系管理员";
            }
            $table_html .= "</td></tr>";
            $table_html .= "</table>";
            echo $table_html;
            //订单支付成功
            if($data->tradeStatus == "TRADE_SUCCESS" && magick_refund_contrast($data->sendPayDate) ){
                ?>
                <p>退款原因：<input type="text" id="npcink-zfb-reason" placeholder="请输入退款原因"></p>
                <?php
               echo " <button id='order-btn' class='button button-primary refund' data-order-id='" . $data->outTradeNo . "' data-order-amount='" . $data->totalAmount . "'data-order-time='" . $data->sendPayDate . "'>支付宝全额退款</button>";
            } else {
                
                if($data->tradeStatus == "TRADE_SUCCESS" && !magick_refund_contrast($data->sendPayDate)){
                 ?>
                <h3>该订单时间超过7天，无法使用本功能退款，请联系管理员操作</h3>
                <?php
                }
            }
            
            
            
            
        } else {
            //$error_msg = "调用失败，原因：" . $result->msg . "，" . $result->subMsg . PHP_EOL;
             $error_msg .= "操作失败，原因如下：<br>" . $result->subMsg . PHP_EOL;
            echo $error_msg;
        }
    } catch (Exception $e) {
        $error_msg = "调用失败，" . $e->getMessage() . PHP_EOL;;
        echo $error_msg;
    }
    wp_die();
}



//退款功能
add_action('wp_ajax_my_plugin_order_detail', 'my_plugin_order_detail');

function my_plugin_order_detail() {
    $order_id = $_POST['order_id']; // 获取传递的订单号
    $order_time = $_POST['order_time']; // 获取传递的订单时间
     $order_amount = $_POST['order_amount']; // 获取传递的总金额
     $order_reason = $_POST['order_reason']; // 获取传递的退款原因
    
     //获取登录用户名
          $current_user = wp_get_current_user();
          $user = $current_user->display_name;
    
    try {
    //2. 发起API调用（以支付能力下的统一收单交易创建接口为例）
    $result = Factory::payment()->common()->refund( $order_id,$order_amount);
    $responseChecker = new ResponseChecker();
    //3. 处理响应或异常
    if ($responseChecker->success($result)) {
        $table_html = "<table>";
            $table_html .= "<tr><td>订单号：</td><td>" . $order_id . "</td></tr>";
            $table_html .= "<tr><td>时间：</td><td>" . $order_time . "</td></tr>";
            $table_html .= "<tr><td>金额：</td><td>" . $order_amount . "</td></tr>";
             $table_html .= "<tr><td>状态：</td><td><b class='tomato'>已退款</b></td></tr>";
            $table_html .= "</td></tr>";
            $table_html .= "</table>";
            
            //添加数据进JSON文件
            npc_add_json($order_time,$user,$order_amount,$order_id,$order_reason,'支付宝');
        
        echo  $table_html;
    } else {
       $error_msg = "退款失败，原因：" . $result->msg . "，" . $result->subMsg . PHP_EOL;
            echo $error_msg;
    }
} catch (Exception $e) {
    echo "退款失败，". $e->getMessage(). PHP_EOL;;
}


    
    wp_die();
}


