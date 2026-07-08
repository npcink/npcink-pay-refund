<?php

if (!defined('ABSPATH')) {
    exit;
}

//支付宝支付相关
use Alipay\EasySDK\Kernel\Factory;
use Alipay\EasySDK\Kernel\Util\ResponseChecker;
use Alipay\EasySDK\Kernel\Config;


if (!class_exists('Npcink_Pay_Refund_Admin_Zfb')) {
    class Npcink_Pay_Refund_Admin_Zfb
    {
        public static function run()
        {
            /**
             * 引入公共方法
             */
            require_once plugin_dir_path(__FILE__) . 'npcink-pay-refund-admin-public.php';

            //处理查询请求
            add_action('wp_ajax_npcink_pay_refund_zfb_order_query', array(__CLASS__, 'npcink_pay_refund_zfb_order_query'));

            //退款功能
            add_action('wp_ajax_npcink_pay_refund_zfb_order_refund', array(__CLASS__, 'npcink_pay_refund_zfb_order_refund'));
        }

        public static function gateway_result_log_context($result)
        {
            $context = array();
            foreach (array('code', 'msg', 'subCode', 'subMsg') as $field) {
                if (isset($result->{$field}) && is_scalar($result->{$field})) {
                    $context[$field] = sanitize_text_field((string) $result->{$field});
                }
            }

            return wp_json_encode($context);
        }

        /**
         * 准备认证信息
         */
        static public function getOptions()
        {
            //准备设置选项
            $config =   Npcink_Pay_Refund_Admin::npcConfig('zfb');


            $options = new Config();
            $options->protocol = 'https';
            $options->gatewayHost = 'openapi.alipay.com';
            $options->signType = 'RSA2';

            //$options->appId = '2021002134609167';
            $options->appId = Npcink_Pay_Refund_Admin::get_options($config, 'appid');

            // 为避免私钥随源码泄露，推荐从文件中读取私钥字符串而不是写入源码中
            $options->merchantPrivateKey = Npcink_Pay_Refund_Admin::get_options($config, 'private_key');



            //注：如果采用非证书模式，则无需赋值上面的三个证书路径，改为赋值如下的支付宝公钥字符串即可
            $options->alipayPublicKey = Npcink_Pay_Refund_Admin::get_options($config, 'public_key');

            //可设置异步通知接收服务地址（可选）
            $options->notifyUrl = "<-- 请填写您的支付类接口异步通知接收服务地址，例如：https://www.test.com/callback -->";

            return $options;
        }

        /**
         * 查询请求
         */
        static public function npcink_pay_refund_zfb_order_query()
        {
            Npcink_Pay_Refund_Admin_Authority::require_refund_ajax_permission();
            if (!self::ensure_sdk_ready()) {
                wp_send_json_error(array('message' => __('支付宝配置不可用，请检查 APP ID、应用私钥和支付宝公钥。', 'npcink-pay-refund')), 400);
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by require_refund_ajax_permission().
            $param = isset($_REQUEST['param']) ? sanitize_text_field(wp_unslash($_REQUEST['param'])) : ''; // 获取传递的参数
            if ('' === $param) {
                wp_send_json_error(array('message' => __('请输入支付宝订单号。', 'npcink-pay-refund')), 400);
            }

            try {
                // 2. 发起API调用（以支付能力下的统一收单交易创建接口为例）
                $result = Factory::payment()->common()->query($param);
                $responseChecker = new ResponseChecker();
                // 3. 处理响应或异常
                if ($responseChecker->success($result)) {
                    $data = $result;
                    $table_html = "<table>";
                    $table_html .= "<tr><td>订单号：</td><td>" . esc_html($data->outTradeNo) . "</td></tr>";
                    $table_html .= "<tr><td>时间：</td><td>" . esc_html($data->sendPayDate) . "</td></tr>";
                    $table_html .= "<tr><td>金额：</td><td>" . esc_html($data->totalAmount) . "</td></tr>";
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
                    //订单支付成功
                    if ($data->tradeStatus == "TRADE_SUCCESS" && Npcink_Pay_Refund_Admin_Public::contrast_time($data->sendPayDate)) {
                        $table_html .= '<p>退款原因：<input type="text" id="npcink-pay-refund-zfb-reason" placeholder="请输入退款原因"></p>';
                        $table_html .= " <button id='order-btn' class='button button-primary refund' data-order-id='" . esc_attr($data->outTradeNo) . "' data-order-amount='" . esc_attr($data->totalAmount) . "' data-order-time='" . esc_attr($data->sendPayDate) . "'>支付宝全额退款</button>";
                    } else {

                        if ($data->tradeStatus == "TRADE_SUCCESS" && !Npcink_Pay_Refund_Admin_Public::contrast_time($data->sendPayDate)) {
                            /* translators: %d: configured refund window in days. */
                            $table_html .= '<h3>' . esc_html(sprintf(__('该订单时间超过 %d 天，无法使用本功能退款，请联系管理员操作', 'npcink-pay-refund'), Npcink_Pay_Refund_Admin_Public::refund_window_days())) . '</h3>';
                        }
                    }
                    wp_send_json_success(array('html' => $table_html));
                } else {
                    //$error_msg = "调用失败，原因：" . $result->msg . "，" . $result->subMsg . PHP_EOL;
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep gateway diagnostics out of the AJAX response.
                    error_log('Npcink_Pay_Refund Alipay query rejected: ' . self::gateway_result_log_context($result));
                    wp_send_json_error(array('message' => __('支付宝订单查询失败，请检查订单号或稍后重试。', 'npcink-pay-refund')), 400);
                }
            } catch (Exception $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep gateway diagnostics out of the AJAX response.
                error_log('Npcink_Pay_Refund Alipay query failed: ' . $e->getMessage());
                wp_send_json_error(array('message' => __('调用失败，请检查支付宝配置或稍后重试。', 'npcink-pay-refund')), 500);
            }
        }





        /**
         * 退款功能
         */
        public static function npcink_pay_refund_zfb_order_refund()
        {
            Npcink_Pay_Refund_Admin_Authority::require_refund_ajax_permission();
            if (!self::ensure_sdk_ready()) {
                wp_send_json_error(array('message' => __('支付宝配置不可用，请检查 APP ID、应用私钥和支付宝公钥。', 'npcink-pay-refund')), 400);
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by require_refund_ajax_permission().
            $order_id = isset($_POST['order_id']) ? sanitize_text_field(wp_unslash($_POST['order_id'])) : ''; // 获取传递的订单号
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by require_refund_ajax_permission(); sanitized by Npcink_Pay_Refund_Admin::sanitize_textarea_value().
            $order_reason = isset($_POST['order_reason']) ? Npcink_Pay_Refund_Admin::sanitize_textarea_value(wp_unslash($_POST['order_reason'])) : ''; // 获取传递的退款原因

            if ('' === $order_id || '' === $order_reason) {
                wp_send_json_error(array('message' => __('订单号和退款原因不能为空。', 'npcink-pay-refund')), 400);
            }

            //获取登录用户名
            $current_user = wp_get_current_user();
            $user = $current_user->display_name;
            $refund_claimed = false;

            try {
                $query_result = Factory::payment()->common()->query($order_id);
                $responseChecker = new ResponseChecker();
                if (!$responseChecker->success($query_result)) {
                    wp_send_json_error(array('message' => __('订单查询失败，未执行退款。', 'npcink-pay-refund')), 400);
                }

                if ('TRADE_SUCCESS' !== $query_result->tradeStatus) {
                    wp_send_json_error(array('message' => __('该订单当前状态不可退款。', 'npcink-pay-refund')), 400);
                }

                if (!Npcink_Pay_Refund_Admin_Public::contrast_time($query_result->sendPayDate)) {
                    /* translators: %d: configured refund window in days. */
                    wp_send_json_error(array('message' => sprintf(__('该订单时间超过 %d 天，未执行退款。', 'npcink-pay-refund'), Npcink_Pay_Refund_Admin_Public::refund_window_days())), 400);
                }

                $order_amount = $query_result->totalAmount;
                $order_time = $query_result->sendPayDate;
                if (!Npcink_Pay_Refund_Admin_Public::claim_refund($order_id, '支付宝')) {
                    wp_send_json_error(array('message' => __('该订单已提交退款或正在处理中，请勿重复操作。', 'npcink-pay-refund')), 409);
                }
                $refund_claimed = true;

                //2. 发起API调用（以支付能力下的统一收单交易创建接口为例）
                $result = Factory::payment()->common()->refund($order_id, $order_amount);
                //3. 处理响应或异常
                if ($responseChecker->success($result)) {
                    $table_html = "<table>";
                    $table_html .= "<tr><td>订单号：</td><td>" . esc_html($order_id) . "</td></tr>";
                    $table_html .= "<tr><td>时间：</td><td>" . esc_html($order_time) . "</td></tr>";
                    $table_html .= "<tr><td>金额：</td><td>" . esc_html($order_amount) . "</td></tr>";
                    $table_html .= "<tr><td>状态：</td><td><b class='green'>已退款</b></td></tr>";
                    $table_html .= "</td></tr>";
                    $table_html .= "</table>";

                    //添加数据进JSON文件
                    Npcink_Pay_Refund_Admin_Public::add_data($order_time, $user, $order_amount, $order_id, $order_reason, '支付宝');
                    $refund_claimed = false;

                    wp_send_json_success(array('html' => $table_html));
                } else {
                    if ($refund_claimed) {
                        Npcink_Pay_Refund_Admin_Public::release_refund_claim($order_id, '支付宝');
                    }
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep gateway diagnostics out of the AJAX response.
                    error_log('Npcink_Pay_Refund Alipay refund rejected: ' . self::gateway_result_log_context($result));
                    wp_send_json_error(array('message' => __('支付宝退款失败，请稍后重试或联系管理员检查日志。', 'npcink-pay-refund')), 400);
                }
            } catch (Exception $e) {
                if ($refund_claimed) {
                    Npcink_Pay_Refund_Admin_Public::release_refund_claim($order_id, '支付宝');
                }
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep gateway diagnostics out of the AJAX response.
                error_log('Npcink_Pay_Refund Alipay refund failed: ' . $e->getMessage());
                wp_send_json_error(array('message' => __('退款失败，请检查支付宝配置或稍后重试。', 'npcink-pay-refund')), 500);
            }
        }

        public static function ensure_sdk_ready()
        {
            try {
                if (!class_exists(Factory::class) || !class_exists(Config::class)) {
                    return false;
                }
                if (!self::config_has_required_fields()) {
                    return false;
                }
                Factory::setOptions(self::getOptions());
                return true;
            } catch (Exception $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep SDK diagnostics out of the AJAX response.
                error_log('Npcink_Pay_Refund Alipay SDK init failed: ' . $e->getMessage());
                return false;
            } catch (Throwable $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep SDK diagnostics out of the AJAX response.
                error_log('Npcink_Pay_Refund Alipay SDK init failed: ' . $e->getMessage());
                return false;
            }
        }

        public static function config_has_required_fields()
        {
            $config = Npcink_Pay_Refund_Admin::npcConfig('zfb');
            foreach (array('appid', 'private_key', 'public_key') as $field) {
                if ('' === trim((string) Npcink_Pay_Refund_Admin::get_options($config, $field))) {
                    return false;
                }
            }

            return true;
        }

        public static function diagnose_config()
        {
            $config = Npcink_Pay_Refund_Admin::npcConfig('zfb');
            $items = array();
            $ok = true;

            $sdk_ready = class_exists(Factory::class) && class_exists(Config::class) && class_exists(ResponseChecker::class);
            $items[] = array(
                'status' => $sdk_ready ? 'ok' : 'error',
                'label' => __('支付宝 SDK', 'npcink-pay-refund'),
                'message' => $sdk_ready ? __('Composer 自动加载正常。', 'npcink-pay-refund') : __('未找到支付宝 EasySDK，请重新生成发布包。', 'npcink-pay-refund'),
            );
            $ok = $ok && $sdk_ready;

            $fields = array(
                'appid' => __('APP ID', 'npcink-pay-refund'),
                'private_key' => __('应用私钥', 'npcink-pay-refund'),
                'public_key' => __('支付宝公钥', 'npcink-pay-refund'),
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

            if ($ok) {
                try {
                    Factory::setOptions(self::getOptions());
                    $items[] = array(
                        'status' => 'ok',
                        'label' => __('SDK 初始化', 'npcink-pay-refund'),
                        'message' => __('本地初始化通过；真实订单查询仍需商户配置有效。', 'npcink-pay-refund'),
                    );
                } catch (Exception $e) {
                    $ok = false;
                    $items[] = array(
                        'status' => 'error',
                        'label' => __('SDK 初始化', 'npcink-pay-refund'),
                        'message' => __('初始化失败，请检查密钥内容。', 'npcink-pay-refund'),
                    );
                } catch (Throwable $e) {
                    $ok = false;
                    $items[] = array(
                        'status' => 'error',
                        'label' => __('SDK 初始化', 'npcink-pay-refund'),
                        'message' => __('初始化失败，请检查依赖和密钥内容。', 'npcink-pay-refund'),
                    );
                }
            }

            return array(
                'status' => $ok ? 'ok' : 'error',
                'message' => $ok ? __('支付宝配置本地检测通过。', 'npcink-pay-refund') : __('支付宝配置仍有阻塞项。', 'npcink-pay-refund'),
                'items' => $items,
            );
        }
    }
}
