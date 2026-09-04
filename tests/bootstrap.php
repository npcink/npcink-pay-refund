<?php

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['npcink_test_options'] = array();
$GLOBALS['npcink_test_deleted_options'] = array();

function npcink_test_reset_state()
{
    $GLOBALS['npcink_test_options'] = array();
    $GLOBALS['npcink_test_deleted_options'] = array();
    Npcink_Pay_Refund_Admin::$configs = array();
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
    return date($format, null === $timestamp ? time() : $timestamp);
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

class Npcink_Pay_Refund_Admin
{
    public static $configs = array();

    public static function npcConfig($section)
    {
        return isset(self::$configs[$section]) ? self::$configs[$section] : array();
    }

    public static function get_options($config, $key, $default = '')
    {
        return is_array($config) && array_key_exists($key, $config) ? $config[$key] : $default;
    }

    public static function sanitize_textarea_value($value)
    {
        return trim(strip_tags((string) $value));
    }
}

class Npcink_Test_Wpdb
{
    public $prefix = 'wp_';
    public $existing_id = false;
    public $insert_result = 1;
    public $inserted_data = array();

    public function prepare($query, ...$args)
    {
        return $query . '|' . implode('|', array_map('strval', $args));
    }

    public function get_var($query)
    {
        return $this->existing_id;
    }

    public function insert($table, $data)
    {
        $this->inserted_data = $data;
        return $this->insert_result;
    }
}
