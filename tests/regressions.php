<?php

npcink_test_case('payment config shares constant precedence with settings', function () {
    define('NPCINK_PAY_REFUND_WX_CERT_KEY', 'constant-private-key');
    update_option('npcink_pay_refund_config', array('wx' => array('cert_key' => 'legacy-key')));
    update_option('npcink_pay_refund_secrets', array('wx' => array('cert_key' => 'db-key')));
    npcink_test_assert_same('constant-private-key', Npcink_Pay_Refund_Admin::npcConfig('wx')['cert_key'], 'Payment calls must honor wp-config secrets');
});

npcink_test_case('all five real payment AJAX entrypoints deny invalid nonce and capability', function () {
    $handlers = array(
        array('Npcink_Pay_Refund_Admin_Zfb', 'npcink_pay_refund_zfb_order_query'),
        array('Npcink_Pay_Refund_Admin_Zfb', 'npcink_pay_refund_zfb_order_refund'),
        array('Npcink_Pay_Refund_Admin_Wx', 'npcink_pay_refund_wx_order_query'),
        array('Npcink_Pay_Refund_Admin_Wx', 'npcink_pay_refund_wx_order_refund'),
        array('Npcink_Pay_Refund_Admin_Wx', 'npcink_pay_refund_wx_refund_status'),
    );
    foreach ($handlers as $handler) {
        foreach (array(false, true) as $valid_nonce) {
            $GLOBALS['npcink_test_nonce'] = $valid_nonce;
            try { call_user_func($handler); }
            catch (Npcink_Test_Json $response) {
                npcink_test_assert_same(403, $response->status, implode('::', $handler) . ' must reject before gateway work');
                continue;
            }
            throw new RuntimeException('Unauthorized handler returned');
        }
    }
});

npcink_test_case('empty and malformed WeChat timestamps fail closed', function () {
    foreach (array('', 'now', 'tomorrow', '2026-02-30T12:00:00+08:00', array(), '2026-11-05') as $input) {
        npcink_test_assert_same('', Npcink_Pay_Refund_Admin_Wx::handle_time($input), 'Reject malformed payment time');
    }
    npcink_test_assert_same('2026-11-05 04:00:00', Npcink_Pay_Refund_Admin_Wx::handle_time('2026-11-05T12:00:00+08:00'), 'Convert RFC3339 instant to site time');
});

npcink_test_case('Alipay payment window uses the provider timezone', function () {
    $now = (new DateTimeImmutable())->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('Y-m-d H:i:s');
    npcink_test_assert_true(Npcink_Pay_Refund_Admin_Public::contrast_time($now, 'Asia/Shanghai'), 'UTC site must accept a current Shanghai payment');
});

npcink_test_case('unknown pending card does not claim provider acceptance', function () {
    $html = Npcink_Pay_Refund_Admin_Wx::render_pending_refund_html('order', array());
    npcink_test_assert_true(false !== strpos($html, '退款状态未知'), 'Unknown response must remain visibly unknown');
    npcink_test_assert_same(false, strpos($html, '微信支付已受理退款'), 'Do not assert acceptance without a verified response');
});

npcink_test_case('real EasySDK signs normalized PEM and rejects invalid key material', function () {
    $keys = npcink_test_keys();
    $private = Npcink_Pay_Refund_Admin_Zfb::normalize_rsa_key($keys['private'], true);
    $public = Npcink_Pay_Refund_Admin_Zfb::normalize_rsa_key($keys['public'], false);
    $signer = new Alipay\EasySDK\Kernel\Util\Signer();
    $signature = $signer->sign('refund-regression', $private);
    npcink_test_assert_true($signer->verify('refund-regression', $signature, $public), 'Actual SDK must accept configured PEM after normalization');
    npcink_test_assert_same($private, Npcink_Pay_Refund_Admin_Zfb::normalize_rsa_key($private, true), 'Base64 input remains supported');
    try { Npcink_Pay_Refund_Admin_Zfb::normalize_rsa_key('invalid-key', true); }
    catch (InvalidArgumentException $error) { return; }
    throw new RuntimeException('Malformed RSA accepted');
});

npcink_test_case('valid Alipay config diagnosis resolves the actual SDK namespace', function () {
    $keys = npcink_test_keys();
    update_option('npcink_pay_refund_config', array('zfb' => array('appid' => 'test', 'private_key' => $keys['private'], 'public_key' => $keys['public'])));
    npcink_test_assert_same('ok', Npcink_Pay_Refund_Admin_Zfb::diagnose_config()['status'], 'Valid local config must pass diagnosis');
});

npcink_test_case('Alipay missing and non-success refund statuses preserve uncertainty', function () {
    $order = 'status-order';
    $id = Npcink_Pay_Refund_Admin_Zfb::refund_request_id($order);
    $result = (object) array('code' => '10000', 'outTradeNo' => $order, 'outRequestNo' => $id, 'refundAmount' => '7.00');
    foreach (array(null, 'REFUND_PROCESSING', 'FUTURE_STATUS') as $status) {
        $result->refundStatus = $status;
        npcink_test_assert_same('unknown', Npcink_Pay_Refund_Admin_Zfb::classify_refund_query_response($result, $order, $id, '7.00'), 'Amount alone cannot prove completion');
    }
});

function npcink_test_wx_success($order, $payer = 700) {
    return (object) array('status' => 'SUCCESS', 'out_trade_no' => $order,
        'out_refund_no' => Npcink_Pay_Refund_Admin_Wx::active_refund_request_id($order),
        'amount' => (object) array('total' => 1000, 'refund' => 1000, 'payer_refund' => $payer, 'currency' => 'CNY'));
}

npcink_test_case('mismatched WeChat amounts and currencies never clear pending', function () {
    $order = 'amount-order';
    Npcink_Pay_Refund_Admin_Wx::save_pending_refund($order, 'Operator', 7, 'reason', '2026-11-05 10:00:00', 1000);
    foreach (array(array('refund', 1), array('total', 1), array('currency', 'USD'), array('payer_refund', -1), array('payer_refund', 1001), array('refund', '1000')) as $change) {
        $data = npcink_test_wx_success($order);
        $data->amount->{$change[0]} = $change[1];
        npcink_test_assert_same(false, Npcink_Pay_Refund_Admin_Wx::record_successful_refund($order, $data), 'Reject mismatched provider amount');
        npcink_test_assert_true((bool) Npcink_Pay_Refund_Admin_Wx::get_pending_refund($order), 'Keep original context');
    }
});

npcink_test_case('valid zero payer refund is recorded as zero', function () {
    global $wpdb;
    $order = 'zero-payer-order';
    Npcink_Pay_Refund_Admin_Wx::save_pending_refund($order, 'Operator', 0, 'reason', '2026-11-05 10:00:00', 1000);
    npcink_test_assert_true(Npcink_Pay_Refund_Admin_Wx::record_successful_refund($order, npcink_test_wx_success($order, 0)), 'Zero payer amount is valid when merchant full refund matches');
    npcink_test_assert_same(0.0, $wpdb->inserted_data['n_amount'], 'Do not substitute a nonzero fallback');
});

npcink_test_case('late terminal response cannot clear a replacement WeChat attempt', function () {
    $order = 'terminal-race';
    Npcink_Pay_Refund_Admin_Wx::save_pending_refund($order, 'Operator', 7, 'reason', '2026-11-05 10:00:00', 1000);
    $old = (object) array('status' => 'CLOSED', 'out_trade_no' => $order, 'out_refund_no' => $order . '-refund');
    npcink_test_assert_true(Npcink_Pay_Refund_Admin_Wx::sync_refund_status($order, $old), 'First terminal response rotates the attempt');
    npcink_test_assert_same($order . '-refund-1', Npcink_Pay_Refund_Admin_Wx::refund_request_id($order), 'Terminal transition stores next ID atomically');
    Npcink_Pay_Refund_Admin_Wx::save_pending_refund($order, 'Operator', 7, 'reason', '2026-11-05 10:01:00', 1000);
    npcink_test_assert_same(false, Npcink_Pay_Refund_Admin_Wx::sync_refund_status($order, $old), 'Delayed old response must fail its request fence');
    npcink_test_assert_same($order . '-refund-1', Npcink_Pay_Refund_Admin_Wx::get_pending_refund($order)['request_id'], 'Replacement context survives');
});

npcink_test_case('persisted WeChat request id survives legacy partial attempt advancement', function () {
    $order = 'legacy-partial';
    Npcink_Pay_Refund_Admin_Wx::save_pending_refund($order, 'Operator', 7, 'reason', '2026-11-05 10:00:00', 1000);
    Npcink_Pay_Refund_Admin_Wx::advance_refund_request_id($order);
    npcink_test_assert_same($order . '-refund', Npcink_Pay_Refund_Admin_Wx::active_refund_request_id($order), 'Query original persisted ID');
    npcink_test_assert_true(Npcink_Pay_Refund_Admin_Wx::sync_refund_status($order, (object) array('status' => 'CLOSED', 'out_trade_no' => $order, 'out_refund_no' => $order . '-refund')), 'Legacy partial transition is recoverable');
    npcink_test_assert_same($order . '-refund-1', Npcink_Pay_Refund_Admin_Wx::refund_request_id($order), 'Do not advance twice');
});

npcink_test_case('stale claim CAS and release cannot delete a replacement owner', function () {
    $order = 'claim-race'; $type = '微信';
    $key = Npcink_Pay_Refund_Admin_Public::refund_lock_name($order, $type);
    $expired = time() - 601;
    update_option($key, $expired);
    npcink_test_assert_true(Npcink_Pay_Refund_Admin_Public::claim_refund($order, $type), 'Expired legacy claim can be atomically replaced');
    npcink_test_assert_same(false, Npcink_Pay_Refund_Admin_Public::compare_option($key, $expired, array('time' => time(), 'token' => 'stale-contender')), 'Second stale contender loses CAS');
    $replacement = array('time' => time(), 'token' => 'new-owner');
    update_option($key, $replacement);
    Npcink_Pay_Refund_Admin_Public::release_refund_claim($order, $type);
    npcink_test_assert_same($replacement, get_option($key), 'Old owner cannot release a newer claim');
});

npcink_test_case('Guzzle generated exception never logs raw gateway messages', function () {
    $log = tempnam(sys_get_temp_dir(), 'npcink-log-');
    $old_log = ini_get('error_log');
    ini_set('error_log', $log);
    try {
        Npcink_Pay_Refund_Admin_Wx::$client = new stdClass();
        Npcink_Pay_Refund_Admin_Wx::$merchant_config = array_fill_keys(array('mchid', 'serial_no', 'private_key', 'platform_key_id', 'platform_public_key'), 'synthetic');
        $request = new GuzzleHttp\Psr7\Request('POST', 'https://api.mch.weixin.qq.com/v3/refund/domestic/refunds');
        $response = new GuzzleHttp\Psr7\Response(500, array(), '{"code":"SYSTEM_ERROR","message":"buyer-secret-13700000000"}');
        Npcink_Pay_Refund_Admin_Wx::api_call_result(function () use ($request, $response) {
            throw GuzzleHttp\Exception\RequestException::create($request, $response);
        });
        $text = file_get_contents($log);
        npcink_test_assert_same(false, strpos($text, 'buyer-secret'), 'Real Guzzle body summary must not enter logs');
        npcink_test_assert_same(false, strpos($text, '13700000000'), 'Provider personal information must not enter logs');
        npcink_test_assert_true(false !== strpos($text, 'SYSTEM_ERROR'), 'Keep bounded diagnostic code');
    } finally { ini_set('error_log', $old_log); unlink($log); }
});

npcink_test_case('refund timestamps use provider or submission time in the site timezone', function () {
    $GLOBALS['npcink_test_timezone'] = 'Asia/Shanghai';
    try {
        npcink_test_assert_same('2026-11-05 12:00:00', Npcink_Pay_Refund_Admin_Zfb::refund_time('2026-11-05 12:00:00'), 'Provider Shanghai time must not receive a second offset');
        $submitted = (new DateTimeImmutable('2026-11-05T04:00:00Z'))->getTimestamp();
        npcink_test_assert_same('2026-11-05 12:00:00', Npcink_Pay_Refund_Admin_Zfb::refund_time('', $submitted), 'Missing provider time uses real submission instant');
    } finally { $GLOBALS['npcink_test_timezone'] = 'UTC'; }
});

npcink_test_case('completed replacement attempt retains its request id for later queries', function () {
    $order = 'completed-replacement';
    Npcink_Pay_Refund_Admin_Wx::save_pending_refund($order, 'Operator', 7, 'reason', '2026-11-05 10:00:00', 1000);
    Npcink_Pay_Refund_Admin_Wx::sync_refund_status($order, (object) array('status' => 'CLOSED', 'out_trade_no' => $order, 'out_refund_no' => $order . '-refund'));
    Npcink_Pay_Refund_Admin_Wx::save_pending_refund($order, 'Operator', 7, 'reason', '2026-11-05 10:01:00', 1000);
    npcink_test_assert_same($order . '-refund-1', Npcink_Pay_Refund_Admin_Wx::refund_request_id($order), 'Active attempt generator stays consistent');
    npcink_test_assert_true(Npcink_Pay_Refund_Admin_Wx::record_successful_refund($order, npcink_test_wx_success($order)), 'Record the replacement success');
    npcink_test_assert_same($order . '-refund-1', Npcink_Pay_Refund_Admin_Wx::active_refund_request_id($order), 'Completed attempt remains queryable after pending deletion');
});

npcink_test_case('all four secret constants override persisted config without being saved', function () {
    $keys = npcink_test_keys();
    define('NPCINK_PAY_REFUND_ZFB_PRIVATE_KEY', $keys['private']);
    define('NPCINK_PAY_REFUND_ZFB_PUBLIC_KEY', $keys['public']);
    define('NPCINK_PAY_REFUND_WX_PLATFORM_PUBLIC_KEY', $keys['public']);
    $database = array('zfb' => array('private_key' => 'db-private', 'public_key' => 'db-public'),
        'wx' => array('cert_key' => 'db-wx-private', 'platform_public_key' => 'db-wx-public'));
    update_option('npcink_pay_refund_secrets', $database);
    foreach (Npcink_Pay_Refund_Admin_Config::secret_fields() as $section => $fields) {
        foreach ($fields as $field) {
            $name = 'NPCINK_PAY_REFUND_' . strtoupper($section . '_' . $field);
            npcink_test_assert_same(constant($name), Npcink_Pay_Refund_Admin::npcConfig($section)[$field], 'Payment accessor must honor ' . $name);
        }
    }
    Npcink_Pay_Refund_Admin_Config::save_secrets(array());
    npcink_test_assert_same($database, get_option('npcink_pay_refund_secrets'), 'Blank settings save cannot persist constants');
});
