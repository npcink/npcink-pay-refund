<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Operational breadcrumbs, separate from authoritative refund records. */
final class Npcink_Pay_Refund_Admin_Audit
{
    private static $payment = null;

    public static function start_payment($action, $order)
    {
        self::$payment = array('action' => $action);
        if ('' !== $order) {
            self::$payment['order_ref'] = hash_hmac('sha256', $order, wp_salt('auth'));
        }
    }

    public static function payment_error($data, $status)
    {
        self::finish_payment('error', $status);
        wp_send_json_error($data, $status);
    }

    public static function payment_success($data)
    {
        self::finish_payment('success', 200);
        wp_send_json_success($data);
    }

    private static function finish_payment($result, $status)
    {
        if (null !== self::$payment) {
            $event = self::$payment + array('result' => $result, 'http_status' => $status);
            self::$payment = null;
            self::write($event);
        }
    }

    public static function export_result($row_count = null, $truncated = false)
    {
        $event = array('action' => 'records_export', 'result' => null === $row_count ? 'error' : 'success');
        if (null !== $row_count) {
            $event += array('row_count' => $row_count, 'truncated' => $truncated);
        }
        self::write($event);
    }

    public static function change_operator_capability($user, $enabled)
    {
        $cap = 'npcink_refund_orders';
        $before = (bool) ($user->caps[$cap] ?? false);
        if ($enabled) {
            $user->add_cap($cap);
        } else {
            $user->remove_cap($cap);
        }
        $after = (bool) ($user->caps[$cap] ?? false);
        if ($before !== $after) {
            self::write(array('action' => $after ? 'capability_grant' : 'capability_revoke', 'target_user_id' => (int) $user->ID));
        }
    }

    private static function write($event)
    {
        $event += array('site_id' => get_current_blog_id(), 'actor_id' => get_current_user_id(), 'time_utc' => gmdate('c'));
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operator-controlled PHP log; never write payloads or secrets.
        error_log('Npcink_Pay_Refund operation ' . wp_json_encode($event));
    }
}
