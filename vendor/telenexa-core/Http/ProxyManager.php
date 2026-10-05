<?php

namespace TeleNexa\Http;

/**
 * Central proxy configuration, encryption, failover and health reporting.
 * The WordPress HTTP API remains the transport; this class only applies
 * proxy options to cURL requests while keeping the rest of the plugin stable.
 */
class ProxyManager {
    private static $active_profile = null;
    private static $last_profile_name = '';

    public static function profiles($options = null) {
        // Keep the adapter usable by the standalone transport tests and by
        // non-WordPress tooling; in WordPress get_option is always available.
        if ($options === null && !function_exists('get_option')) return [];
        $options = is_array($options) ? $options : (array) get_option('woogram_settings', []);
        $profiles = [];
        foreach (['primary' => '', 'fallback' => '_fallback'] as $name => $suffix) {
            $enabled_key = 'proxy_status' . $suffix;
            $address_key = 'proxy_address' . $suffix;
            $address = trim((string) ($options[$address_key] ?? ''));
            if (empty($options[$enabled_key]) || $address === '') {
                continue;
            }
            $profiles[$name] = [
                'name'     => $name,
                'type'     => self::normalizeType($options['proxy_type' . $suffix] ?? 'http'),
                'address'  => self::normalizeAddress($address),
                'port'     => max(1, min(65535, (int) ($options['proxy_port' . $suffix] ?? 8080))),
                'username' => trim((string) ($options['proxy_username' . $suffix] ?? '')),
                'password' => self::decrypt((string) ($options['proxy_password_enc' . $suffix] ?? ($options['proxy_password' . $suffix] ?? ''))),
            ];
        }
        return $profiles;
    }

    public static function request($url, array $args = []) {
        $profiles = self::profiles();
        if (empty($profiles)) {
            return wp_remote_post($url, $args);
        }

        $last_error = null;
        foreach ($profiles as $name => $profile) {
            $started = microtime(true);
            self::$active_profile = $profile;
            add_filter('http_api_curl', [self::class, 'configureCurl'], 10, 3);
            $response = wp_remote_post($url, $args);
            remove_filter('http_api_curl', [self::class, 'configureCurl'], 10);
            self::$active_profile = null;

            if (!is_wp_error($response)) {
                self::$last_profile_name = $name;
                self::record($name, true, '', (int) round((microtime(true) - $started) * 1000));
                return $response;
            }
            $last_error = $response;
            self::record($name, false, $response->get_error_message(), (int) round((microtime(true) - $started) * 1000));
        }
        return $last_error ?: new \WP_Error('woogram_proxy_failed', __('All configured proxies failed.', 'telenexa-commerce-for-telegram'));
    }

    public static function configureCurl($handle) {
        $profile = self::$active_profile;
        if (!$profile || !function_exists('curl_setopt')) {
            return $handle;
        }
        if (defined('CURLOPT_PROXY')) curl_setopt($handle, CURLOPT_PROXY, $profile['address']);
        if (defined('CURLOPT_PROXYPORT')) curl_setopt($handle, CURLOPT_PROXYPORT, $profile['port']);
        if (defined('CURLOPT_PROXYTYPE')) curl_setopt($handle, CURLOPT_PROXYTYPE, self::curlType($profile['type']));
        if (defined('CURLOPT_HTTPPROXYTUNNEL')) curl_setopt($handle, CURLOPT_HTTPPROXYTUNNEL, $profile['type'] === 'https');
        if ($profile['username'] !== '' && defined('CURLOPT_PROXYUSERPWD')) {
            curl_setopt($handle, CURLOPT_PROXYUSERPWD, $profile['username'] . ':' . $profile['password']);
        }
        return $handle;
    }

    public static function test() {
        $started = microtime(true);
        $response = TelegramApiAdapter::request('getMe');
        $elapsed = (int) round((microtime(true) - $started) * 1000);
        return [
            'ok'      => $response && $response->isOk(),
            'ms'      => $elapsed,
            'proxy'   => self::activeName(),
            'message' => $response && $response->isOk() ? __('Connection successful.', 'telenexa-commerce-for-telegram') : ($response ? $response->getDescription() : __('No response.', 'telenexa-commerce-for-telegram')),
        ];
    }

    public static function activeName() {
        if (self::$last_profile_name !== '') return self::$last_profile_name;
        $profiles = self::profiles();
        if (empty($profiles)) return __('Direct connection', 'telenexa-commerce-for-telegram');
        $health = get_option('wp_woogram_proxy_health', []);
        foreach ($profiles as $name => $profile) {
            if (!empty($health[$name]['ok'])) return $name;
        }
        return array_key_first($profiles);
    }

    public static function health() {
        $health = get_option('wp_woogram_proxy_health', []);
        return is_array($health) ? $health : [];
    }

    public static function record($name, $ok, $error = '', $latency = 0) {
        $health = self::health();
        $health[$name] = [
            'ok'         => (bool) $ok,
            'error'      => sanitize_text_field($error),
            'last_check' => current_time('mysql'),
            'latency_ms' => (int) $latency,
        ];
        update_option('wp_woogram_proxy_health', $health, false);
    }

    public static function encrypt($value) {
        $value = (string) $value;
        if ($value === '') return '';
        $key = hash('sha256', wp_salt('auth'), true);
        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($cipher !== false) return 'gcm:' . base64_encode($iv . $tag . $cipher);
        }
        return 'plain:' . base64_encode($value);
    }

    public static function decrypt($value) {
        if ($value === '') return '';
        if (strpos($value, 'plain:') === 0) return (string) base64_decode(substr($value, 6));
        if (strpos($value, 'gcm:') !== 0 || !function_exists('openssl_decrypt')) return '';
        $raw = base64_decode(substr($value, 4), true);
        if (!is_string($raw) || strlen($raw) < 29) return '';
        $key = hash('sha256', wp_salt('auth'), true);
        return (string) openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    }

    private static function normalizeType($type) {
        return in_array($type, ['http', 'https', 'socks4', 'socks5'], true) ? $type : 'http';
    }

    private static function normalizeAddress($address) {
        $address = preg_replace('#^[a-z]+://#i', '', $address);
        return trim(strtok($address, '/'));
    }

    private static function curlType($type) {
        if ($type === 'socks5') return defined('CURLPROXY_SOCKS5_HOSTNAME') ? CURLPROXY_SOCKS5_HOSTNAME : 7;
        if ($type === 'socks4') return defined('CURLPROXY_SOCKS4A') ? CURLPROXY_SOCKS4A : 6;
        return defined('CURLPROXY_HTTP') ? CURLPROXY_HTTP : 0;
    }
}
