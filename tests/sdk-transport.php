<?php
/** Actual SDK signing/verification with only the HTTP transport replaced. */
function npcink_test_sdk_client($response, &$history)
{
    $keys = npcink_test_keys();
    $stack = GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler(array($response)));
    $stack->push(GuzzleHttp\Middleware::history($history));
    Npcink_Pay_Refund_Admin_Wx::$merchant_config = array(
        'mchid' => 'synthetic-mchid', 'serial_no' => 'synthetic-serial', 'private_key' => $keys['private'],
        'platform_key_id' => 'PUB_KEY_ID_SYNTHETIC', 'platform_public_key' => $keys['public'],
    );
    Npcink_Pay_Refund_Admin_Wx::$client = WeChatPay\Builder::factory(array(
        'mchid' => 'synthetic-mchid', 'serial' => 'synthetic-serial', 'privateKey' => $keys['private'],
        'certs' => array('PUB_KEY_ID_SYNTHETIC' => $keys['public']), 'handler' => $stack,
    ));
}

function npcink_test_signed_response($status, $data, $tamper = false)
{
    $body = json_encode($data);
    $timestamp = (string) time(); $nonce = 'sdk-test-nonce';
    $signature = WeChatPay\Crypto\Rsa::sign(WeChatPay\Formatter::response($timestamp, $nonce, $body), npcink_test_keys()['private']);
    return new GuzzleHttp\Psr7\Response($status, array(
        'Content-Type' => 'application/json', 'Wechatpay-Serial' => 'PUB_KEY_ID_SYNTHETIC',
        'Wechatpay-Timestamp' => $timestamp, 'Wechatpay-Nonce' => $nonce, 'Wechatpay-Signature' => $signature,
    ), $tamper ? $body . ' ' : $body);
}

npcink_test_case('real WeChat SDK queries persisted request id and verifies successful response', function () {
    $order = 'sdk-persisted-query';
    Npcink_Pay_Refund_Admin_Wx::save_pending_refund($order, 'Operator', 7, 'reason', wp_date('Y-m-d H:i:s'), 1000);
    Npcink_Pay_Refund_Admin_Wx::advance_refund_request_id($order);
    $response = npcink_test_wx_success($order);
    $history = array();
    npcink_test_sdk_client(npcink_test_signed_response(200, $response), $history);
    $result = Npcink_Pay_Refund_Admin_Wx::get_refund_result($order);
    npcink_test_assert_same('success', $result['state'], 'Actual SDK must verify response before returning data');
    npcink_test_assert_same('/v3/refund/domestic/refunds/' . $order . '-refund', $history[0]['request']->getUri()->getPath(), 'Query persisted id even after partial legacy advancement');
    npcink_test_assert_true(str_starts_with($history[0]['request']->getHeaderLine('Authorization'), 'WECHATPAY2-SHA256-RSA2048 '), 'Actual SDK must sign the outgoing query');
    npcink_test_assert_true(Npcink_Pay_Refund_Admin_Wx::record_successful_refund($order, $result['data']), 'Signed matching refund reconciles successfully');
});

npcink_test_case('real WeChat SDK submits the persisted full refund through the locked dispatch', function () {
    $order = 'sdk-submit-refund';
    Npcink_Pay_Refund_Admin_Wx::save_pending_refund($order, 'Operator', 7, 'reason', wp_date('Y-m-d H:i:s'), 1000);
    $pending = Npcink_Pay_Refund_Admin_Wx::get_pending_refund($order);
    $history = array();
    npcink_test_sdk_client(npcink_test_signed_response(200, npcink_test_wx_success($order)), $history);
    $result = Npcink_Pay_Refund_Admin_Wx::submit_refund($order, $pending['request_id'], 'reason', 1000);
    npcink_test_assert_same('success', $result['state'], 'Locked dispatch must retain real SDK signature verification');
    npcink_test_assert_same(1, count($history), 'Dispatch sends exactly one HTTP request');
    $request = $history[0]['request'];
    npcink_test_assert_same('POST', $request->getMethod(), 'Refund uses POST');
    npcink_test_assert_same('/v3/refund/domestic/refunds', $request->getUri()->getPath(), 'Refund uses domestic refunds endpoint');
    $body = json_decode((string) $request->getBody(), true);
    npcink_test_assert_same($pending['request_id'], $body['out_refund_no'], 'Dispatch uses original persisted refund id');
    npcink_test_assert_same(array('refund' => 1000, 'total' => 1000, 'currency' => 'CNY'), $body['amount'], 'Dispatch sends full original amount and currency');
});

npcink_test_case('real WeChat SDK rejects tampered success and keeps original context', function () {
    $order = 'sdk-tampered-query';
    Npcink_Pay_Refund_Admin_Wx::save_pending_refund($order, 'Operator', 7, 'reason', wp_date('Y-m-d H:i:s'), 1000);
    $pending = Npcink_Pay_Refund_Admin_Wx::get_pending_refund($order);
    $history = array();
    npcink_test_sdk_client(npcink_test_signed_response(200, npcink_test_wx_success($order), true), $history);
    $result = Npcink_Pay_Refund_Admin_Wx::get_refund_result($order);
    npcink_test_assert_same('unknown', $result['state'], 'SDK signature failure cannot become a successful query');
    npcink_test_assert_same(null, $result['data'], 'Do not expose unverified success data');
    npcink_test_assert_same($pending, Npcink_Pay_Refund_Admin_Wx::get_pending_refund($order), 'Signature failure retains exact pending context');
});

npcink_test_case('real WeChat SDK error path permits retry only from signed refund not found', function () {
    foreach (array(false, true) as $tamper) {
        $history = array();
        npcink_test_sdk_client(npcink_test_signed_response(404, array('code' => 'RESOURCE_NOT_EXISTS', 'message' => 'synthetic'), $tamper), $history);
        $result = Npcink_Pay_Refund_Admin_Wx::get_refund_result('sdk-missing-refund');
        npcink_test_assert_same($tamper ? 'unknown' : 'not_found', $result['state'], 'Actual SDK/error verification rejects tampered missing responses');
    }
});
