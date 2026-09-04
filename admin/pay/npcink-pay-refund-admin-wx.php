<?php

if (!defined('ABSPATH')) {
    exit;
}

//微信支付相关
use GuzzleHttp\Exception\RequestException;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;


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

            //退款状态查询
            add_action('wp_ajax_npcink_pay_refund_wx_refund_status', array(__CLASS__, 'npcink_pay_refund_wx_refund_status'));
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

        public static function pem_block_type($data)
        {
            return preg_match('/-----BEGIN ([^-]+)-----/', trim((string) $data), $matches) ? $matches[1] : '';
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

            $context = array(
                'status' => $response->getStatusCode(),
            );
            $request_id = $response->getHeaderLine('Request-ID');
            if ('' === $request_id) {
                $request_id = $response->getHeaderLine('Wechatpay-Request-Id');
            }
            if ('' !== $request_id) {
                $context['request_id'] = $request_id;
            }

            $body = json_decode((string) $response->getBody(), true);
            if (is_array($body)) {
                foreach (array('code', 'message') as $field) {
                    if (isset($body[$field]) && is_scalar($body[$field])) {
                        $context[$field] = sanitize_text_field((string) $body[$field]);
                    }
                }
            }

            return ' context=' . wp_json_encode($context);
        }

        public static function api_call($callback)
        {
			$result = self::api_call_result($callback);
			return 'success' === $result['state'] ? $result['data'] : null;
		}

		public static function api_call_result($callback)
		{
            if (!self::ensure_client_ready()) {
				return array('state' => 'unknown', 'data' => null, 'http_status' => 0, 'code' => 'CONFIG_ERROR');
            }

            try {
                $response = call_user_func($callback, self::$client);
                $data = json_decode((string) $response->getBody());
				if (!is_object($data)) {
					return array('state' => 'unknown', 'data' => null, 'http_status' => (int) $response->getStatusCode(), 'code' => 'INVALID_RESPONSE');
				}

				return array('state' => 'success', 'data' => $data, 'http_status' => (int) $response->getStatusCode(), 'code' => '');
			} catch (RequestException $e) {
				$response = $e->getResponse();
				$http_status = $response ? (int) $response->getStatusCode() : 0;
				$body_text = $response ? (string) $response->getBody() : '';
				$body = '' !== $body_text ? json_decode($body_text, true) : array();
				$code = is_array($body) && isset($body['code']) && is_scalar($body['code']) ? sanitize_text_field((string) $body['code']) : '';
				$verified = self::error_response_signature_is_valid($response, $body_text);
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep gateway diagnostics out of the AJAX response.
                error_log('Npcink_Pay_Refund WeChat API request failed: ' . $e->getMessage() . self::api_error_log_context($e->getResponse()));
				return array(
					'state' => $verified ? self::classify_api_error($http_status, $code) : 'unknown',
					'data' => null,
					'http_status' => $http_status,
					'code' => $code,
					'verified' => $verified,
				);
            } catch (Exception $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep gateway diagnostics out of the AJAX response.
                error_log('Npcink_Pay_Refund WeChat API request failed: ' . $e->getMessage());
				return array('state' => 'unknown', 'data' => null, 'http_status' => 0, 'code' => '');
            } catch (Throwable $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Keep gateway diagnostics out of the AJAX response.
                error_log('Npcink_Pay_Refund WeChat API request failed: ' . $e->getMessage());
				return array('state' => 'unknown', 'data' => null, 'http_status' => 0, 'code' => '');
            }
        }

		public static function classify_api_error($http_status, $code)
		{
			if (404 === (int) $http_status && 'RESOURCE_NOT_EXISTS' === strtoupper((string) $code)) {
				return 'not_found';
			}

			return 'unknown';
		}

		public static function error_response_signature_is_valid($response, $body)
		{
			if (!$response || empty(self::$merchant_config['platform_key_id']) || empty(self::$merchant_config['platform_public_key'])) {
				return false;
			}

			$nonce = $response->getHeaderLine('Wechatpay-Nonce');
			$serial = $response->getHeaderLine('Wechatpay-Serial');
			$signature = $response->getHeaderLine('Wechatpay-Signature');
			$timestamp = $response->getHeaderLine('Wechatpay-Timestamp');
			if ('' === $nonce || '' === $serial || '' === $signature || '' === $timestamp) {
				return false;
			}
			if ((string) self::$merchant_config['platform_key_id'] !== $serial || !ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
				return false;
			}

			try {
				return Rsa::verify(
					Formatter::response($timestamp, $nonce, (string) $body),
					$signature,
					self::$merchant_config['platform_public_key']
				);
			} catch (Throwable $e) {
				return false;
			}
		}

        public static function request_json($method, $uri, $options = array())
        {
			$result = self::request_json_result($method, $uri, $options);
			return 'success' === $result['state'] ? $result['data'] : null;
		}

		public static function request_json_result($method, $uri, $options = array())
		{
            $method = strtolower($method);
            if (!in_array($method, array('get', 'post'), true)) {
				return array('state' => 'unknown', 'data' => null, 'http_status' => 0, 'code' => 'INVALID_METHOD');
            }

			return self::api_call_result(function ($client) use ($method, $uri, $options) {
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

        public static function get_refund($order_id)
        {
			$result = self::get_refund_result($order_id);
			return 'success' === $result['state'] ? $result['data'] : null;
		}

		public static function get_refund_result($order_id)
		{
			$refund_id = self::refund_request_id($order_id);

			return self::api_call_result(function ($client) use ($refund_id) {
                return $client->v3->refund->domestic->refunds->_out_refund_no_->get(array(
                    'out_refund_no' => $refund_id,
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

            // 获取传递的订单
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified by require_refund_ajax_permission().
            $order_id = isset($_REQUEST['order_id']) ? sanitize_text_field(wp_unslash($_REQUEST['order_id'])) : '';
            if ('' === $order_id) {
                wp_send_json_error(array('message' => __('请输入微信订单号。', 'npcink-pay-refund')), 400);
            }

			$pending_not_found = false;
			$pending = self::get_pending_refund($order_id);
			if (!empty($pending)) {
				$pending_result = self::get_refund_result($order_id);
				if ('success' === $pending_result['state'] && !empty($pending_result['data']->status) && self::refund_response_matches($pending_result['data'], $order_id)) {
					$recorded = self::sync_refund_status($order_id, $pending_result['data']);
					if (false === $recorded) {
						wp_send_json_error(array(
							'message' => self::local_recording_error_message(),
							'provider_status' => 'SUCCESS',
							'reconciliation_required' => true,
						), 500);
					}

					wp_send_json_success(array(
						'html' => self::render_refund_query_html($pending_result['data']),
						'order_id' => $order_id,
						'refund_status' => $pending_result['data']->status,
						'poll' => 'PROCESSING' === $pending_result['data']->status,
					));
				}

				if ('not_found' !== $pending_result['state']) {
					wp_send_json_success(array(
						'html' => self::render_pending_refund_html($order_id, $pending) . '<p class="description">' . esc_html__('微信退款结果暂时无法确认。订单保持锁定，请稍后查询；不要更换商户退款单号。', 'npcink-pay-refund') . '</p>',
						'order_id' => $order_id,
						'refund_status' => 'UNKNOWN',
						'poll' => true,
						'provider_status' => 'UNKNOWN',
					));
				}

				$pending_not_found = true;
			}

            // 发送请求

            $data = self::get_transaction($order_id);

            if (!$data || empty($data->trade_state)) {
                wp_send_json_error(array('message' => __('微信订单查询失败，请检查订单号和鉴权配置。', 'npcink-pay-refund')), 400);
            }

                    $time = self::handle_time(isset($data->success_time) ? $data->success_time : '');
                    $order_amount = self::full_refund_amount($data);
                    $payer_amount = isset($data->amount->payer_total) ? (int) $data->amount->payer_total : $order_amount;
                    $order = isset($data->out_trade_no) ? $data->out_trade_no : $order_id;
                    // echo "failed,resp code = " . $e->getResponse()->getStatusCode() . " return body = " . $e->getResponse()->getBody() . "\n";
                    //根据当前订单状态进行对应操作

                    //准备订单成功数据
                    $table_html = "<table>";
                    $table_html .= "<tr><td>订单号：</td><td>" . esc_html($order) . "</td></tr>";
                    $table_html .= "<tr><td>时间：</td><td>" .  esc_html($time)  . "</td></tr>";
                    $table_html .= "<tr><td>订单总额：</td><td>" . esc_html($order_amount / 100) . "元</td></tr>";
                    if ($payer_amount !== $order_amount) {
                        $table_html .= "<tr><td>用户实付：</td><td>" . esc_html($payer_amount / 100) . "元</td></tr>";
                    }
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


                    //正常成功状态且订单时间在配置的退款窗口内，则添加退款按钮
                    //if (true) {
                    if ($data->trade_state === "SUCCESS" && $order_amount > 0 && Npcink_Pay_Refund_Admin_Public::contrast_time($time)) {

						if ($pending_not_found) {
							$response_html .= '<p class="notice notice-warning inline npcink-pay-refund-refund-notice"><strong>' . esc_html__('上次退款单尚未在微信查询到；再次提交时将复用原商户退款单号和原参数。', 'npcink-pay-refund') . '</strong></p>';
						}
                        $response_html .= '<p>退款原因：<input type="text" id="npcink-pay-refund-wx-reason" placeholder="请输入退款原因"></p>';
                        $response_html .= '<p>点击退款按钮后请稍等进行退款处理</p>';
                        $response_html .= "<button id='wx-order-btn' class='button button-primary refund ' data-order-id='" . esc_attr($order) . "' data-order-amount='" . esc_attr($order_amount) . "'>微信全额退款</button>";
                    } else {
                        if ($data->trade_state === "SUCCESS" && !Npcink_Pay_Refund_Admin_Public::contrast_time($time)) {
                            /* translators: %d: configured refund window in days. */
                            $response_html .= '<h3>' . esc_html(sprintf(__('订单时间超过 %d 天，无法使用本功能退款，请联系管理员操作', 'npcink-pay-refund'), Npcink_Pay_Refund_Admin_Public::refund_window_days())) . '</h3>';
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

			if ('' === $order_id) {
				wp_send_json_error(array('message' => __('订单号不能为空。', 'npcink-pay-refund')), 400);
            }

			$pending = self::get_pending_refund($order_id);
			$retrying_pending = false;
			if (!empty($pending)) {
				$pending_result = self::get_refund_result($order_id);
				if ('success' === $pending_result['state'] && !empty($pending_result['data']->status) && self::refund_response_matches($pending_result['data'], $order_id)) {
					$recorded = self::sync_refund_status($order_id, $pending_result['data']);
					if (false === $recorded) {
						wp_send_json_error(array(
							'message' => self::local_recording_error_message(),
							'provider_status' => 'SUCCESS',
							'reconciliation_required' => true,
						), 500);
					}

					wp_send_json_success(array(
						'html' => self::render_refund_query_html($pending_result['data']),
						'order_id' => $order_id,
						'refund_status' => $pending_result['data']->status,
						'poll' => 'PROCESSING' === $pending_result['data']->status,
					));
				}

				if ('not_found' !== $pending_result['state']) {
					wp_send_json_success(array(
						'html' => self::render_pending_refund_html($order_id, $pending) . '<p class="description">' . esc_html__('微信退款结果暂时无法确认。已先查询原商户退款单号且未得到明确结果，本次不会再次提交。', 'npcink-pay-refund') . '</p>',
						'order_id' => $order_id,
						'refund_status' => 'UNKNOWN',
						'poll' => true,
						'provider_status' => 'UNKNOWN',
					));
				}

				$retrying_pending = true;
				if (!empty($pending['reason'])) {
					$reason = $pending['reason'];
				}
			}

			$api_reason = self::format_refund_reason($reason);
			if ('' === $api_reason) {
				wp_send_json_error(array('message' => __('退款原因不能为空。', 'npcink-pay-refund')), 400);
			}

            $order_data = self::get_transaction($order_id);
            if (!$order_data || empty($order_data->trade_state)) {
                wp_send_json_error(array('message' => __('订单查询失败，未执行退款。', 'npcink-pay-refund')), 400);
            }

            $order_time = self::handle_time(isset($order_data->success_time) ? $order_data->success_time : '');
            if ('SUCCESS' !== $order_data->trade_state || !Npcink_Pay_Refund_Admin_Public::contrast_time($order_time)) {
                wp_send_json_error(array('message' => __('该订单当前状态或时间窗口不可退款。', 'npcink-pay-refund')), 400);
            }

            $order_amount = self::full_refund_amount($order_data);
            $payer_amount = isset($order_data->amount->payer_total) ? (int) $order_data->amount->payer_total : $order_amount;
            if ($order_amount <= 0) {
                wp_send_json_error(array('message' => __('订单金额无效，未执行退款。', 'npcink-pay-refund')), 400);
            }

            // 准备退款订单号
			$order_refund_id = self::refund_request_id($order_id);
			if ($retrying_pending && !empty($pending['request_id']) && !hash_equals($order_refund_id, sanitize_text_field($pending['request_id']))) {
				wp_send_json_error(array('message' => __('保存的微信退款请求号与当前订单不一致，订单继续锁定。请在微信商户平台核对后再处理。', 'npcink-pay-refund')), 409);
			}
			if ($retrying_pending && !empty($pending['request_amount']) && (int) $pending['request_amount'] !== $order_amount) {
				wp_send_json_error(array('message' => __('订单金额与上次退款请求不一致，订单继续锁定。请在微信商户平台核对后再处理。', 'npcink-pay-refund')), 409);
            }

			if (!$retrying_pending && !Npcink_Pay_Refund_Admin_Public::claim_refund($order_id, '微信')) {
                wp_send_json_error(array('message' => __('该订单已提交退款或正在处理中，请勿重复操作。', 'npcink-pay-refund')), 409);
            }

			$current_user = wp_get_current_user();
			$user = $retrying_pending && !empty($pending['user']) ? $pending['user'] : $current_user->display_name;
			$amount = $retrying_pending && isset($pending['amount']) ? (float) $pending['amount'] : $payer_amount / 100;
			$time_now = $retrying_pending && !empty($pending['time']) ? $pending['time'] : wp_date('Y-m-d H:i', current_time('timestamp'));
			if (!self::save_pending_refund($order_id, $user, $amount, $reason, $time_now, $order_amount, true)) {
				if (!$retrying_pending) {
					Npcink_Pay_Refund_Admin_Public::release_refund_claim($order_id, '微信');
				}
				wp_send_json_error(array('message' => __('无法保存微信退款请求状态，未向微信提交退款。请检查数据库后重试。', 'npcink-pay-refund')), 500);
			}
			$pending = self::get_pending_refund($order_id);

			// A retry reaches this POST only after the query returned RESOURCE_NOT_EXISTS.
			$submit_result = self::request_json_result(
                'POST',
                'v3/refund/domestic/refunds',
                self::refund_request_options($order_id, $order_refund_id, $api_reason, $order_amount)
            );

			$data = $submit_result['data'];
			if ('success' !== $submit_result['state'] || !$data || empty($data->status) || !self::refund_response_matches($data, $order_id)) {
				wp_send_json_success(array(
					'html' => self::render_pending_refund_html($order_id, $pending) . '<p class="description">' . esc_html__('微信退款请求结果暂时无法确认。订单和原商户退款单号已保留，系统将只查询状态，不会盲目重复提交。', 'npcink-pay-refund') . '</p>',
					'order_id' => $order_id,
					'refund_status' => 'UNKNOWN',
					'poll' => true,
					'provider_status' => 'UNKNOWN',
				));
            }

			$order = $order_id;
            switch ($data->status) {
                case "PROCESSING": // 退款处理中，进行退款查询
                    $response_html = self::render_refund_query_html($data);
                    $response_html .= '<p class="description">' . esc_html__('微信退款已受理，状态可能需要几秒同步；请稍后再次点击查询刷新结果。', 'npcink-pay-refund') . '</p>';
                    break;
                case "SUCCESS": // 退款成功，进行退款查询
					$recorded = self::record_successful_refund($order, $data);
                    $response_html = self::render_refund_query_html($data);
                    if (false === $recorded) {
                        wp_send_json_error(array(
                            'message' => self::local_recording_error_message(),
                            'provider_status' => 'SUCCESS',
                            'reconciliation_required' => true,
                        ), 500);
                    }

                    break;
                case "CLOSED": // 退款关闭
					if (!self::advance_refund_request_id($order_id)) {
						wp_send_json_error(array('message' => __('微信退款单已关闭，但新的退款尝试号保存失败。订单继续锁定，请检查数据库后再处理。', 'npcink-pay-refund')), 500);
					}
					self::clear_pending_refund($order_id);
                    Npcink_Pay_Refund_Admin_Public::release_refund_claim($order_id, '微信');
                    wp_send_json_error(array('message' => __('退款关闭', 'npcink-pay-refund')), 400);
                    break;
                case "ABNORMAL": // 退款异常
					if (!self::advance_refund_request_id($order_id)) {
						wp_send_json_error(array('message' => __('微信退款状态异常，但新的退款尝试号保存失败。订单继续锁定，请检查数据库后再处理。', 'npcink-pay-refund')), 500);
					}
					self::clear_pending_refund($order_id);
                    Npcink_Pay_Refund_Admin_Public::release_refund_claim($order_id, '微信');
                    wp_send_json_error(array('message' => __('退款异常，请联系管理员。', 'npcink-pay-refund')), 400);
                    break;
                default:
					wp_send_json_success(array(
						'html' => self::render_pending_refund_html($order_id, $pending) . '<p class="description">' . esc_html__('微信返回了未识别的退款状态。订单继续锁定，请在商户平台核对。', 'npcink-pay-refund') . '</p>',
						'order_id' => $order_id,
						'refund_status' => 'UNKNOWN',
						'poll' => true,
						'provider_status' => 'UNKNOWN',
					));
            }

            wp_send_json_success(array(
                'html' => $response_html,
                'order_id' => $order,
                'refund_status' => isset($data->status) ? $data->status : '',
                'poll' => 'PROCESSING' === $data->status,
            ));
        }

        public static function npcink_pay_refund_wx_refund_status()
        {
            Npcink_Pay_Refund_Admin_Authority::require_refund_ajax_permission();

            if (!self::ensure_client_ready()) {
                wp_send_json_error(array('message' => self::api_error_message()), 400);
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by require_refund_ajax_permission().
            $order_id = isset($_POST['order_id']) ? sanitize_text_field(wp_unslash($_POST['order_id'])) : '';
            if ('' === $order_id) {
                wp_send_json_error(array('message' => __('订单号不能为空。', 'npcink-pay-refund')), 400);
            }

			$query_result = self::get_refund_result($order_id);
			$data = $query_result['data'];
			if ('success' !== $query_result['state'] || !$data || empty($data->status) || !self::refund_response_matches($data, $order_id)) {
				$pending = self::get_pending_refund($order_id);
				if (!empty($pending)) {
					wp_send_json_success(array(
						'html' => self::render_pending_refund_html($order_id, $pending),
						'order_id' => $order_id,
						'refund_status' => 'UNKNOWN',
						'poll' => true,
						'provider_status' => 'UNKNOWN',
					));
				}

				wp_send_json_error(array('message' => __('退款状态查询失败，请稍后手动查询。', 'npcink-pay-refund')), 400);
            }

			$order = $order_id;

			$recorded = self::sync_refund_status($order, $data);
			if (false === $recorded) {
				$message = in_array($data->status, array('CLOSED', 'ABNORMAL'), true)
					? __('微信退款已进入终态，但新的退款尝试号保存失败。订单继续锁定，请检查数据库后再处理。', 'npcink-pay-refund')
					: self::local_recording_error_message();
                wp_send_json_error(array(
                    'message' => $message,
                    'provider_status' => sanitize_text_field($data->status),
                    'reconciliation_required' => true,
                ), 500);
            }

            wp_send_json_success(array(
                'html' => self::render_refund_query_html($data),
                'order_id' => $order,
                'refund_status' => $data->status,
                'poll' => 'PROCESSING' === $data->status,
            ));
        }

        public static function format_refund_reason($reason)
        {
            $reason = trim(preg_replace('/\s+/', ' ', (string) $reason));
            if (function_exists('mb_substr')) {
                return mb_substr($reason, 0, 80);
            }

            return substr($reason, 0, 80);
        }

        public static function full_refund_amount($order_data)
        {
            if (!is_object($order_data) || !isset($order_data->amount) || !is_object($order_data->amount) || !isset($order_data->amount->total)) {
                return 0;
            }

            return max(0, (int) $order_data->amount->total);
        }

		public static function refund_request_id($order_id)
		{
			$order_id = sanitize_text_field($order_id);
			$legacy_id = $order_id . '-refund';
			$attempt = (int) get_option(self::refund_attempt_option_name($order_id), 0);
			if ($attempt > 0) {
				$attempted_id = $legacy_id . '-' . $attempt;
				if (strlen($attempted_id) <= 64 && preg_match('/^[0-9A-Za-z_\-|*@]+$/D', $attempted_id)) {
					return $attempted_id;
				}
				return 'npcink-refund-' . substr(hash('sha256', $order_id . '|' . $attempt), 0, 40);
			}
			if (strlen($legacy_id) <= 64 && preg_match('/^[0-9A-Za-z_\-|*@]+$/D', $legacy_id)) {
				return $legacy_id;
			}

			return 'npcink-refund-' . substr(hash('sha256', $order_id), 0, 40);
		}

		public static function refund_attempt_option_name($order_id)
		{
			return 'npcink_pay_refund_attempt_wx_' . md5(sanitize_text_field($order_id));
		}

		public static function advance_refund_request_id($order_id)
		{
			$option = self::refund_attempt_option_name($order_id);
			$attempt = (int) get_option($option, 0) + 1;
			return update_option($option, $attempt, false);
		}

		public static function refund_response_matches($data, $order_id)
		{
			if (!is_object($data) || !isset($data->out_trade_no) || !isset($data->out_refund_no)) {
				return false;
			}

			return sanitize_text_field($order_id) === sanitize_text_field($data->out_trade_no)
				&& self::refund_request_id($order_id) === sanitize_text_field($data->out_refund_no);
		}

        public static function refund_request_options($order_id, $order_refund_id, $reason, $order_amount)
        {
            $order_amount = max(0, (int) $order_amount);

            return array(
                'json' => array(
                    'out_trade_no' => sanitize_text_field($order_id),
                    'out_refund_no' => sanitize_text_field($order_refund_id),
                    'reason' => self::format_refund_reason($reason),
                    'amount' => array(
                        'refund' => $order_amount,
                        'total' => $order_amount,
                        'currency' => 'CNY',
                    ),
                ),
                'headers' => array('Accept' => 'application/json'),
            );
        }

        public static function local_recording_error_message()
        {
            return __('微信退款已成功，但本地退款记录保存失败。订单已锁定等待管理员对账，请勿重复退款。', 'npcink-pay-refund');
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
				if (function_exists('npcink_pay_refund_load_vendor')) {
					npcink_pay_refund_load_vendor();
				}
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
			if (function_exists('npcink_pay_refund_load_vendor')) {
				npcink_pay_refund_load_vendor();
			}
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
                $merchant_private_key = trim((string) Npcink_Pay_Refund_Admin::get_options($config, 'cert_key'));
                $platform_public_key = trim((string) Npcink_Pay_Refund_Admin::get_options($config, 'platform_public_key'));
                $parse_error = '';

                if ('CERTIFICATE' === self::pem_block_type($merchant_private_key)) {
                    $parse_error = __('商户私钥字段当前像是商户 API 证书（apiclient_cert.pem），请填写 apiclient_key.pem 私钥内容。', 'npcink-pay-refund');
                } elseif (false === @openssl_pkey_get_private(self::format_private_key($merchant_private_key))) {
                    $parse_error = __('商户私钥无法解析，请检查是否为 apiclient_key.pem 的完整 PEM 内容。', 'npcink-pay-refund');
                } elseif (false === @openssl_pkey_get_public(self::format_public_key($platform_public_key))) {
                    $parse_error = __('微信支付公钥 / 平台证书无法解析，请检查 PEM 内容是否完整。', 'npcink-pay-refund');
                }

                if ('' !== $parse_error) {
                    $ok = false;
                    $items[] = array(
                        'status' => 'error',
                        'label' => __('密钥解析', 'npcink-pay-refund'),
                        'message' => $parse_error,
                    );
                }
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
            $data = self::get_refund($id);

            if (!$data) {
                return esc_html__('退款查询失败，请稍后重试。', 'npcink-pay-refund');
            }

            $order = isset($data->out_trade_no) ? $data->out_trade_no : $id;
            $recorded = self::sync_refund_status($order, $data);

            $html = self::render_refund_query_html($data);
            if (false === $recorded) {
                $html .= '<p class="notice notice-error inline npcink-pay-refund-refund-notice"><strong>' . esc_html(self::local_recording_error_message()) . '</strong></p>';
            }

            return $html;
        }

        public static function render_refund_query_html($data)
        {
            $refund_time = isset($data->success_time) ? self::handle_time($data->success_time) : '';
            $refund_amount = isset($data->amount->payer_refund) ? $data->amount->payer_refund / 100 : 0;
            $status = isset($data->status) ? $data->status : '';
            $status_view = self::refund_status_view($status);
            $refund_id = isset($data->out_refund_no) ? $data->out_refund_no : '';
            $order_id = isset($data->out_trade_no) ? $data->out_trade_no : '';

            $html = '<div class="npcink-pay-refund-result-card npcink-pay-refund-result-' . esc_attr($status_view['class']) . '">';
            $html .= '<div class="npcink-pay-refund-result-header">';
            $html .= '<div>';
            $html .= '<span class="npcink-pay-refund-result-badge">' . esc_html($status_view['label']) . '</span>';
            $html .= '<h3>' . esc_html($status_view['title']) . '</h3>';
            $html .= '<p>' . esc_html($status_view['description']) . '</p>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<dl class="npcink-pay-refund-result-details">';
            $html .= self::render_refund_detail(__('商户订单号', 'npcink-pay-refund'), $order_id);
            $html .= self::render_refund_detail(__('商户退款单号', 'npcink-pay-refund'), $refund_id);
            $html .= self::render_refund_detail(__('退款金额', 'npcink-pay-refund'), '' !== (string) $refund_amount ? $refund_amount . __('元', 'npcink-pay-refund') : '');
            $html .= self::render_refund_detail(__('退款时间', 'npcink-pay-refund'), $refund_time);
            $html .= self::render_refund_detail(__('通道状态', 'npcink-pay-refund'), $status_view['title']);
            $html .= '</dl>';
            $html .= '</div>';

            return $html;
        }

        public static function render_refund_detail($label, $value)
        {
            $display_value = '' === (string) $value ? __('暂无', 'npcink-pay-refund') : (string) $value;

            return '<div class="npcink-pay-refund-result-detail"><dt>' . esc_html($label) . '</dt><dd>' . esc_html($display_value) . '</dd></div>';
        }

		public static function render_pending_refund_html($order_id, $pending)
		{
			$data = (object) array(
				'status' => 'PROCESSING',
				'out_trade_no' => sanitize_text_field($order_id),
				'out_refund_no' => self::refund_request_id($order_id),
                'amount' => (object) array(
                    'payer_refund' => isset($pending['amount']) ? (float) $pending['amount'] * 100 : 0,
                ),
            );

            return self::render_refund_query_html($data);
        }

        public static function pending_refund_option_name($order_id)
        {
            return 'npcink_pay_refund_pending_wx_' . md5(sanitize_text_field($order_id));
        }

		public static function save_pending_refund($order_id, $user, $amount, $reason, $time, $request_amount = 0, $increment_attempt = false)
        {
			$existing = self::get_pending_refund($order_id);
			if (!empty($existing)) {
				$user = isset($existing['user']) ? $existing['user'] : $user;
				$amount = isset($existing['amount']) ? $existing['amount'] : $amount;
				$reason = isset($existing['reason']) ? $existing['reason'] : $reason;
				$time = isset($existing['time']) ? $existing['time'] : $time;
				$request_amount = !empty($existing['request_amount']) ? $existing['request_amount'] : $request_amount;
			}

			return update_option(
                self::pending_refund_option_name($order_id),
                array(
					'n_order' => sanitize_text_field($order_id),
					'n_user_id' => isset($existing['n_user_id']) ? absint($existing['n_user_id']) : (function_exists('get_current_user_id') ? get_current_user_id() : 0),
                    'user' => sanitize_text_field($user),
                    'amount' => (float) $amount,
                    'reason' => Npcink_Pay_Refund_Admin::sanitize_textarea_value($reason),
                    'time' => sanitize_text_field($time),
					'request_id' => self::refund_request_id($order_id),
					'request_amount' => max(0, (int) $request_amount),
					'started_at' => isset($existing['started_at']) ? (int) $existing['started_at'] : time(),
					'last_submit_at' => $increment_attempt ? time() : (isset($existing['last_submit_at']) ? (int) $existing['last_submit_at'] : 0),
					'submit_attempts' => (isset($existing['submit_attempts']) ? (int) $existing['submit_attempts'] : 0) + ($increment_attempt ? 1 : 0),
                ),
                false
            );
        }

        public static function get_pending_refund($order_id)
        {
            $pending = get_option(self::pending_refund_option_name($order_id), array());
            return is_array($pending) ? $pending : array();
        }

        public static function clear_pending_refund($order_id)
        {
            delete_option(self::pending_refund_option_name($order_id));
        }

        public static function record_successful_refund($order_id, $data, $fallback = array())
        {
            $stored_pending = self::get_pending_refund($order_id);
            if (empty($stored_pending) && empty($fallback)) {
                return false;
            }

            $pending = array_merge($stored_pending, $fallback);
            $time = isset($data->success_time) ? self::handle_time($data->success_time) : '';
            if ('' === $time) {
                $time = isset($pending['time']) ? $pending['time'] : wp_date('Y-m-d H:i', current_time('timestamp'));
            }

            $amount = isset($data->amount->payer_refund) ? $data->amount->payer_refund / 100 : 0;
            if ($amount <= 0 && isset($pending['amount'])) {
                $amount = (float) $pending['amount'];
            }

            $user = isset($pending['user']) ? $pending['user'] : '';
            $reason = isset($pending['reason']) ? $pending['reason'] : '';
            if ('' === $user || $amount <= 0) {
                return false;
            }

			$recorded = Npcink_Pay_Refund_Admin_Public::add_data($time, $user, $amount, $order_id, $reason, '微信', isset($pending['n_user_id']) ? $pending['n_user_id'] : 0);
            if (false === $recorded) {
                self::save_pending_refund($order_id, $user, $amount, $reason, $time);
                return false;
            }

            self::clear_pending_refund($order_id);
            return true;
        }

        public static function sync_refund_status($order_id, $data)
        {
            $pending = self::get_pending_refund($order_id);
            if (empty($pending)) {
                return true;
            }

            if ('SUCCESS' === $data->status) {
                return self::record_successful_refund($order_id, $data);
            } elseif (in_array($data->status, array('CLOSED', 'ABNORMAL'), true)) {
				if (!self::advance_refund_request_id($order_id)) {
					return false;
				}
                self::clear_pending_refund($order_id);
                Npcink_Pay_Refund_Admin_Public::release_refund_claim($order_id, '微信');
            }

            return true;
        }

        public static function refund_status_html($status)
        {
            $status_view = self::refund_status_view($status);
            return '<span class="npcink-pay-refund-result-badge npcink-pay-refund-result-badge-' . esc_attr($status_view['class']) . '">' . esc_html($status_view['label']) . '</span>';
        }

        public static function refund_status_view($status)
        {
            switch ($status) {
                case 'SUCCESS':
                    return array(
                        'class' => 'success',
                        'label' => __('成功', 'npcink-pay-refund'),
                        'title' => __('退款成功', 'npcink-pay-refund'),
                        'description' => __('微信支付已确认退款完成，可在微信支付商户平台核对到账状态。', 'npcink-pay-refund'),
                    );
                case 'PROCESSING':
                    return array(
                        'class' => 'processing',
                        'label' => __('处理中', 'npcink-pay-refund'),
                        'title' => __('退款处理中', 'npcink-pay-refund'),
                        'description' => __('微信支付已受理退款，状态通常会在稍后同步；可等待自动同步或稍后手动查询。', 'npcink-pay-refund'),
                    );
                case 'CLOSED':
                    return array(
                        'class' => 'closed',
                        'label' => __('已关闭', 'npcink-pay-refund'),
                        'title' => __('退款已关闭', 'npcink-pay-refund'),
                        'description' => __('微信支付返回退款单已关闭，通常不能继续通过同一退款单处理。', 'npcink-pay-refund'),
                    );
                case 'ABNORMAL':
                    return array(
                        'class' => 'error',
                        'label' => __('异常', 'npcink-pay-refund'),
                        'title' => __('退款异常', 'npcink-pay-refund'),
                        'description' => __('微信支付返回退款异常，请到微信支付商户平台查看原因并处理。', 'npcink-pay-refund'),
                    );
                default:
                    return array(
                        'class' => 'neutral',
                        'label' => __('未知', 'npcink-pay-refund'),
                        'title' => __('退款状态未知', 'npcink-pay-refund'),
                        'description' => __('暂时无法确认退款状态，请稍后再次查询。', 'npcink-pay-refund'),
                    );
            }
        }

        /**
         * 处理拿到的私钥，转为正确的格式
         */
        public static function format_private_key($data)
        {
            $pem_data = $data;
            $pem = trim($pem_data);
            if (false !== strpos($pem, '-----BEGIN ')) {
                return $pem;
            }

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
			$timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
			$datetime->setTimezone($timezone);
            return $datetime->format('Y-m-d H:i:s');
            //首先创建了一个 DateTime 对象，将时间字符串作为构造函数的参数传入。然后使用 setTimezone() 方法将时区从 UTC 转换为 WordPress 设置的时区。最后调用 format() 方法将时间格式化成指定格式的时间字符串。
        }
    } //end
}
