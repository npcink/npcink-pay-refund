<?php

if (!defined('ABSPATH')) {
    exit;
}

//微信支付相关
use GuzzleHttp\Exception\RequestException;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;


if (!class_exists('Npcink_Pay_Refund_Admin_Wx')) {
    class Npcink_Pay_Refund_Admin_Wx
    {

        /**
         * 认证信息
         */
        public static $client;
        public static $config_error = '';
        public static $merchant_config = array();



        public static function run()
        {
            /**
             * 引入公共方法
             */
            require_once plugin_dir_path(__FILE__) . 'npcink-pay-refund-admin-public.php';

            //订单查询
            add_action('wp_ajax_npcink_pay_refund_wx_order_query', array(__CLASS__, 'npcink_pay_refund_wx_order_query'));

            //订单退款
            add_action('wp_ajax_npcink_pay_refund_wx_order_refund', array(__CLASS__, 'npcink_pay_refund_wx_order_refund'));
        }


        /**
         * 商户相关配置
         */

        public static function config()
        {
            //选项值
            $config = Npcink_Pay_Refund_Admin::npcConfig('wx');
            //私钥
            $key = Npcink_Pay_Refund_Admin::get_options($config, 'cert_key');

            $merchantId = Npcink_Pay_Refund_Admin::get_options($config, 'mch_id'); // 商户号

            $merchantSerialNumber =  Npcink_Pay_Refund_Admin::get_options($config, 'cert_api'); // 商户API证书序列号
            $platformKeyId = Npcink_Pay_Refund_Admin::get_options($config, 'platform_key_id'); // 微信支付公钥ID或平台证书序列号
            $platformPublicKey = Npcink_Pay_Refund_Admin::get_options($config, 'platform_public_key'); // 微信支付公钥或平台证书

            if ('' === $merchantId || '' === $merchantSerialNumber || '' === $platformKeyId || '' === trim((string) $key) || '' === trim((string) $platformPublicKey)) {
                throw new Exception('Missing or invalid WeChat Pay merchant configuration.');
            }

            $merchantPrivateKey = Rsa::from(self::format_private_key($key), Rsa::KEY_TYPE_PRIVATE);
            $wechatpayPublicKey = Rsa::from(self::format_public_key($platformPublicKey), Rsa::KEY_TYPE_PUBLIC);

            self::$merchant_config = array(
                'mchid' => $merchantId,
                'serial_no' => $merchantSerialNumber,
                'private_key' => $merchantPrivateKey,
                'platform_key_id' => $platformKeyId,
                'platform_public_key' => $wechatpayPublicKey,
            );
            self::$client = Builder::factory(array(
                'mchid' => $merchantId,
                'serial' => $merchantSerialNumber,
                'privateKey' => $merchantPrivateKey,
                'certs' => array(
                    $platformKeyId => $wechatpayPublicKey,
                ),
                'timeout' => 20,
                'connect_timeout' => 10,
            ));
        }

        public static function format_public_key($data)
        {
            $pem = trim((string) $data);
            if (false !== strpos($pem, '-----BEGIN ')) {
                return $pem;
            }

            $pem = preg_replace('/\s+/', '', $pem);
            $pem = chunk_split($pem, 64, "\r\n");
            return "-----BEGIN PUBLIC KEY-----\r\n" . $pem . "-----END PUBLIC KEY-----\r\n";
        }

        public static function api_error_message()
        {
            return __('微信支付配置不可用，请检查商户号、商户 API 证书序列号、商户私钥，以及用于验签的微信支付公钥 ID / 平台证书序列号和微信支付公钥 / 平台证书。', 'npcink-pay-refund');
        }

        public static function api_error_log_context($response)
        {
            if (!$response) {
                return '';
            }

            return ' status=' . $response->getStatusCode() . ' body=' . (string) $response->getBody();
        }

        public static function api_call($callback)
        {
            if (!self::ensure_client_ready()) {
                return null;
            }

            try {
                $response = call_user_func($callback, self::$client);
                $data = json_decode((string) $response->getBody());
                return is_object($data) ? $data : null;
            } catch (RequestException $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep gateway diagnostics out of the AJAX response.
                error_log('Npcink_Pay_Refund WeChat API request failed: ' . $e->getMessage() . self::api_error_log_context($e->getResponse()));
                return null;
            } catch (Exception $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep gateway diagnostics out of the AJAX response.
                error_log('Npcink_Pay_Refund WeChat API request failed: ' . $e->getMessage());
                return null;
            } catch (Throwable $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep gateway diagnostics out of the AJAX response.
                error_log('Npcink_Pay_Refund WeChat API request failed: ' . $e->getMessage());
                return null;
            }
        }

        public static function request_json($method, $uri, $options = array())
        {
            $method = strtolower($method);
            if (!in_array($method, array('get', 'post'), true)) {
                return null;
            }

            return self::api_call(function ($client) use ($method, $uri, $options) {
                return $client->chain($uri)->{$method}($options);
            });
        }

        public static function get_transaction($order_id)
        {
            $config = Npcink_Pay_Refund_Admin::npcConfig('wx');
            $mchid = Npcink_Pay_Refund_Admin::get_options($config, 'mch_id');

            return self::api_call(function ($client) use ($order_id, $mchid) {
                return $client->v3->pay->transactions->outTradeNo->_out_trade_no_->get(array(
                    'out_trade_no' => $order_id,
                    'query' => array('mchid' => $mchid),
                ));
            });
        }




        /**
         * 订单查询
         */
        public static function npcink_pay_refund_wx_order_query()
        {
            Npcink_Pay_Refund_Admin_Authority::require_refund_ajax_permission();

            if (!self::ensure_client_ready()) {
                wp_send_json_error(array('message' => self::api_error_message()), 400);
            }

            //选项值
            $config = Npcink_Pay_Refund_Admin::npcConfig('wx');
            
            // 获取传递的订单
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by require_refund_ajax_permission().
            $order_id = isset($_REQUEST['order_id']) ? sanitize_text_field(wp_unslash($_REQUEST['order_id'])) : '';
            if ('' === $order_id) {
                wp_send_json_error(array('message' => __('请输入微信订单号。', 'npcink-pay-refund')), 400);
            }

            // 发送请求

            $data = self::get_transaction($order_id);

            if (!$data || empty($data->trade_state)) {
                wp_send_json_error(array('message' => __('微信订单查询失败，请检查订单号和鉴权配置。', 'npcink-pay-refund')), 400);
            }

                    $time = self::handle_time(isset($data->success_time) ? $data->success_time : '');
                    $amount = isset($data->amount->payer_total) ? $data->amount->payer_total / 100 : 0;
                    $order = isset($data->out_trade_no) ? $data->out_trade_no : $order_id;
                    // echo "failed,resp code = " . $e->getResponse()->getStatusCode() . " return body = " . $e->getResponse()->getBody() . "\n";
                    //根据当前订单状态进行对应操作

                    //准备订单成功数据
                    $table_html = "<table>";
                    $table_html .= "<tr><td>订单号：</td><td>" . esc_html($order) . "</td></tr>";
                    $table_html .= "<tr><td>时间：</td><td>" .  esc_html($time)  . "</td></tr>";
                    $table_html .= "<tr><td>金额：</td><td>" . esc_html($amount) . "元</td></tr>";
                    $table_html .= "<tr><td>状态：</td><td><span class='green'>支付成功</span></td></tr>";
                    $table_html .= "</table>";


                    switch ($data->trade_state) {
                        case "SUCCESS": //支付成功
                            $response_html = $table_html;
                            break;
                        case "REFUND": //转入退款
                            //进行退款查询
                            $response_html = self::query_refunds($order);
                            break;
                        case "NOTPAY":
                            $response_html = esc_html__('未支付', 'npcink-pay-refund');
                            break;
                        case "CLOSED":
                            $response_html = esc_html__('已关闭', 'npcink-pay-refund');
                            break;
                        default:
                            $response_html = esc_html__('若您输入的是微信订单号，且重复看到这句话，请联系管理员', 'npcink-pay-refund');
                    }


                    //正常成功状态且订单时间在7天内，则添加退款按钮
                    //if (true) {
                    if ($data->trade_state === "SUCCESS" && Npcink_Pay_Refund_Admin_Public::contrast_time($time)) {

                        $response_html .= '<p>退款原因：<input type="text" id="npcink-pay-refund-wx-reason" placeholder="请输入退款原因"></p>';
                        $response_html .= '<p>点击退款按钮后请稍等进行退款处理</p>';
                        $response_html .= "<button id='wx-order-btn' class='button button-primary refund ' data-order-id='" . esc_attr($order) . "' data-order-amount='" . esc_attr(isset($data->amount->payer_total) ? $data->amount->payer_total : 0) . "'>微信全额退款</button>";
                    } else {
                        if ($data->trade_state === "SUCCESS" && !Npcink_Pay_Refund_Admin_Public::contrast_time($time)) {
                            $response_html .= '<h3>订单时间超过7天，无法使用本功能退款，请联系管理员操作</h3>';
                        }
                    }
            wp_send_json_success(array('html' => $response_html));
        }

        /**
         * 订单退款
         */

        public static function npcink_pay_refund_wx_order_refund()
        {
            Npcink_Pay_Refund_Admin_Authority::require_refund_ajax_permission();

            if (!self::ensure_client_ready()) {
                wp_send_json_error(array('message' => self::api_error_message()), 400);
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by require_refund_ajax_permission().
            $order_id = isset($_POST['order_id']) ? sanitize_text_field(wp_unslash($_POST['order_id'])) : ''; //退款ID

            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by require_refund_ajax_permission(); sanitized by Npcink_Pay_Refund_Admin::sanitize_textarea_value().
            $reason = isset($_POST['order_reason']) ? Npcink_Pay_Refund_Admin::sanitize_textarea_value(wp_unslash($_POST['order_reason'])) : ''; //退款原因
            $api_reason = self::format_refund_reason($reason);

            if ('' === $order_id || '' === $api_reason) {
                wp_send_json_error(array('message' => __('订单号和退款原因不能为空。', 'npcink-pay-refund')), 400);
            }

            $order_data = self::get_transaction($order_id);
            if (!$order_data || empty($order_data->trade_state)) {
                wp_send_json_error(array('message' => __('订单查询失败，未执行退款。', 'npcink-pay-refund')), 400);
            }

            $order_time = self::handle_time(isset($order_data->success_time) ? $order_data->success_time : '');
            if ('SUCCESS' !== $order_data->trade_state || !Npcink_Pay_Refund_Admin_Public::contrast_time($order_time)) {
                wp_send_json_error(array('message' => __('该订单当前状态或时间窗口不可退款。', 'npcink-pay-refund')), 400);
            }

            $order_amount = isset($order_data->amount->payer_total) ? (int) $order_data->amount->payer_total : 0;
            if ($order_amount <= 0) {
                wp_send_json_error(array('message' => __('订单金额无效，未执行退款。', 'npcink-pay-refund')), 400);
            }



            // 准备退款订单号
            $order_refund_id =  $order_id . "-refund";
            if (!Npcink_Pay_Refund_Admin_Public::claim_refund($order_id, '微信')) {
                wp_send_json_error(array('message' => __('该订单已提交退款或正在处理中，请勿重复操作。', 'npcink-pay-refund')), 409);
            }

            // 发送请求

            $data = self::request_json(
                'POST',
                'v3/refund/domestic/refunds',
                array(
                    'json' => array(
                        'out_trade_no' => $order_id,
                        'out_refund_no' => $order_refund_id,
                        'reason' => $api_reason,
                        'amount' => array(
                            'refund' => $order_amount,
                            'total' => $order_amount,
                            'currency' => 'CNY'
                        ),
                    ),
                    'headers' => array('Accept' => 'application/json')
                )
            );

            if (!$data || empty($data->status)) {
                Npcink_Pay_Refund_Admin_Public::release_refund_claim($order_id, '微信');
                wp_send_json_error(array('message' => __('微信退款请求失败，请稍后重试。', 'npcink-pay-refund')), 400);
            }

                //当前时间
                $current_time = current_time('timestamp');
                $time_now = wp_date('Y-m-d H:i', $current_time);
                //退款成功时间
                $time = self::handle_time(isset($data->success_time) ? $data->success_time : '');

                //获取登录用户名
                $current_user = wp_get_current_user();
                $user = $current_user->display_name;

                $amount = isset($data->amount->payer_refund) ? $data->amount->payer_refund / 100 : $order_amount / 100;
                $order = isset($data->out_trade_no) ? $data->out_trade_no : $order_id;
                //switch ("PROCESSING") {
                switch ($data->status) {
                    case "PROCESSING": // 退款处理中，进行退款查询
                        //添加数据进数据库文件
                        Npcink_Pay_Refund_Admin_Public::add_data($time_now, $user, $amount, $order, $reason, '微信');
                        //二次查询并记录
                        $response_html = self::query_refunds($order);
                        break;
                    case "SUCCESS": // 退款成功，进行退款查询
                        //添加数据进数据库文件
                        Npcink_Pay_Refund_Admin_Public::add_data($time, $user, $amount, $order, $reason, '微信');
                        $response_html = self::query_refunds($order);

                        break;
                    case "CLOSED": // 退款关闭
                        Npcink_Pay_Refund_Admin_Public::release_refund_claim($order_id, '微信');
                        wp_send_json_error(array('message' => __('退款关闭', 'npcink-pay-refund')), 400);
                        break;
                    case "ABNORMAL": // 退款异常
                        Npcink_Pay_Refund_Admin_Public::release_refund_claim($order_id, '微信');
                        wp_send_json_error(array('message' => __('退款异常，请联系管理员。', 'npcink-pay-refund')), 400);
                        break;
                    default:
                        Npcink_Pay_Refund_Admin_Public::release_refund_claim($order_id, '微信');
                        wp_send_json_error(array('message' => __('退款失败，请稍后重试。', 'npcink-pay-refund')), 400);
                }

            wp_send_json_success(array('html' => $response_html));
        }

        public static function format_refund_reason($reason)
        {
            $reason = trim(preg_replace('/\s+/', ' ', (string) $reason));
            if (function_exists('mb_substr')) {
                return mb_substr($reason, 0, 80);
            }

            return substr($reason, 0, 80);
        }

        /**
         * Whether the WeChat HTTP client can be used for API calls.
         */
        public static function client_is_ready()
        {
            return self::$client && !empty(self::$merchant_config['mchid']) && !empty(self::$merchant_config['serial_no']) && !empty(self::$merchant_config['private_key']) && !empty(self::$merchant_config['platform_key_id']) && !empty(self::$merchant_config['platform_public_key']);
        }

        /**
         * Load the WeChat SDK only after the request has passed permission checks.
         */
        public static function ensure_client_ready()
        {
            if (self::client_is_ready()) {
                return true;
            }

            try {
                self::config();
                return self::client_is_ready();
            } catch (Exception $e) {
                self::$config_error = $e->getMessage();
                self::$client = null;
                if ('Missing or invalid WeChat Pay merchant configuration.' !== $e->getMessage()) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep SDK diagnostics out of the AJAX response.
                    error_log('Npcink_Pay_Refund WeChat config init failed: ' . $e->getMessage());
                }
                return false;
            } catch (Throwable $e) {
                self::$config_error = $e->getMessage();
                self::$client = null;
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep SDK diagnostics out of the AJAX response.
                error_log('Npcink_Pay_Refund WeChat config init failed: ' . $e->getMessage());
                return false;
            }
        }

        public static function diagnose_config()
        {
            $config = Npcink_Pay_Refund_Admin::npcConfig('wx');
            $items = array();
            $ok = true;

            $sdk_ready = class_exists(Builder::class) && class_exists(Rsa::class);
            $items[] = array(
                'status' => $sdk_ready ? 'ok' : 'error',
                'label' => __('微信支付 SDK', 'npcink-pay-refund'),
                'message' => $sdk_ready ? __('Composer 自动加载正常。', 'npcink-pay-refund') : __('未找到微信支付 SDK，请重新生成发布包。', 'npcink-pay-refund'),
            );
            $ok = $ok && $sdk_ready;

            $fields = array(
                'mch_id' => __('商户号', 'npcink-pay-refund'),
                'cert_api' => __('商户 API 证书序列号', 'npcink-pay-refund'),
                'cert_key' => __('商户私钥', 'npcink-pay-refund'),
            );
            foreach ($fields as $field => $label) {
                $value = trim((string) Npcink_Pay_Refund_Admin::get_options($config, $field));
                $has_value = '' !== $value;
                $items[] = array(
                    'status' => $has_value ? 'ok' : 'error',
                    'label' => $label,
                    'message' => $has_value ? __('已配置。', 'npcink-pay-refund') : __('未配置。', 'npcink-pay-refund'),
                );
                $ok = $ok && $has_value;
            }

            $verification_fields = array(
                'platform_key_id' => __('微信支付公钥 ID / 平台证书序列号', 'npcink-pay-refund'),
                'platform_public_key' => __('微信支付公钥 / 平台证书', 'npcink-pay-refund'),
            );
            foreach ($verification_fields as $field => $label) {
                $value = trim((string) Npcink_Pay_Refund_Admin::get_options($config, $field));
                $has_value = '' !== $value;
                $items[] = array(
                    'status' => $has_value ? 'ok' : 'error',
                    'label' => $label,
                    'message' => $has_value ? __('已配置。', 'npcink-pay-refund') : __('保存时允许留空；执行微信查询或退款前必须配置。', 'npcink-pay-refund'),
                );
                $ok = $ok && $has_value;
            }

            if ($ok) {
                try {
                    self::$client = null;
                    self::$merchant_config = array();
                    self::config();
                    $client_ready = self::client_is_ready();
                    $items[] = array(
                        'status' => $client_ready ? 'ok' : 'error',
                        'label' => __('密钥解析', 'npcink-pay-refund'),
                        'message' => $client_ready ? __('商户私钥和微信支付公钥可解析。', 'npcink-pay-refund') : __('客户端未完成初始化。', 'npcink-pay-refund'),
                    );
                    $ok = $ok && $client_ready;
                } catch (Exception $e) {
                    $ok = false;
                    $items[] = array(
                        'status' => 'error',
                        'label' => __('密钥解析', 'npcink-pay-refund'),
                        'message' => __('解析失败，请检查 PEM 内容和序列号。', 'npcink-pay-refund'),
                    );
                } catch (Throwable $e) {
                    $ok = false;
                    $items[] = array(
                        'status' => 'error',
                        'label' => __('密钥解析', 'npcink-pay-refund'),
                        'message' => __('解析失败，请检查依赖和 PEM 内容。', 'npcink-pay-refund'),
                    );
                }
            }

            return array(
                'status' => $ok ? 'ok' : 'error',
                'message' => $ok ? __('微信配置本地检测通过。', 'npcink-pay-refund') : __('微信配置仍有阻塞项。', 'npcink-pay-refund'),
                'items' => $items,
            );
        }

        /**
         * 退款订单查询
         * 传入商户退款单号
         * 返回退款订单信息
         */
        public static function query_refunds($id)
        {
            //对ID进行处理，组合为商户退款订单号

            $id =  $id . "-refund";


            $data = self::api_call(function ($client) use ($id) {
                return $client->v3->refund->domestic->refunds->_out_refund_no_->get(array(
                    'out_refund_no' => $id,
                ));
            });

            if (!$data) {
                return esc_html__('退款查询失败，请稍后重试。', 'npcink-pay-refund');
            }

            $refund_time = isset($data->success_time) ? self::handle_time($data->success_time) : '';
            $refund_amount = isset($data->amount->payer_refund) ? $data->amount->payer_refund / 100 : 0;
            $status = isset($data->status) ? $data->status : '';

            $table_html = "<table>";
            $table_html .= "<tr><td>订单号-退款查询：</td><td>" . esc_html(isset($data->out_trade_no) ? $data->out_trade_no : '') . "</td></tr>";
            $table_html .= "<tr><td>退款时间：</td><td>" . esc_html($refund_time) . "</td></tr>";
            $table_html .= "<tr><td>退款金额：</td><td>" . esc_html($refund_amount) . "元</td></tr>";
            $table_html .= "<tr><td>退款状态：</td><td>" . ('SUCCESS' === $status ? "<span class='green'>退款成功</span>" : esc_html__('请重试', 'npcink-pay-refund')) . "</td></tr>";
            $table_html .= "</table>";
            return $table_html;
        }

        /**
         * 处理拿到的私钥，转为正确的格式
         */
        public static function format_private_key($data)
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
            try {
                $datetime = new DateTime($data);
            } catch (Exception $e) {
                return '';
            }
            $timezone = get_option('timezone_string');
            if (empty($timezone)) {
                $timezone = 'UTC';
            }
            $datetime->setTimezone(new DateTimeZone($timezone));
            return $datetime->format('Y-m-d H:i:s');
            //首先创建了一个 DateTime 对象，将时间字符串作为构造函数的参数传入。然后使用 setTimezone() 方法将时区从 UTC 转换为 WordPress 设置的时区。最后调用 format() 方法将时间格式化成指定格式的时间字符串。
        }
    } //end
}
