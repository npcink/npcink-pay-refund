<?php
//微信支付相关


use GuzzleHttp\Exception\RequestException;
use WechatPay\GuzzleMiddleware\WechatPayMiddleware;
use WechatPay\GuzzleMiddleware\Util\PemUtil;




if (!class_exists('Mare_Admin_Wx')) {
    class Mare_Admin_Wx
    {

        /**
         * 认证信息
         */
        public static $client;

        public static function run()
        {

            //加载SDK
            require_once plugin_dir_path(__DIR__) . '/sdk/guzz/autoload.php';

            /**
             * 引入公共方法
             */
            require_once plugin_dir_path(__FILE__) . 'mare-admin-public.php';

            //准备认证信息
            self::config();

            //订单查询
            add_action('wp_ajax_wx_order_query', array(__CLASS__, 'wx_order_query'));

            //订单退款
            add_action('wp_ajax_wx_order_refund', array(__CLASS__, 'wx_order_refund'));
        }


        /**
         * 商户相关配置
         */

        public static function config()
        {
            $merchantId = get_option('npc_wx_mch_id'); // 商户号

            $merchantSerialNumber = get_option('npc_wx_cert_api'); // 商户API证书序列号


            $merchantPrivateKey = self::mare_handle_meat(get_option('npc_wx_cert_key')); // 商户私钥
            // 微信支付平台配置
            //$wechatpayCertificate = PemUtil::loadCertificate(plugin_dir_path(__FILE__) . 'cert/apiclient_cert.pem'); // 微信支付平台证书

            // 构造一个WechatPayMiddleware
            $wechatpayMiddleware = WechatPayMiddleware::builder()
                ->withMerchant($merchantId, $merchantSerialNumber, $merchantPrivateKey) // 传入商户相关配置
                ->withWechatPay([
                    //$wechatpayCertificate
                ]) // 可传入多个微信支付平台证书，参数类型为array
                ->build();

            // 将WechatPayMiddleware添加到Guzzle的HandlerStack中
            $stack = GuzzleHttp\HandlerStack::create();
            $stack->push($wechatpayMiddleware, 'wechatpay');

            // 创建Guzzle HTTP Client时，将HandlerStack传入

            self::$client = new GuzzleHttp\Client(['handler' => $stack]);
        }


        /**
         * 订单查询
         */
        public static function wx_order_query()
        {

            $order_id =  $_REQUEST['order_id']; // 获取传递的订单
            $mchid = get_option('npc_wx_mch_id'); // 获取传递的商家ID

            // 发送请求

            try {
                $resp = self::$client->request(
                    'GET',
                    'https://api.mch.weixin.qq.com/v3/pay/transactions/out-trade-no/' . $order_id . '?mchid=' . $mchid . '', //请求URL
                    [
                        'headers' => ['Accept' => 'application/json']
                    ]
                );
                //$statusCode = $resp->getStatusCode();
                //if ($statusCode == 200) { //处理成功
                //    echo "success,return body = " . $resp->getBody()->getContents()."\n";
                //} else if ($statusCode == 204) { //处理成功，无返回Body
                //    echo "success";
                //}
            } catch (RequestException $e) {
                // 进行错误处理
                //echo "简单测 " .$e->getMessage()."\n";
                if ($e->hasResponse()) {
                    $data = json_decode($e->getResponse()->getBody()); // 转换成 PHP 对象

                    $time = self::handle_time($data->success_time);
                    $amount = $data->amount->payer_total / 100;
                    $order = $data->out_trade_no;
                    // echo "failed,resp code = " . $e->getResponse()->getStatusCode() . " return body = " . $e->getResponse()->getBody() . "\n";
                    //根据当前订单状态进行对应操作

                    //准备订单成功数据
                    $table_html = "<table>";
                    $table_html .= "<tr><td>订单号：</td><td>" . $order . "</td></tr>";
                    $table_html .= "<tr><td>时间：</td><td>" .  $time  . "</td></tr>";
                    $table_html .= "<tr><td>金额：</td><td>" . $amount . "元</td></tr>";
                    $table_html .= "<tr><td>状态：</td><td><span class='green'>支付成功</span></td></tr>";
                    $table_html .= "</table>";


                    switch ($data->trade_state) {
                        case "SUCCESS": //支付成功
                            echo $table_html;

                            break;
                        case "REFUND": //转入退款
                            //进行退款查询
                            echo self::query_refunds($data->out_trade_no);
                            break;
                        case "NOTPAY":
                            echo "未支付";
                            break;
                        case "CLOSED":
                            echo "已关闭";
                            break;
                        default:
                            echo "若您输入的是微信订单号，且重复看到这句话，请联系管理员";
                    }


                    //正常成功状态且订单时间在7天内，则添加退款按钮
                    //if (true) {
                    if ($data->trade_state === "SUCCESS" && Mare_Admin_Public::contrast_time($time)) {

?>
                        <p>退款原因：<input type="text" id="npcink-wx-reason" placeholder="请输入退款原因"></p>
                        <p>点击退款按钮后请稍等<b style="color: red;">15秒</b>进行退款处理</p>
                        <?php
                        echo "
                        <button id='wx-order-btn' class='button button-primary refund ' data-order-id='" . $data->out_trade_no . "' data-order-amount='" . $data->amount->payer_total . "'>微信全额退款</button>
                        ";
                    } else {
                        if ($data->trade_state === "SUCCESS" && !Mare_Admin_Public::contrast_time($time)) {
                        ?>
                            <h3>订单时间超过7天，无法使用本功能退款，请联系管理员操作</h3>
<?php
                        }
                    }
                }
            }
            wp_die();
        }

        /**
         * 订单退款
         */

        public static function wx_order_refund()
        {
            $order_id = $_POST['order_id'] ?? ''; //退款ID
            $order_amount = (int)($_POST['order_amount'] ?? 0); //退款金额

            $reason = $_POST['order_reason'] ?? ''; //退款原因



            // 准备退款订单号
            $order_refund_id =  $order_id . "-refund";

            // 发送请求

            try {
                $resp = self::$client->post(
                    'https://api.mch.weixin.qq.com/v3/refund/domestic/refunds', // 请求URL
                    [
                        // JSON请求体
                        'json' => [
                            "out_trade_no" => $order_id,
                            "out_refund_no" => $order_refund_id,
                            "reason" => "商家主动退款",
                            "amount" => [
                                "refund" => $order_amount,
                                "total" => $order_amount,
                                "currency" => "CNY"
                            ],
                        ],
                        'headers' => ['Accept' => 'application/json']
                    ]
                );
                if ($resp->getStatusCode() === 200) { // 处理成功
                    //echo "success,return body = " . $resp->getBody()->getContents() . "\n";
                }
            } catch (RequestException $e) {
                // 进行错误处理
                if ($e->hasResponse()) {
                    //echo "failed,resp code = " . $e->getResponse()->getStatusCode() . " return body = " . $e->getResponse()->getBody() . "\n";
                }
                $data = json_decode($e->getResponse()->getBody()); // 转换成 PHP 对象

                //当前时间
                $current_time = current_time('timestamp');
                $time_now = date('Y-m-d H:i', $current_time);
                //退款成功时间
                $time = self::handle_time($data->success_time);

                //获取登录用户名
                $current_user = wp_get_current_user();
                $user = $current_user->display_name;

                $amount = $data->amount->payer_refund / 100;
                $order = $data->out_trade_no;
                //switch ("PROCESSING") {
                switch ($data->status) {
                    case "PROCESSING": // 退款处理中，进行退款查询
                        //添加数据进数据库文件
                        Mare_Admin_Public::add_data($time_now, $user, $amount, $order, $reason, '微信');
                        //延迟10秒后执行
                        sleep(15);
                        //二次查询并记录
                        echo self::query_refunds($order);
                        break;
                    case "SUCCESS": // 退款成功，进行退款查询
                        //添加数据进数据库文件
                        Mare_Admin_Public::add_data($time, $user, $amount, $order, $reason, '微信');
                        echo self::query_refunds($data->out_trade_no);

                        break;
                    case "CLOSED": // 退款关闭
                        echo "退款关闭";
                        break;
                    case "ABNORMAL": // 退款异常
                        echo "退款异常 - 联系管理员试试";
                        break;
                    default:
                        echo "可能账户昨天提现后没钱了，您可以晚一点再试试";
                }
            }

            wp_die();
        }



        //退款返回
        //应答的微信支付签名验证失败 failed,resp code = 200 return body = {"amount":{"currency":"CNY","discount_refund":0,"from":[],"payer_refund":1,"payer_total":1,"refund":1,"refund_fee":0,"settlement_refund":1,"settlement_total":1,"total":1},"channel":"ORIGINAL","create_time":"2023-06-05T11:57:41+08:00","funds_account":"AVAILABLE","out_refund_no":"refund-D605289669435254","out_trade_no":"D605289669435254","promotion_detail":[],"refund_id":"50300705862023060535316073583","status":"PROCESSING","transaction_id":"4200001882202306054652664747","user_received_account":"工商银行借记卡5167"} 





        /**
         * 退款订单查询
         * 传入商户退款单号
         * 返回退款订单信息
         */
        public static function query_refunds($id)
        {
            //对ID进行处理，组合为商户退款订单号

            $id =  $id . "-refund";


            try {
                $resp = self::$client->request(
                    'GET',
                    'https://api.mch.weixin.qq.com/v3/refund/domestic/refunds/' . $id, //请求URL
                    [
                        'headers' => ['Accept' => 'application/json']
                    ]
                );
                //$statusCode = $resp->getStatusCode();
                //if ($statusCode == 200) { //处理成功
                //    echo "success,return body = " . $resp->getBody()->getContents()."\n";
                //} else if ($statusCode == 204) { //处理成功，无返回Body
                //    echo "success";
                //}
            } catch (RequestException $e) {
                // 进行错误处理
                //echo "简单测 " .$e->getMessage()."\n";
                if ($e->hasResponse()) {
                    //echo "failed,resp code = " . $e->getResponse()->getStatusCode() . " return body = " . $e->getResponse()->getBody() . "\n";

                    $data = json_decode($e->getResponse()->getBody()); // 转换成 PHP 对象




                    $table_html = "<table>";
                    $table_html .= "<tr><td>订单号-退款查询：</td><td>" . $data->out_trade_no . "</td></tr>";
                    $table_html .= "<tr><td>退款时间：</td><td>" .  self::handle_time($data->success_time) . "</td></tr>";
                    $table_html .= "<tr><td>退款金额：</td><td>" . $data->amount->payer_refund / 100 . "元</td></tr>";
                    $table_html .= "<tr><td>退款状态：</td><td>" . ($data->status === "SUCCESS" ? "<span class='green'>退款成功</span>" : "请重试") . "</td></tr>";
                    $table_html .= "</table>";
                    return $table_html;
                }
            }
            wp_die();
        }

        /**
         * 处理拿到的私钥，转为正确的格式
         */
        public static function mare_handle_meat($data)
        {
            $pem_data = $data;
            $pem = trim($pem_data);
            $pem = str_replace(array("-----BEGIN PRIVATE KEY-----", "-----END PRIVATE KEY-----"), '', $pem);
            $pem = preg_replace('/\s+/', '', $pem);
            $pem = chunk_split($pem, 64, "\r\n");
            $pem = "-----BEGIN PRIVATE KEY-----\r\n" . $pem . "-----END PRIVATE KEY-----\r\n";
            return $pem;
        }

        /**
         * 整理时间
         * 传入带时区的时间 - 2023-06-05T11:57:41+08:00
         */
        public static function handle_time($data)
        {
            $datetime = new DateTime($data);
            $datetime->setTimezone(new DateTimeZone(get_option('timezone_string')));
            return $datetime->format('Y-m-d H:i:s');
            //首先创建了一个 DateTime 对象，将时间字符串作为构造函数的参数传入。然后使用 setTimezone() 方法将时区从 UTC 转换为 WordPress 设置的时区。最后调用 format() 方法将时间格式化成指定格式的时间字符串。
        }
    } //end
}
