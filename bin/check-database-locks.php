<?php
/** WP-CLI only. Session-scoped preflight, without writing plugin options or refund records. */
if (!defined('WP_CLI') || !WP_CLI || !defined('ABSPATH')) {
    throw new RuntimeException('Run with wp eval-file bin/check-database-locks.php --path=/explicit/site.');
}
(static function () {
    global $wpdb;
    $nonce = bin2hex(random_bytes(12));
    $lock = 'npcink-preflight:' . $nonce;
    $table = 'npcink_preflight_' . $nonce; // Generated hex only; never an identifier from input.
    $other = null; $created = false; $locked = false;
    $report = array('php' => PHP_VERSION, 'database' => $wpdb->db_version(), 'checks' => array());
    $assert = static function ($condition, $label) use (&$report) {
        if (!$condition) { throw new RuntimeException('Database preflight failed: ' . $label); }
        $report['checks'][] = $label;
    };
    try {
        $connection = (string) $wpdb->get_var('SELECT CONNECTION_ID()');
        $assert('' !== $connection, 'connection identity available');
        $assert('0' === (string) $wpdb->get_var('SELECT @@read_only'), 'connection uses a writable database server');
        $locked = '1' === (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock));
        $assert($locked, 'named mutex acquired');
        $assert($connection === (string) $wpdb->get_var('SELECT CONNECTION_ID()'), 'mutex acquisition retained the original connection');
        $other = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $assert('0' === (string) $other->get_var($other->prepare('SELECT GET_LOCK(%s, 0)', $lock)), 'independent connection excluded by mutex');
        $created = false !== $wpdb->query("CREATE TEMPORARY TABLE {$table} (marker varchar(64) NOT NULL)");
        $assert($created, 'temporary table created on the write connection');
        $assert(1 === $wpdb->query($wpdb->prepare("INSERT INTO {$table} (marker) VALUES (%s)", $nonce)), 'temporary write succeeded');
        $assert($nonce === $wpdb->get_var("SELECT marker FROM {$table}"), 'subsequent SELECT sees the same temporary write');
        $assert($connection === (string) $wpdb->get_var('SELECT CONNECTION_ID()')
            && $connection === (string) $wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock)), 'reads, writes and mutex still share one connection');
        $assert('1' === (string) $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)), 'mutex released');
        $locked = false;
        $assert('1' === (string) $other->get_var($other->prepare('SELECT GET_LOCK(%s, 0)', $lock)), 'independent connection acquires released mutex');
        $assert('1' === (string) $other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)', $lock)), 'independent connection released its mutex');
    } finally {
        if ($created) { $wpdb->query("DROP TEMPORARY TABLE IF EXISTS {$table}"); }
        if ($locked) { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
        if ($other) { $other->close(); }
    }
    $report['result'] = 'passed_for_observed_connections';
    $report['scope'] = 'Session probe only; deployment routing changes require a new run. No gateway requests or persistent plugin writes.';
    echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
})();
