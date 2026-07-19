<?php

if (!defined('ABSPATH')) {
    exit;
}

//支付宝支付相关
use Alipay\EasySDK\Kernel\Factory;
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

            return $options;
        }

        /**
         * 查询请求
         */
        static public function npcink_pay_refund_zfb_order_query()
        {
            Npcink_Pay_Refund_Admin_Authority::require_refund_ajax_permission();

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by require_refund_ajax_permission().
            $param = isset($_REQUEST['param']) ? sanitize_text_field(wp_unslash($_REQUEST['param'])) : ''; // 获取传递的参数
            if ('' === $param) {
                wp_send_json_error(array('message' => __('请输入支付宝订单号。', 'npcink-pay-refund')), 400);
            }

            if (!empty(Npcink_Pay_Refund_Admin_Public::get_refund_reconciliation($param, '支付宝'))) {
                self::send_reconciliation_result($param);
            }

            if (!self::ensure_sdk_ready()) {
                wp_send_json_error(array('message' => __('支付宝配置不可用，请检查 APP ID、应用私钥和支付宝公钥。', 'npcink-pay-refund')), 400);
            }

			$uncertain_resolution = self::maybe_send_uncertain_refund_result($param);
			$retryable_uncertain = 'not_found' === $uncertain_resolution['state'];

            try {
                // 2. 发起API调用（以支付能力下的统一收单交易创建接口为例）
                $result = Factory::payment()->common()->query($param);
                // 3. 处理响应或异常
				if ($result && isset($result->code) && '10000' === (string) $result->code) {
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
						if ($retryable_uncertain) {
							$table_html .= '<p class="notice notice-warning inline npcink-pay-refund-refund-notice"><strong>' . esc_html__('原退款请求尚未在支付宝查询到；再次提交时将复用原退款请求号和原参数。', 'npcink-pay-refund') . '</strong></p>';
						}
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
			} catch (Throwable $e) {
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

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by require_refund_ajax_permission().
            $order_id = isset($_POST['order_id']) ? sanitize_text_field(wp_unslash($_POST['order_id'])) : ''; // 获取传递的订单号
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by require_refund_ajax_permission(); sanitized by Npcink_Pay_Refund_Admin::sanitize_textarea_value().
            $order_reason = isset($_POST['order_reason']) ? Npcink_Pay_Refund_Admin::sanitize_textarea_value(wp_unslash($_POST['order_reason'])) : ''; // 获取传递的退款原因

            if ('' === $order_id) {
                wp_send_json_error(array('message' => __('订单号不能为空。', 'npcink-pay-refund')), 400);
            }

            if (!empty(Npcink_Pay_Refund_Admin_Public::get_refund_reconciliation($order_id, '支付宝'))) {
                self::send_reconciliation_result($order_id);
            }

            if (!self::ensure_sdk_ready()) {
                wp_send_json_error(array('message' => __('支付宝配置不可用，请检查 APP ID、应用私钥和支付宝公钥。', 'npcink-pay-refund')), 400);
            }

			$uncertain_resolution = self::maybe_send_uncertain_refund_result($order_id);
			$retry_context = 'not_found' === $uncertain_resolution['state'] ? $uncertain_resolution['context'] : array();
			if (!empty($retry_context['n_reason'])) {
				$order_reason = $retry_context['n_reason'];
			}

            if ('' === $order_reason) {
                wp_send_json_error(array('message' => __('订单号和退款原因不能为空。', 'npcink-pay-refund')), 400);
            }

            //获取登录用户名
			$current_user = wp_get_current_user();
			$user = !empty($retry_context['n_user']) ? $retry_context['n_user'] : $current_user->display_name;
            $refund_claimed = false;
			$refund_uncertain_saved = !empty($retry_context);

            try {
                $query_result = Factory::payment()->common()->query($order_id);
				if (!$query_result || !isset($query_result->code) || '10000' !== (string) $query_result->code) {
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
				$order_time = !empty($retry_context['n_time']) ? $retry_context['n_time'] : $query_result->sendPayDate;
				if (!empty($retry_context['n_amount']) && abs((float) $retry_context['n_amount'] - (float) $order_amount) >= 0.001) {
					wp_send_json_error(array('message' => __('订单金额与原支付宝退款请求不一致，订单继续锁定。请在支付宝商家平台核对后再处理。', 'npcink-pay-refund')), 409);
				}
				if (empty($retry_context) && !Npcink_Pay_Refund_Admin_Public::claim_refund($order_id, '支付宝')) {
                    wp_send_json_error(array('message' => __('该订单已提交退款或正在处理中，请勿重复操作。', 'npcink-pay-refund')), 409);
                }
                $refund_claimed = true;

                $request_id = self::refund_request_id($order_id);
                $refund_context = array(
                    'n_time' => $order_time,
                    'n_user' => $user,
                    'n_amount' => $order_amount,
                    'n_order' => $order_id,
                    'n_reason' => $order_reason,
                    'n_type' => '支付宝',
                    'request_id' => $request_id,
                );
				if (empty($retry_context) && !Npcink_Pay_Refund_Admin_Public::save_refund_uncertain($refund_context)) {
                    Npcink_Pay_Refund_Admin_Public::release_refund_claim($order_id, '支付宝');
                    $refund_claimed = false;
                    wp_send_json_error(array('message' => __('无法保存退款请求状态，未向支付宝提交退款。请检查数据库后重试。', 'npcink-pay-refund')), 500);
                }
                $refund_uncertain_saved = true;

                // The stable out_request_no makes a query-first retry idempotent.
				$submission = self::submit_refund($order_id, $order_amount, $order_reason, $request_id);
				$result = is_array($submission) && isset($submission['response']) ? $submission['response'] : null;
				$submit_state = self::classify_refund_submit_response($submission, $order_id, $order_amount);
                //3. 处理响应或异常
				if ('confirmed' === $submit_state) {
                    $table_html = "<table>";
                    $table_html .= "<tr><td>订单号：</td><td>" . esc_html($order_id) . "</td></tr>";
                    $table_html .= "<tr><td>时间：</td><td>" . esc_html($order_time) . "</td></tr>";
                    $table_html .= "<tr><td>金额：</td><td>" . esc_html($order_amount) . "</td></tr>";
                    $table_html .= "<tr><td>状态：</td><td><b class='green'>已退款</b></td></tr>";
                    $table_html .= "</td></tr>";
                    $table_html .= "</table>";

                    $recorded = Npcink_Pay_Refund_Admin_Public::add_data($order_time, $user, $order_amount, $order_id, $order_reason, '支付宝');
                    if (false === $recorded) {
                        $refund_claimed = false;
                        wp_send_json_error(array(
                            'message' => __('支付宝退款已成功，但本地退款记录保存失败。订单已锁定等待管理员对账，请勿重复退款。', 'npcink-pay-refund'),
                            'provider_status' => 'SUCCESS',
                            'reconciliation_required' => true,
                        ), 500);
                    }
                    $refund_claimed = false;
					$refund_uncertain_saved = false;

                    wp_send_json_success(array('html' => $table_html));
                } else {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep gateway diagnostics out of the AJAX response.
                    error_log('Npcink_Pay_Refund Alipay refund rejected: ' . self::gateway_result_log_context($result));
					if ('unknown' === $submit_state || self::refund_failure_is_uncertain($result)) {
						$refund_claimed = false;
						wp_send_json_error(array(
							'message' => __('支付宝退款结果暂时无法确认。订单已锁定；再次查询或退款时会先用原退款请求号核对状态，请勿另行重复退款。', 'npcink-pay-refund'),
							'provider_status' => 'UNKNOWN',
							'reconciliation_required' => true,
						), 502);
					}

					Npcink_Pay_Refund_Admin_Public::clear_refund_uncertain($order_id, '支付宝');
					Npcink_Pay_Refund_Admin_Public::release_refund_claim($order_id, '支付宝');
					$refund_claimed = false;
					$refund_uncertain_saved = false;
                    wp_send_json_error(array('message' => __('支付宝退款失败，请稍后重试或联系管理员检查日志。', 'npcink-pay-refund')), 400);
                }
			} catch (Throwable $e) {
				if ($refund_claimed && !$refund_uncertain_saved) {
                    Npcink_Pay_Refund_Admin_Public::release_refund_claim($order_id, '支付宝');
                }
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep gateway diagnostics out of the AJAX response.
                error_log('Npcink_Pay_Refund Alipay refund failed: ' . $e->getMessage());
				if ($refund_uncertain_saved) {
					wp_send_json_error(array(
						'message' => __('支付宝退款调用中断，结果暂时无法确认。订单已锁定；再次操作时会先查询原退款请求号，请勿重复退款。', 'npcink-pay-refund'),
						'provider_status' => 'UNKNOWN',
						'reconciliation_required' => true,
					), 502);
				}
				wp_send_json_error(array('message' => __('退款失败，请检查支付宝配置或稍后重试。', 'npcink-pay-refund')), 500);
            }
        }

		public static function refund_request_id($order_id)
		{
			return 'npcink-refund-' . substr(hash('sha256', sanitize_text_field($order_id)), 0, 40);
		}

		public static function refund_request_params($order_id, $order_amount, $reason, $request_id)
		{
			$reason = Npcink_Pay_Refund_Admin::sanitize_textarea_value($reason);
			if (function_exists('mb_substr')) {
				$reason = mb_substr($reason, 0, 256);
			} else {
				$reason = substr($reason, 0, 256);
			}

			return array(
				'out_trade_no' => sanitize_text_field($order_id),
				'refund_amount' => number_format((float) $order_amount, 2, '.', ''),
				'refund_reason' => $reason,
				'out_request_no' => sanitize_text_field($request_id),
			);
		}

		public static function submit_refund($order_id, $order_amount, $reason, $request_id)
		{
			$response = Factory::util()->generic()->execute(
				'alipay.trade.refund',
				array(),
				self::refund_request_params($order_id, $order_amount, $reason, $request_id)
			);

			// The locked EasySDK Generic client verifies the gateway signature and
			// throws before returning on verification failure. Attach this marker only
			// after execute() returns so raw or fabricated bodies cannot cross the
			// production success boundary by themselves.
			return array(
				'verified_by_easysdk' => true,
				'response' => $response,
			);
		}

		public static function refund_amounts_match($actual, $expected)
		{
			return is_numeric($actual) && is_numeric($expected) && abs((float) $actual - (float) $expected) < 0.001;
		}

		public static function classify_refund_submit_response($submission, $order_id, $expected_amount)
		{
			if (!is_array($submission) || true !== ($submission['verified_by_easysdk'] ?? false) || !isset($submission['response'])) {
				return 'unknown';
			}

			$result = $submission['response'];
			$code = isset($result->code) ? (string) $result->code : '';
			if ('10000' !== $code) {
				return self::refund_failure_is_uncertain($result) ? 'unknown' : 'rejected';
			}

			$body = isset($result->httpBody) && is_string($result->httpBody) ? json_decode($result->httpBody, true) : null;
			$response = is_array($body) && isset($body['alipay_trade_refund_response']) && is_array($body['alipay_trade_refund_response'])
				? $body['alipay_trade_refund_response']
				: array();
			$response_code = isset($response['code']) ? (string) $response['code'] : '';
			$response_order = isset($response['out_trade_no']) ? sanitize_text_field($response['out_trade_no']) : '';
			$response_amount = isset($response['refund_fee']) ? $response['refund_fee'] : null;

			if ('10000' === $response_code
				&& sanitize_text_field($order_id) === $response_order
				&& self::refund_amounts_match($response_amount, $expected_amount)) {
				return 'confirmed';
			}

			return 'unknown';
		}

		public static function refund_failure_is_uncertain($result)
		{
			$code = isset($result->code) ? strtoupper((string) $result->code) : '';
			$sub_code = isset($result->subCode) ? strtoupper((string) $result->subCode) : '';

			return '' === $code || '20000' === $code || in_array($sub_code, array('ACQ.SYSTEM_ERROR', 'ACQ.DISCORDANT_REPEAT_REQUEST', 'SYSTEM_ERROR', 'ISP.UNKNOW-ERROR', 'ISP.UNKNOWN-ERROR', 'UNKNOWN_ERROR'), true);
		}

		public static function classify_refund_query_response($result, $order_id, $request_id, $expected_amount)
		{
			$code = isset($result->code) ? (string) $result->code : '';
			$has_refund_data = isset($result->refundAmount) && '' !== (string) $result->refundAmount;

			if ('10000' === $code && $has_refund_data) {
				$response_order = isset($result->outTradeNo) ? sanitize_text_field($result->outTradeNo) : '';
				$response_request = isset($result->outRequestNo) ? sanitize_text_field($result->outRequestNo) : '';
				$amount_matches = self::refund_amounts_match($result->refundAmount, $expected_amount);
				if (sanitize_text_field($order_id) === $response_order && sanitize_text_field($request_id) === $response_request && $amount_matches) {
					return 'confirmed';
				}

				return 'unknown';
			}

			// Alipay documents code 10000 with no refund data as safe to retry,
			// provided the original out_request_no and parameters are reused.
			if ('10000' === $code && !$has_refund_data) {
				return 'not_found';
			}

			return 'unknown';
		}

		public static function query_refund_provider($order_id, $request_id)
		{
			return Factory::payment()->common()->queryRefund($order_id, $request_id);
		}

		public static function resolve_uncertain_refund($order_id)
		{
			$uncertain = Npcink_Pay_Refund_Admin_Public::get_refund_uncertain($order_id, '支付宝');
			if (empty($uncertain)) {
				return array('state' => 'none');
			}
			if (!isset($uncertain['request_id']) || self::refund_request_id($order_id) !== sanitize_text_field($uncertain['request_id'])) {
				return array('state' => 'unknown');
			}

			try {
				$result = static::query_refund_provider($order_id, $uncertain['request_id']);
				$state = self::classify_refund_query_response($result, $order_id, $uncertain['request_id'], $uncertain['n_amount']);
				if ('not_found' === $state) {
					return array('state' => 'not_found', 'context' => $uncertain);
				}

				if ('confirmed' !== $state) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep gateway diagnostics out of the AJAX response.
					error_log('Npcink_Pay_Refund Alipay uncertain refund query unresolved: ' . self::gateway_result_log_context($result));
					return array('state' => 'unknown');
				}

				$recorded = Npcink_Pay_Refund_Admin_Public::add_data(
					$uncertain['n_time'],
					$uncertain['n_user'],
					$uncertain['n_amount'],
					$uncertain['n_order'],
					$uncertain['n_reason'],
					$uncertain['n_type']
				);
				if (false === $recorded) {
					return array('state' => 'recording_failed', 'context' => $uncertain);
				}

				return array('state' => 'confirmed', 'context' => $uncertain);
			} catch (Throwable $e) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep gateway diagnostics out of the AJAX response.
				error_log('Npcink_Pay_Refund Alipay uncertain refund query failed: ' . $e->getMessage());
				return array('state' => 'unknown');
			}
		}

		public static function maybe_send_uncertain_refund_result($order_id)
		{
			if (empty(Npcink_Pay_Refund_Admin_Public::get_refund_uncertain($order_id, '支付宝'))) {
				return array('state' => 'none');
			}

			$resolution = self::resolve_uncertain_refund($order_id);
			if ('not_found' === $resolution['state']) {
				return $resolution;
			}

			if ('recording_failed' === $resolution['state']) {
				wp_send_json_error(array(
					'message' => __('支付宝已确认退款成功，但本地退款记录保存失败。订单继续锁定，请修复数据库后再次查询。', 'npcink-pay-refund'),
					'provider_status' => 'SUCCESS',
					'reconciliation_required' => true,
				), 500);
			}

			if ('confirmed' === $resolution['state']) {
				wp_send_json_success(array(
					'html' => self::render_confirmed_refund_html($resolution['context'], __('已退款，状态已通过支付宝查询确认', 'npcink-pay-refund')),
					'provider_status' => 'SUCCESS',
					'reconciled' => true,
				));
			}

			wp_send_json_error(array(
				'message' => __('支付宝退款结果仍无法确认。订单保持锁定，请稍后再次查询，并在支付宝商家平台核对；请勿创建新的退款。', 'npcink-pay-refund'),
				'provider_status' => 'UNKNOWN',
				'reconciliation_required' => true,
			), 409);
		}

		public static function render_confirmed_refund_html($context, $status)
		{
			$table_html = '<table>';
			$table_html .= '<tr><td>订单号：</td><td>' . esc_html($context['n_order']) . '</td></tr>';
			$table_html .= '<tr><td>时间：</td><td>' . esc_html($context['n_time']) . '</td></tr>';
			$table_html .= '<tr><td>金额：</td><td>' . esc_html($context['n_amount']) . '</td></tr>';
			$table_html .= '<tr><td>状态：</td><td><b class="green">' . esc_html($status) . '</b></td></tr>';
			$table_html .= '</table>';

			return $table_html;
		}

        /**
         * Complete a previously failed local audit write without refunding again.
         */
        public static function send_reconciliation_result($order_id)
        {
            $reconciliation = Npcink_Pay_Refund_Admin_Public::get_refund_reconciliation($order_id, '支付宝');
            if (empty($reconciliation)) {
                return;
            }

            if (false === Npcink_Pay_Refund_Admin_Public::retry_refund_reconciliation($order_id, '支付宝')) {
                wp_send_json_error(array(
                    'message' => __('支付宝退款已经成功，但本地退款记录仍无法保存。订单继续锁定，请修复数据库后再次查询该订单。', 'npcink-pay-refund'),
                    'provider_status' => 'SUCCESS',
                    'reconciliation_required' => true,
                ), 500);
            }

            $table_html = '<table>';
            $table_html .= '<tr><td>订单号：</td><td>' . esc_html($reconciliation['n_order']) . '</td></tr>';
            $table_html .= '<tr><td>时间：</td><td>' . esc_html($reconciliation['n_time']) . '</td></tr>';
            $table_html .= '<tr><td>金额：</td><td>' . esc_html($reconciliation['n_amount']) . '</td></tr>';
            $table_html .= '<tr><td>状态：</td><td><b class="green">已退款，本地记录已补记</b></td></tr>';
            $table_html .= '</table>';

            wp_send_json_success(array(
                'html' => $table_html,
                'provider_status' => 'SUCCESS',
                'reconciled' => true,
            ));
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
