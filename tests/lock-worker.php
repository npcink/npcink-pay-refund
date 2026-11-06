<?php
/** A separate PHP process for isolated WordPress connection-lock checks. */
$wp_root = isset($argv[1]) ? rtrim($argv[1], '/') : '';
if (!is_file($wp_root . '/.npcink-refund-test-site') || empty($argv[2])) {
    fwrite(STDERR, "Isolated WordPress root and test order required.\n");
    exit(1);
}
require $wp_root . '/wp-load.php';
require_once dirname(__DIR__) . '/admin/pay/npcink-pay-refund-admin-public.php';
if (!Npcink_Pay_Refund_Admin_Public::lock_operation($argv[2], '微信')) {
    fwrite(STDERR, "Could not acquire test order mutex.\n");
    exit(1);
}
echo "LOCKED\n";
flush();
// Parent either closes stdin to exercise shutdown or kills this process.
fgets(STDIN);
