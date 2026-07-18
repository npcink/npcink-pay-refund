<?php

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/admin/pay/npcink-pay-refund-admin-public.php';
require dirname(__DIR__) . '/admin/pay/npcink-pay-refund-admin-wx.php';
require dirname(__DIR__) . '/admin/pay/npcink-pay-refund-admin-zfb.php';

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

npcink_test_case('published PHP baseline matches Composer', function () {
    $root = dirname(__DIR__);
    $composer = json_decode(file_get_contents($root . '/composer.json'), true);
    npcink_test_assert_same('>=8.1', $composer['require']['php'], 'Composer must declare the runtime PHP floor');

    $plugin = file_get_contents($root . '/npcink-pay-refund.php');
    $readme = file_get_contents($root . '/readme.txt');
    npcink_test_assert_true((bool) preg_match('/Requires PHP:\s+8\.1/', $plugin), 'Plugin header must require PHP 8.1');
    npcink_test_assert_true((bool) preg_match('/^Requires PHP:\s*8\.1$/m', $readme), 'WordPress.org readme must require PHP 8.1');
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

npcink_test_case('audit write failure preserves lock and reconciliation state', function () {
    global $wpdb;
    $wpdb = new Npcink_Test_Wpdb();
    $wpdb->insert_result = false;

    $order = 'coupon-order-1';
    $type = '支付宝';
    $lock_name = Npcink_Pay_Refund_Admin_Public::refund_lock_name($order, $type);
    $reconciliation_name = Npcink_Pay_Refund_Admin_Public::refund_reconciliation_name($order, $type);
    $GLOBALS['npcink_test_options'][$lock_name] = time();

    $result = Npcink_Pay_Refund_Admin_Public::add_data('2026-07-18 20:00', 'Operator', 7.0, $order, 'Coupon refund', $type);
    npcink_test_assert_same(false, $result, 'Failed database insert must propagate');
    npcink_test_assert_true(isset($GLOBALS['npcink_test_options'][$lock_name]), 'Refund lock must remain after audit failure');
    npcink_test_assert_true(isset($GLOBALS['npcink_test_options'][$reconciliation_name]), 'Reconciliation marker must persist');
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
