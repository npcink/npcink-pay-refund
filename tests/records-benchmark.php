<?php
/** Run with wp eval-file on a disposable WordPress/MySQL site. No gateway access. */
if (!defined('ABSPATH') || !is_file(ABSPATH . '.npcink-refund-test-site')) {
    throw new RuntimeException('An isolated .npcink-refund-test-site is required.');
}
(static function () {
    global $wpdb;
    require_once dirname(__DIR__) . '/admin/class-npcink-pay-refund-admin.php';
    require_once dirname(__DIR__) . '/admin/partials/npcink-pay-refund-admin-authority.php';
    require_once dirname(__DIR__) . '/admin/partials/npcink-pay-refund-admin-query.php';
    $initial_user = get_current_user_id();
    $admin = get_users(array('role' => 'administrator', 'number' => 1))[0]->ID;
    $source = $wpdb->prefix . 'npcink_pay_refund_order';
    $table = 'bench_refund_records';
    if (!preg_match('/^[A-Za-z0-9_]+$/D', $source)) { throw new RuntimeException('Invalid table name.'); }
    if (false === $wpdb->query("CREATE TEMPORARY TABLE `{$table}` LIKE `{$source}`")) {
        throw new RuntimeException('Cannot create session-local refund table.');
    }
    $operator = null;
    try {
        $operator = wp_insert_user(array('user_login' => 'bench-' . bin2hex(random_bytes(6)), 'user_pass' => wp_generate_password(), 'role' => 'author'));
        if (is_wp_error($operator)) { throw new RuntimeException($operator->get_error_message()); }
        get_userdata($operator)->add_cap('npcink_refund_orders');
        $digits = '(SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)';
        $numbers = "SELECT a.n + 10*b.n + 100*c.n + 1000*d.n + 10000*e.n + 100000*f.n AS seq
            FROM {$digits} a CROSS JOIN {$digits} b CROSS JOIN {$digits} c CROSS JOIN {$digits} d CROSS JOIN {$digits} e CROSS JOIN {$digits} f
            WHERE f.n < 5";
        $insert = $wpdb->prepare("INSERT INTO `{$table}` (id, n_amount, n_time, n_order, n_user, n_user_id, n_type, n_reason)
            SELECT seq+1, 7.05, TIMESTAMP('2025-01-01') + INTERVAL MOD(seq, 365) DAY, CONCAT('bench-', LPAD(seq, 7, '0')),
            'Fixture operator', IF(MOD(seq, 10)=0, %d, %d), IF(MOD(seq, 2)=0, '微信', '支付宝'), REPEAT('fixture ', 8)
            FROM ({$numbers}) n", $operator, $admin);
        if (500000 !== $wpdb->query($insert)) { throw new RuntimeException('Fixture insert did not create exactly 500,000 records.'); }
        $cases = array(
            'admin-first' => array($admin, array(), 500000),
            'admin-deep' => array($admin, array('page' => 20001), 500000),
            'owner-deep' => array($operator, array('page' => 2001), 50000),
            'admin-search' => array($admin, array('keyword' => 'bench-004990'), 10),
        );
        foreach ($cases as $label => [$user, $filters, $expected]) {
            wp_set_current_user($user);
            $queries = array();
            $capture = static function ($sql) use (&$queries, $source, $table) {
                if (str_contains($sql, "FROM {$source} WHERE")) {
                    $sql = str_replace("FROM {$source} WHERE", "FROM {$table} WHERE", $sql);
                    $queries[] = $sql;
                }
                return $sql;
            };
            $samples = array();
            for ($i = 0; $i < 6; ++$i) {
                $queries = array();
                add_filter('query', $capture);
                $start = hrtime(true);
                try { $result = Npcink_Pay_Refund_Admin_Query::records_page($filters); }
                finally { remove_filter('query', $capture); }
                if ($expected !== $result['total'] || count($queries) !== 2) { throw new RuntimeException('Unexpected result: ' . $label); }
                if ($i > 0) { $samples[] = (hrtime(true) - $start) / 1e6; }
            }
            sort($samples);
            echo wp_json_encode(array('case' => $label, 'total' => $expected, 'median_ms' => round($samples[2], 2), 'max_ms' => round($samples[4], 2))) . "\n";
            foreach ($queries as $query) {
                foreach ($wpdb->get_col('EXPLAIN ANALYZE ' . $query) as $plan) { echo $plan . "\n"; }
            }
        }
        $wpdb->query("INSERT INTO `{$table}` (id, n_amount, n_time, n_order, n_user, n_type, n_reason)
            VALUES (8388606, 1, '2025-01-01', 'capacity-seed', 'fixture', '微信', 'fixture')");
        $wpdb->query("INSERT INTO `{$table}` (n_amount, n_time, n_order, n_user, n_type, n_reason)
            VALUES (1, '2025-01-01', 'capacity-last', 'fixture', '微信', 'fixture')");
        if (8388607 !== (int) $wpdb->insert_id) { throw new RuntimeException('Unexpected last MEDIUMINT ID.'); }
        $suppress = $wpdb->suppress_errors(true);
        try {
            if (false !== $wpdb->query("INSERT INTO `{$table}` (n_amount, n_time, n_order, n_user, n_type, n_reason)
                VALUES (1, '2025-01-01', 'capacity-overflow', 'fixture', '微信', 'fixture')")) {
                throw new RuntimeException('Expected ID capacity failure.');
            }
        } finally { $wpdb->suppress_errors($suppress); }
        echo "Capacity: assigned 8388607, then rejected the next automatic ID.\n";
    } finally {
        $wpdb->query("DROP TEMPORARY TABLE IF EXISTS `{$table}`");
        wp_set_current_user($initial_user);
        if (is_int($operator)) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($operator);
        }
    }
})();
