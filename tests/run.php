<?php

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/admin/pay/npcink-pay-refund-admin-public.php';
require dirname(__DIR__) . '/admin/pay/npcink-pay-refund-admin-wx.php';
require dirname(__DIR__) . '/admin/pay/npcink-pay-refund-admin-zfb.php';

class Npcink_Test_Alipay_Refund extends Npcink_Pay_Refund_Admin_Zfb
{
	public static $query_result;
	public static $query_calls = 0;

	public static function reset_provider()
	{
		self::$query_result = null;
		self::$query_calls = 0;
	}

	public static function query_refund_provider($order_id, $request_id)
	{
		++self::$query_calls;
		return self::$query_result;
	}
}

$passed = 0;
$failed = 0;

function npcink_test_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '; expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function npcink_test_assert_true($actual, $message)
{
    npcink_test_assert_same(true, (bool) $actual, $message);
}

function npcink_test_case($name, $callback)
{
    global $passed, $failed;

    try {
        npcink_test_reset_state();
        $callback();
        ++$passed;
        echo "PASS: {$name}\n";
    } catch (Throwable $error) {
        ++$failed;
        fwrite(STDERR, "FAIL: {$name}: {$error->getMessage()}\n");
    }
}

npcink_test_case('published PHP and version metadata stay synchronized', function () {
    $root = dirname(__DIR__);
    $composer = json_decode(file_get_contents($root . '/composer.json'), true);
    npcink_test_assert_same('>=8.1', $composer['require']['php'], 'Composer must declare the runtime PHP floor');

    $plugin = file_get_contents($root . '/npcink-pay-refund.php');
    $readme = file_get_contents($root . '/readme.txt');
    npcink_test_assert_true((bool) preg_match('/Requires PHP:\s+8\.1/', $plugin), 'Plugin header must require PHP 8.1');
    npcink_test_assert_true((bool) preg_match('/^Requires PHP:\s*8\.1$/m', $readme), 'WordPress.org readme must require PHP 8.1');

	$header_match = preg_match('/^ \* Version:\s*([0-9A-Za-z.-]+)\s*$/m', $plugin, $header_matches);
	npcink_test_assert_same(1, $header_match, 'Plugin header must publish a version');
	$published_version = $header_matches[1];
	$quoted_version = preg_quote($published_version, '/');

	npcink_test_assert_true(
		(bool) preg_match("/NPCINK_PAY_REFUND_VERSION', '{$quoted_version}'/", $plugin),
		'Runtime constant must match the plugin header version'
	);
	npcink_test_assert_true(
		(bool) preg_match("/^Stable tag:\\s*{$quoted_version}$/m", $readme),
		'WordPress.org stable tag must match the plugin header version'
	);
	$checklist = file_get_contents($root . '/docs/REFUND-INTEGRATION-CHECKLIST.md');
	npcink_test_assert_true(false !== strpos($checklist, 'build/npcink-pay-refund-' . $published_version . '.zip'), 'Integration checklist must target the published package version');
	npcink_test_assert_true(false === strpos($checklist, 'only access the configured admin pages'), 'Integration checklist must not describe the removed admin-page restriction');
	npcink_test_assert_true(false === strpos($readme, 'allowed admin pages'), 'Published readme must not describe the removed admin-page restriction');
});

npcink_test_case('order numbers are passed to providers without legacy suffix rewriting', function () {
	$root = dirname(__DIR__);
	$script = file_get_contents($root . '/admin/js/npcink-pay-refund-admin.js');
	npcink_test_assert_true(false === strpos($script, 'function trimHyphen'), 'Provider order inputs must not use the legacy suffix normalizer');
	npcink_test_assert_true(false !== strpos($script, 'param: $.trim($("#npcink-pay-refund-zfb-input").val())'), 'Alipay query must pass the entered order number');
	npcink_test_assert_true(false !== strpos($script, 'order_id: $.trim($("#npcink-pay-refund-wx-input").val())'), 'WeChat query must pass the entered order number');
});

npcink_test_case('WeChat full refund uses original total for coupon orders', function () {
    $coupon_order = (object) array(
        'amount' => (object) array(
            'total' => 1000,
            'payer_total' => 700,
        ),
    );
    npcink_test_assert_same(1000, Npcink_Pay_Refund_Admin_Wx::full_refund_amount($coupon_order), 'Full refund must use the original order total');

    $request = Npcink_Pay_Refund_Admin_Wx::refund_request_options('coupon-order', 'coupon-order-refund', 'Coupon refund', 1000);
    npcink_test_assert_same(1000, $request['json']['amount']['refund'], 'Refund request must send the original order total as amount.refund');
    npcink_test_assert_same(1000, $request['json']['amount']['total'], 'Refund request must send the original order total as amount.total');

    $missing_total = (object) array('amount' => (object) array('payer_total' => 700));
    npcink_test_assert_same(0, Npcink_Pay_Refund_Admin_Wx::full_refund_amount($missing_total), 'Missing original total must fail closed');
});

npcink_test_case('Alipay configuration leaves notify URL unset', function () {
    Npcink_Pay_Refund_Admin::$configs['zfb'] = array(
        'appid' => 'test-app-id',
        'private_key' => 'test-private-key',
        'public_key' => 'test-public-key',
    );

    $options = Npcink_Pay_Refund_Admin_Zfb::getOptions();
    npcink_test_assert_same(null, $options->notifyUrl, 'No placeholder callback URL may enter signed Alipay requests');
});

npcink_test_case('Alipay refund retries use one explicit request id and query first', function () {
	$order = 'alipay-order-1';
	$request_id = Npcink_Pay_Refund_Admin_Zfb::refund_request_id($order);
	npcink_test_assert_same($request_id, Npcink_Pay_Refund_Admin_Zfb::refund_request_id($order), 'Alipay request id must be stable across retries');
	npcink_test_assert_true(strlen($request_id) <= 64, 'Alipay request id must fit the provider limit');

	$params = Npcink_Pay_Refund_Admin_Zfb::refund_request_params($order, '7.00', 'Customer request', $request_id);
	npcink_test_assert_same($request_id, $params['out_request_no'], 'Refund request must explicitly send out_request_no');
	npcink_test_assert_same($order, $params['out_trade_no'], 'Refund request must retain the original merchant order');
	npcink_test_assert_true(method_exists('Alipay\\EasySDK\\Payment\\Common\\Client', 'queryRefund'), 'Packaged Alipay EasySDK must provide queryRefund');
	npcink_test_assert_true(method_exists('Alipay\\EasySDK\\Kernel\\Factory', 'util') && method_exists('Alipay\\EasySDK\\Kernel\\Util', 'generic') && method_exists('Alipay\\EasySDK\\Util\\Generic\\Client', 'execute'), 'Packaged Alipay EasySDK must provide the generic verified execution path');

	$submit_response = (object) array(
			'code' => '10000',
			'httpBody' => wp_json_encode(array(
				'alipay_trade_refund_response' => array(
					'code' => '10000',
					'out_trade_no' => $order,
					'refund_fee' => '7.00',
				),
			)),
	);
	$submit = array(
		'verified_by_easysdk' => true,
		'response' => $submit_response,
	);
	npcink_test_assert_same('confirmed', Npcink_Pay_Refund_Admin_Zfb::classify_refund_submit_response($submit, $order, '7.00'), 'EasySDK-verified submit body must match order and amount before local success');
	npcink_test_assert_same('unknown', Npcink_Pay_Refund_Admin_Zfb::classify_refund_submit_response($submit_response, $order, '7.00'), 'An unwrapped raw body must not cross the verified production boundary');
	$wrong_submit_response = clone $submit_response;
	$wrong_submit_response->httpBody = str_replace($order, 'another-order', $submit_response->httpBody);
	$wrong_submit = array('verified_by_easysdk' => true, 'response' => $wrong_submit_response);
	npcink_test_assert_same('unknown', Npcink_Pay_Refund_Admin_Zfb::classify_refund_submit_response($wrong_submit, $order, '7.00'), 'Mismatched submit response must remain uncertain');
	npcink_test_assert_same('unknown', Npcink_Pay_Refund_Admin_Zfb::classify_refund_submit_response(array('verified_by_easysdk' => true, 'response' => (object) array('code' => '10000')), $order, '7.00'), 'Code-only submit response must not clear the lock');
	npcink_test_assert_same('rejected', Npcink_Pay_Refund_Admin_Zfb::classify_refund_submit_response(array('verified_by_easysdk' => true, 'response' => (object) array('code' => '40004', 'subCode' => 'ACQ.INVALID_PARAMETER')), $order, '7.00'), 'Definitive validation rejection may release the claim');

	$confirmed = (object) array(
		'code' => '10000',
		'outTradeNo' => $order,
		'outRequestNo' => $request_id,
		'refundAmount' => '7.00',
	);
	npcink_test_assert_same('confirmed', Npcink_Pay_Refund_Admin_Zfb::classify_refund_query_response($confirmed, $order, $request_id, '7.00'), 'Matching refund query data must confirm the refund');

	$wrong_request = clone $confirmed;
	$wrong_request->outRequestNo = 'another-refund';
	npcink_test_assert_same('unknown', Npcink_Pay_Refund_Admin_Zfb::classify_refund_query_response($wrong_request, $order, $request_id, '7.00'), 'Mismatched request id must fail closed');

	$not_found = (object) array('code' => '10000');
	npcink_test_assert_same('not_found', Npcink_Pay_Refund_Admin_Zfb::classify_refund_query_response($not_found, $order, $request_id, '7.00'), 'Successful query with no refund data may permit a same-id retry');
	$trade_not_found = (object) array('code' => '40004', 'subCode' => 'ACQ.TRADE_NOT_EXIST');
	npcink_test_assert_same('unknown', Npcink_Pay_Refund_Admin_Zfb::classify_refund_query_response($trade_not_found, $order, $request_id, '7.00'), 'A failed query must not independently authorize retry');
	npcink_test_assert_true(Npcink_Pay_Refund_Admin_Zfb::refund_failure_is_uncertain((object) array('code' => '20000')), 'Provider system uncertainty must preserve state');
	npcink_test_assert_true(Npcink_Pay_Refund_Admin_Zfb::refund_failure_is_uncertain((object) array('code' => '40004', 'subCode' => 'ISP.UNKNOWN-ERROR')), 'Unknown provider spelling variants must preserve state');
	npcink_test_assert_same(false, Npcink_Pay_Refund_Admin_Zfb::refund_failure_is_uncertain((object) array('code' => '40004', 'subCode' => 'ACQ.INVALID_PARAMETER')), 'Definitive validation failure may release the claim');
});

npcink_test_case('Alipay uncertain resolver queries once and preserves or clears state deterministically', function () {
	global $wpdb;
	$wpdb = new Npcink_Test_Wpdb();
	$order = 'alipay-order-state-machine';
	$request_id = Npcink_Pay_Refund_Admin_Zfb::refund_request_id($order);
	$context = array(
		'n_time' => '2026-07-19 10:00',
		'n_user' => 'Operator',
		'n_amount' => 7.0,
		'n_order' => $order,
		'n_reason' => 'Customer request',
		'n_type' => '支付宝',
		'request_id' => $request_id,
	);

	npcink_test_assert_true(Npcink_Pay_Refund_Admin_Public::claim_refund($order, '支付宝'), 'Test must acquire the refund lock');
	npcink_test_assert_true(Npcink_Pay_Refund_Admin_Public::save_refund_uncertain($context), 'Test must persist uncertain context');
	Npcink_Test_Alipay_Refund::$query_result = (object) array('code' => '10000');
	$resolution = Npcink_Test_Alipay_Refund::resolve_uncertain_refund($order);
	npcink_test_assert_same('not_found', $resolution['state'], 'A successful query with no refund data may retry with the same id');
	npcink_test_assert_same(1, Npcink_Test_Alipay_Refund::$query_calls, 'Resolver must issue exactly one provider query');
	npcink_test_assert_true(!empty(Npcink_Pay_Refund_Admin_Public::get_refund_uncertain($order, '支付宝')), 'Not-found resolution must keep original retry context');
	npcink_test_assert_same(false, Npcink_Pay_Refund_Admin_Public::claim_refund($order, '支付宝'), 'Uncertain marker must continue to block a new claim');

	Npcink_Test_Alipay_Refund::$query_result = (object) array(
		'code' => '10000',
		'outTradeNo' => $order,
		'outRequestNo' => $request_id,
		'refundAmount' => '7.00',
	);
	$resolution = Npcink_Test_Alipay_Refund::resolve_uncertain_refund($order);
	npcink_test_assert_same('confirmed', $resolution['state'], 'Matching provider query must complete local reconciliation');
	npcink_test_assert_same(2, Npcink_Test_Alipay_Refund::$query_calls, 'Each resolver invocation must issue only one provider query');
	npcink_test_assert_same(array(), Npcink_Pay_Refund_Admin_Public::get_refund_uncertain($order, '支付宝'), 'Confirmed refund must clear uncertain state after audit insert');
	npcink_test_assert_true(in_array(Npcink_Pay_Refund_Admin_Public::refund_lock_name($order, '支付宝'), $GLOBALS['npcink_test_deleted_options'], true), 'Confirmed refund must release the claim');
});

npcink_test_case('WeChat retries only after explicit refund-not-found and preserve request parameters', function () {
	$order = 'wechat-order-1';
	npcink_test_assert_same($order . '-refund', Npcink_Pay_Refund_Admin_Wx::refund_request_id($order), 'Valid legacy refund ids must remain query-compatible');
	$hardened_id = Npcink_Pay_Refund_Admin_Wx::refund_request_id(str_repeat('x', 70) . '/invalid');
	npcink_test_assert_same($hardened_id, Npcink_Pay_Refund_Admin_Wx::refund_request_id(str_repeat('x', 70) . '/invalid'), 'Fallback refund id must be stable');
	npcink_test_assert_true(strlen($hardened_id) <= 64 && (bool) preg_match('/^[0-9A-Za-z_\-|*@]+$/D', $hardened_id), 'Fallback refund id must fit WeChat constraints');
	npcink_test_assert_same('not_found', Npcink_Pay_Refund_Admin_Wx::classify_api_error(404, 'RESOURCE_NOT_EXISTS'), 'Only the documented missing refund code permits retry');
	npcink_test_assert_same('unknown', Npcink_Pay_Refund_Admin_Wx::classify_api_error(404, 'MCH_NOT_EXISTS'), 'A merchant configuration error must not permit retry');
	npcink_test_assert_same('unknown', Npcink_Pay_Refund_Admin_Wx::classify_api_error(500, 'SYSTEM_ERROR'), 'A provider timeout must remain uncertain');

	$response = (object) array(
		'out_trade_no' => $order,
		'out_refund_no' => Npcink_Pay_Refund_Admin_Wx::refund_request_id($order),
	);
	npcink_test_assert_true(Npcink_Pay_Refund_Admin_Wx::refund_response_matches($response, $order), 'Signed response identifiers must match the requested refund');
	$response->out_refund_no = 'wrong-refund-id';
	npcink_test_assert_same(false, Npcink_Pay_Refund_Admin_Wx::refund_response_matches($response, $order), 'Mismatched signed response must fail closed');

	Npcink_Pay_Refund_Admin_Wx::save_pending_refund($order, 'Operator', 7.0, 'Original reason', '2026-07-19 10:00', 1000, true);
	Npcink_Pay_Refund_Admin_Wx::save_pending_refund($order, 'Other operator', 9.0, 'Changed reason', '2026-07-19 10:01', 1200, true);
	$pending = Npcink_Pay_Refund_Admin_Wx::get_pending_refund($order);
	npcink_test_assert_same('Original reason', $pending['reason'], 'Retry must preserve the original reason');
	npcink_test_assert_same(1000, $pending['request_amount'], 'Retry must preserve the original amount');
	npcink_test_assert_same(2, $pending['submit_attempts'], 'Submission attempts must remain observable');
	Npcink_Pay_Refund_Admin_Wx::advance_refund_request_id($order);
	npcink_test_assert_same($order . '-refund-1', Npcink_Pay_Refund_Admin_Wx::refund_request_id($order), 'A closed or abnormal refund must advance to a new request id');
});

npcink_test_case('WeChat refund-not-found response must have a fresh valid platform signature', function () {
	$private_key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
	npcink_test_assert_true(false !== $private_key, 'Test RSA private key must be generated');
	$key_details = openssl_pkey_get_details($private_key);
	npcink_test_assert_true(is_array($key_details) && !empty($key_details['key']), 'Test RSA public key must be available');

	$timestamp = (string) time();
	$nonce = 'test-refund-nonce';
	$serial = 'PUB_KEY_ID_TEST';
	$body = '{"code":"RESOURCE_NOT_EXISTS","message":"refund not found"}';
	$signature = WeChatPay\Crypto\Rsa::sign(WeChatPay\Formatter::response($timestamp, $nonce, $body), $private_key);
	$response = new GuzzleHttp\Psr7\Response(404, array(
		'Wechatpay-Nonce' => $nonce,
		'Wechatpay-Serial' => $serial,
		'Wechatpay-Signature' => $signature,
		'Wechatpay-Timestamp' => $timestamp,
	), $body);
	Npcink_Pay_Refund_Admin_Wx::$merchant_config = array(
		'mchid' => 'test-mchid',
		'serial_no' => 'test-merchant-serial',
		'private_key' => 'test-private-key',
		'platform_key_id' => $serial,
		'platform_public_key' => $key_details['key'],
	);
	Npcink_Pay_Refund_Admin_Wx::$client = new stdClass();

	npcink_test_assert_true(Npcink_Pay_Refund_Admin_Wx::error_response_signature_is_valid($response, $body), 'Valid signed provider error may be classified');
	npcink_test_assert_same(false, Npcink_Pay_Refund_Admin_Wx::error_response_signature_is_valid($response, $body . 'tampered'), 'Tampered error body must remain uncertain');
	$stale_response = $response->withHeader('Wechatpay-Timestamp', (string) (time() - 301));
	npcink_test_assert_same(false, Npcink_Pay_Refund_Admin_Wx::error_response_signature_is_valid($stale_response, $body), 'Stale signed error must remain uncertain');

	$request = new GuzzleHttp\Psr7\Request('GET', 'https://api.mch.weixin.qq.com/v3/refund/domestic/refunds/test');
	$signed_result = Npcink_Pay_Refund_Admin_Wx::api_call_result(function () use ($request, $response) {
		throw new GuzzleHttp\Exception\RequestException('signed not found', $request, $response);
	});
	npcink_test_assert_same('not_found', $signed_result['state'], 'API wrapper may authorize retry only from a signed provider not-found response');
	$unsigned_response = new GuzzleHttp\Psr7\Response(404, array(), $body);
	$unsigned_result = Npcink_Pay_Refund_Admin_Wx::api_call_result(function () use ($request, $unsigned_response) {
		throw new GuzzleHttp\Exception\RequestException('unsigned not found', $request, $unsigned_response);
	});
	npcink_test_assert_same('unknown', $unsigned_result['state'], 'Unsigned provider error must keep pending state and block resubmission');
});

npcink_test_case('audit write failure preserves lock and reconciliation state', function () {
    global $wpdb;
    $wpdb = new Npcink_Test_Wpdb();
    $wpdb->insert_result = false;

    $order = 'coupon-order-1';
    $type = '支付宝';
    $lock_name = Npcink_Pay_Refund_Admin_Public::refund_lock_name($order, $type);
    $reconciliation_name = Npcink_Pay_Refund_Admin_Public::refund_reconciliation_name($order, $type);
	$uncertain_name = Npcink_Pay_Refund_Admin_Public::refund_uncertain_name($order, $type);
    $GLOBALS['npcink_test_options'][$lock_name] = time();
	Npcink_Pay_Refund_Admin_Public::save_refund_uncertain(array(
		'n_time' => '2026-07-18 20:00',
		'n_user' => 'Operator',
		'n_amount' => 7.0,
		'n_order' => $order,
		'n_reason' => 'Coupon refund',
		'n_type' => $type,
		'request_id' => 'stable-refund-id',
	));

    $result = Npcink_Pay_Refund_Admin_Public::add_data('2026-07-18 20:00', 'Operator', 7.0, $order, 'Coupon refund', $type);
    npcink_test_assert_same(false, $result, 'Failed database insert must propagate');
    npcink_test_assert_true(isset($GLOBALS['npcink_test_options'][$lock_name]), 'Refund lock must remain after audit failure');
    npcink_test_assert_true(isset($GLOBALS['npcink_test_options'][$reconciliation_name]), 'Reconciliation marker must persist');
	npcink_test_assert_true(!isset($GLOBALS['npcink_test_options'][$uncertain_name]), 'Confirmed provider success must transition out of uncertain state');
    npcink_test_assert_same(false, Npcink_Pay_Refund_Admin_Public::claim_refund($order, $type), 'Reconciliation marker must block another refund');

    $wpdb->insert_result = 1;
    $retry = Npcink_Pay_Refund_Admin_Public::retry_refund_reconciliation($order, $type);
    npcink_test_assert_same(1, $retry, 'Successful retry must return the insert result');
    npcink_test_assert_same($order, $wpdb->inserted_data['n_order'], 'Local retry must reuse the stored order without calling a gateway');
    npcink_test_assert_same($type, $wpdb->inserted_data['n_type'], 'Local retry must reuse the stored provider type');
    npcink_test_assert_true(!isset($GLOBALS['npcink_test_options'][$lock_name]), 'Successful audit write must release the lock');
    npcink_test_assert_true(!isset($GLOBALS['npcink_test_options'][$reconciliation_name]), 'Successful audit write must clear reconciliation state');
});

npcink_test_case('WeChat success keeps pending state until audit write succeeds', function () {
    global $wpdb;
    $wpdb = new Npcink_Test_Wpdb();
    $wpdb->insert_result = false;

    $order = 'coupon-order-2';
    $data = (object) array(
        'status' => 'SUCCESS',
        'amount' => (object) array('payer_refund' => 700),
    );
    $fallback = array(
        'user' => 'Operator',
        'amount' => 7.0,
        'reason' => 'Coupon refund',
        'time' => '2026-07-18 20:10',
    );

    $recorded = Npcink_Pay_Refund_Admin_Wx::record_successful_refund($order, $data, $fallback);
    npcink_test_assert_same(false, $recorded, 'WeChat audit failure must propagate');
    $pending_name = Npcink_Pay_Refund_Admin_Wx::pending_refund_option_name($order);
    npcink_test_assert_true(isset($GLOBALS['npcink_test_options'][$pending_name]), 'Pending refund context must remain available for reconciliation');

    $wpdb->insert_result = 1;
    $retried = Npcink_Pay_Refund_Admin_Wx::record_successful_refund($order, $data);
    npcink_test_assert_same(true, $retried, 'Pending audit record must be retryable');
    npcink_test_assert_true(!isset($GLOBALS['npcink_test_options'][$pending_name]), 'Pending state must clear after successful recording');
});

echo "{$passed} test(s) passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
