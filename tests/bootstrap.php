<?php

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['npcink_test_options'] = array();
$GLOBALS['npcink_test_deleted_options'] = array();

function npcink_test_reset_state()
{
    $GLOBALS['npcink_test_options'] = array();
    $GLOBALS['npcink_test_deleted_options'] = array();
    $GLOBALS['npcink_test_user'] = (object) array('ID' => 7, 'display_name' => 'Operator', 'caps' => array());
    $GLOBALS['npcink_test_nonce'] = false;
    $GLOBALS['wpdb'] = new Npcink_Test_Wpdb();
	if (class_exists('Npcink_Pay_Refund_Admin_Wx', false)) {
		Npcink_Pay_Refund_Admin_Wx::$client = null;
		Npcink_Pay_Refund_Admin_Wx::$merchant_config = array();
	}
	if (class_exists('Npcink_Test_Alipay_Refund', false)) {
		Npcink_Test_Alipay_Refund::reset_provider();
	}
}

function sanitize_text_field($value)
{
    return trim(strip_tags((string) $value));
}

function absint($value)
{
    return abs((int) $value);
}

function esc_sql($value)
{
    return $value;
}

function get_option($name, $default = false)
{
    return array_key_exists($name, $GLOBALS['npcink_test_options']) ? $GLOBALS['npcink_test_options'][$name] : $default;
}

function update_option($name, $value, $autoload = null)
{
    $unchanged = array_key_exists($name, $GLOBALS['npcink_test_options']) && $GLOBALS['npcink_test_options'][$name] === $value;
    $GLOBALS['npcink_test_options'][$name] = $value;
    return !$unchanged;
}

function add_option($name, $value = '', $deprecated = '', $autoload = 'yes')
{
    if (array_key_exists($name, $GLOBALS['npcink_test_options'])) {
        return false;
    }

    $GLOBALS['npcink_test_options'][$name] = $value;
    return true;
}

function delete_option($name)
{
    $GLOBALS['npcink_test_deleted_options'][] = $name;
    $exists = array_key_exists($name, $GLOBALS['npcink_test_options']);
    unset($GLOBALS['npcink_test_options'][$name]);
    return $exists;
}

function current_time($type)
{
    return time();
}

function wp_date($format, $timestamp = null)
{
    return (new DateTimeImmutable('@' . ($timestamp ?? time())))->setTimezone(wp_timezone())->format($format);
}

function __($text, $domain = null)
{
    return $text;
}

function esc_html($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_html__($text, $domain = null)
{
    return esc_html($text);
}

function esc_attr($text)
{
    return esc_html($text);
}

function wp_json_encode($value)
{
    return json_encode($value);
}

function plugin_dir_path($file)
{
    return trailingslashit(dirname($file));
}

function trailingslashit($value)
{
    return rtrim($value, '/\\') . '/';
}

function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function wp_unslash($value) { return $value; }
function wp_get_current_user() { return $GLOBALS['npcink_test_user']; }
function get_current_user_id() { return (int) wp_get_current_user()->ID; }
function user_can($user, $cap) { return !empty($user->caps[$cap]); }
function current_user_can($cap) { return user_can(wp_get_current_user(), $cap); }
function check_ajax_referer($action, $field, $die) { return $GLOBALS['npcink_test_nonce']; }
class Npcink_Test_Json extends RuntimeException {
    public $status;
    public function __construct($status) { parent::__construct('AJAX response'); $this->status = $status; }
}
function wp_send_json_error($data, $status = 200) { throw new Npcink_Test_Json($status); }
function wp_timezone() { return new DateTimeZone($GLOBALS['npcink_test_timezone'] ?? 'UTC'); }
function maybe_serialize($value) { return is_array($value) || is_object($value) ? serialize($value) : (string) $value; }
function wp_cache_delete($key, $group) { return true; }
require dirname(__DIR__) . '/admin/class-npcink-pay-refund-admin.php';
require dirname(__DIR__) . '/admin/partials/npcink-pay-refund-admin-config.php';
require dirname(__DIR__) . '/admin/partials/npcink-pay-refund-admin-authority.php';

class Npcink_Test_Wpdb
{
    public $prefix = 'wp_';
    public $options = 'wp_options';
    public $existing_id = false;
    public $insert_result = 1;
    public $inserted_data = array();

    public function prepare($query, ...$args)
    {
        return array($query, $args);
    }

    public function get_var($query)
    {
        if (is_array($query) && (str_contains($query[0], 'GET_LOCK') || str_contains($query[0], 'IS_USED_LOCK'))) { return 1; }
        return $this->existing_id;
    }

    public function query($prepared)
    {
        list($sql, $args) = $prepared;
        if (str_starts_with($sql, 'UPDATE')) { list($new, $key, $old) = $args; }
        else { list($key, $old) = $args; }
        if (!array_key_exists($key, $GLOBALS['npcink_test_options']) || maybe_serialize($GLOBALS['npcink_test_options'][$key]) !== $old) { return 0; }
        if (str_starts_with($sql, 'UPDATE')) { $GLOBALS['npcink_test_options'][$key] = is_serialized_test($new) ? unserialize($new) : $new; }
        else { delete_option($key); }
        return 1;
    }

    public function update($table, $data, $where, $format = null, $where_format = null) { return 1; }

    public function insert($table, $data)
    {
        $this->inserted_data = $data;
        return $this->insert_result;
    }
}

function is_serialized_test($v) { return preg_match('/^(a|O):/', $v); }

function npcink_test_keys() {
    static $keys;
    if (!$keys) {
        $key = openssl_pkey_new(array('private_key_bits' => 2048));
        openssl_pkey_export($key, $private);
        $keys = array('private' => $private, 'public' => openssl_pkey_get_details($key)['key']);
    }
    return $keys;
}
