<?php
/** Included by integration.php inside its isolated test scope. */
foreach (array('normal-exit', 'killed-process') as $scenario) {
    $worker_order = $prefix . '-' . $scenario;
    $process = proc_open(array(PHP_BINARY, $root . '/tests/lock-worker.php', rtrim(ABSPATH, '/'), $worker_order),
        array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Cannot start lock worker'); }
    try {
        stream_set_timeout($pipes[1], 10);
        $ready = fgets($pipes[1]);
        npcink_integration_assert("LOCKED\n" === $ready, $scenario . ': separate PHP worker acquired the mutex');
        npcink_integration_assert(!Npcink_Pay_Refund_Admin_Public::lock_operation($worker_order, '微信'), $scenario . ': concurrent PHP request is excluded');
        if ('killed-process' === $scenario) { proc_terminate($process, 9); }
        fclose($pipes[0]);
        $pipes[0] = null;
        // EOF waits for termination; the test worker has no external dependencies.
        stream_get_contents($pipes[1]);
        fclose($pipes[1]); $pipes[1] = null;
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]); $pipes[2] = null;
        proc_close($process); $process = null;
        $recovered = false;
        for ($attempt = 0; $attempt < 20; ++$attempt) {
            if (Npcink_Pay_Refund_Admin_Public::lock_operation($worker_order, '微信')) { $recovered = true; break; }
            usleep(25000);
        }
        npcink_integration_assert($recovered, $scenario . ': connection mutex becomes available after worker termination');
        npcink_integration_assert('' === $stderr, $scenario . ': worker emitted no PHP errors');
    } finally {
        foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
        if (is_resource($process)) { proc_terminate($process, 9); proc_close($process); }
    }
}

$lost_order = $prefix . '-lost-connection';
$lock_key = Npcink_Pay_Refund_Admin_Public::refund_lock_name($lost_order, '微信');
$pending_key = Npcink_Pay_Refund_Admin_Wx::pending_refund_option_name($lost_order);
$option_names[] = $lock_key; $option_names[] = $pending_key;
npcink_integration_assert(Npcink_Pay_Refund_Admin_Public::lock_operation($lost_order, '微信'), 'connection-loss test first holds mutex');
$mutex_name = 'npcink:' . substr(hash('sha256', $wpdb->prefix . '|微信|' . $lost_order), 0, 56);
$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $mutex_name));
$rival = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
try {
    npcink_integration_assert('1' === (string) $rival->get_var($rival->prepare('SELECT GET_LOCK(%s, 0)', $mutex_name)), 'replacement connection owns the lost mutex');
    npcink_integration_assert(!Npcink_Pay_Refund_Admin_Public::lock_operation($lost_order, '微信'), 'cached request ownership cannot hide a lost database mutex');
    npcink_integration_assert(!Npcink_Pay_Refund_Admin_Public::claim_refund($lost_order, '微信') && false === get_option($lock_key, false), 'lost mutex refuses persistent claim creation');
    npcink_integration_assert(!Npcink_Pay_Refund_Admin_Wx::save_pending_refund($lost_order, 'Operator', 7, 'reason', wp_date('Y-m-d H:i:s'), 1000)
        && false === get_option($pending_key, false), 'lost mutex refuses pending persistence');
} finally { $rival->close(); }

// Lose the mutex after persistence, at the final gateway dispatch boundary.
foreach (array('微信', '支付宝') as $channel) {
    $dispatch_order = $prefix . '-dispatch-' . ('微信' === $channel ? 'wx' : 'alipay');
    $pending_key = Npcink_Pay_Refund_Admin_Wx::pending_refund_option_name($dispatch_order);
    $uncertain_key = Npcink_Pay_Refund_Admin_Public::refund_uncertain_name($dispatch_order, $channel);
    $option_names[] = $pending_key; $option_names[] = $uncertain_key;
    if ('微信' === $channel) {
        Npcink_Pay_Refund_Admin_Wx::save_pending_refund($dispatch_order, 'Operator', 7, 'reason', wp_date('Y-m-d H:i:s'), 1000);
    } else {
        Npcink_Pay_Refund_Admin_Public::lock_operation($dispatch_order, $channel);
        update_option($uncertain_key, array('request_id' => 'synthetic-request', 'n_order' => $dispatch_order), false);
    }
    $context_key = '微信' === $channel ? $pending_key : $uncertain_key;
    $before_dispatch = get_option($context_key);
    $mutex_name = 'npcink:' . substr(hash('sha256', $wpdb->prefix . '|' . $channel . '|' . $dispatch_order), 0, 56);
    $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $mutex_name));
    if ('微信' === $channel) {
        $history = array();
        $stack = GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler(array(new GuzzleHttp\Psr7\Response(200, array(), '{}'))));
        $stack->push(GuzzleHttp\Middleware::history($history));
        // Client already ready; only the transport is replaced. No merchant keys or network.
        $original_client = Npcink_Pay_Refund_Admin_Wx::$client;
        $original_merchant = Npcink_Pay_Refund_Admin_Wx::$merchant_config;
        $key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
        $public_key = openssl_pkey_get_public(openssl_pkey_get_details($key)['key']);
        Npcink_Pay_Refund_Admin_Wx::$merchant_config = array('mchid' => 'synthetic', 'serial_no' => 'synthetic-merchant',
            'private_key' => $key, 'platform_key_id' => 'PUB_KEY_ID_SYNTHETIC', 'platform_public_key' => $public_key);
        Npcink_Pay_Refund_Admin_Wx::$client = WeChatPay\Builder::factory(array('handler' => $stack,
            'mchid' => 'synthetic', 'serial' => 'synthetic-merchant', 'privateKey' => $key,
            'certs' => array('PUB_KEY_ID_SYNTHETIC' => $public_key)));
        if (!Npcink_Pay_Refund_Admin_Wx::client_is_ready()) { throw new RuntimeException('Dispatch fixture must reach the SDK callback.'); }
        $mutex_checks = 0;
        $observe_mutex = static function ($query) use (&$mutex_checks) {
            if (str_contains($query, 'IS_USED_LOCK(')) { ++$mutex_checks; }
            return $query;
        };
        add_filter('query', $observe_mutex);
        try {
            $result = Npcink_Pay_Refund_Admin_Wx::submit_refund($dispatch_order, $before_dispatch['request_id'], 'reason', 1000);
            npcink_integration_assert(1 === $mutex_checks && 'unknown' === $result['state'] && empty($history), 'WeChat dispatch refuses lost mutex before HTTP and keeps result unknown');
        } finally {
            remove_filter('query', $observe_mutex);
            Npcink_Pay_Refund_Admin_Wx::$client = $original_client;
            Npcink_Pay_Refund_Admin_Wx::$merchant_config = $original_merchant;
        }
    } else {
        $refused = false;
        try { Npcink_Pay_Refund_Admin_Zfb::submit_refund($dispatch_order, 7, 'reason', 'synthetic-request'); }
        catch (RuntimeException $error) { $refused = 'Refund operation mutex lost before dispatch.' === $error->getMessage(); }
        npcink_integration_assert($refused, 'Alipay dispatch refuses lost mutex before accessing its SDK');
    }
    npcink_integration_assert($before_dispatch === get_option($context_key), $channel . ' lost dispatch preserves exact persisted recovery context');
}

$unsupported_order = $prefix . '-no-get-lock';
$deny_locks = static function ($query) { return str_contains($query, 'GET_LOCK(') ? 'SELECT 0' : $query; };
add_filter('query', $deny_locks);
try {
    npcink_integration_assert(!Npcink_Pay_Refund_Admin_Public::lock_operation($unsupported_order, '微信'), 'unsupported GET_LOCK fails closed');
} finally { remove_filter('query', $deny_locks); }

$terminal_order = $prefix . '-terminal-cas-failure';
$pending_key = Npcink_Pay_Refund_Admin_Wx::pending_refund_option_name($terminal_order);
$option_names[] = $pending_key;
Npcink_Pay_Refund_Admin_Wx::save_pending_refund($terminal_order, 'Operator', 7, 'reason', wp_date('Y-m-d H:i:s'), 1000);
$before = get_option($pending_key);
$terminal_response = (object) array('status' => 'CLOSED', 'out_trade_no' => $terminal_order, 'out_refund_no' => $before['request_id']);
$deny_cas = static function ($query) use ($pending_key) {
    return str_starts_with($query, 'UPDATE ') && str_contains($query, $pending_key) && str_contains($query, 'BINARY option_value') ? 'NPCINK_INJECTED_CAS_FAILURE' : $query;
};
add_filter('query', $deny_cas);
$old_suppression = $wpdb->suppress_errors(true);
try {
    npcink_integration_assert(!Npcink_Pay_Refund_Admin_Wx::sync_refund_status($terminal_order, $terminal_response), 'failed terminal CAS propagates failure');
} finally { remove_filter('query', $deny_cas); $wpdb->suppress_errors($old_suppression); }
npcink_integration_assert($before === get_option($pending_key), 'failed terminal CAS preserves the exact original request context');
npcink_integration_assert(Npcink_Pay_Refund_Admin_Wx::sync_refund_status($terminal_order, $terminal_response)
    && $terminal_order . '-refund-1' === Npcink_Pay_Refund_Admin_Wx::refund_request_id($terminal_order), 'terminal transition retries once after database recovery');
