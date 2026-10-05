<?php

namespace TeleNexa\Http;

/**
 * Small compatibility layer for Telegram Bot API methods that may not exist
 * in Telegram Bot API versions that predate the current adapter.
 *
 * It deliberately uses the WordPress HTTP API so new Telegram methods do not
 * depend on the version of Guzzle bundled by an older Telegram library.
 */
class TelegramApiAdapter {

    /**
     * Compatibility response for the existing bot code. The transport is
     * WordPress HTTP and TeleNexa's internal response entities.
     */
    public static function request($method, array $params = []) {
        if ($method === 'getInput') {
            return file_get_contents('php://input');
        }

        $response = self::call($method, $params);
        if (is_wp_error($response)) {
            $data = $response->get_error_data();
            $raw = is_array($data) && isset($data['response']) && is_array($data['response'])
                ? $data['response'] : [];
            $raw = array_merge([
                'ok'          => false,
                'description' => $response->get_error_message(),
                'error_code'  => isset($data['http_status']) ? (int) $data['http_status'] : 0,
            ], $raw);
        } else {
            $raw = $response;
        }

        return new \TeleNexa\Telegram\Response(
            is_array($raw) ? $raw : ['ok' => false, 'description' => __('Invalid Telegram response.', 'telenexa-commerce-for-telegram')],
            (string) woogram_option('username')
        );
    }

    /**
     * Call a Telegram Bot API method with bounded retry for rate limiting.
     *
     * @return array|WP_Error
     */
    public static function call($method, array $params = [], $retries = null) {
        $token = (string) woogram_option('token');
        // Telegram method names are case-sensitive (for example
        // refundStarPayment), so do not use sanitize_key() here.
        $method = preg_replace('/[^A-Za-z0-9_]/', '', (string) $method);

        if ($token === '' || $method === '') {
            return new \WP_Error('woogram_telegram_config', __('Telegram API is not configured.', 'telenexa-commerce-for-telegram'));
        }

        $url = 'https://api.telegram.org/bot' . rawurlencode($token) . '/' . $method;
        if ($retries === null) {
            $retries = min(2, absint(woogram_option('telegram_api_retries')));
        }
        $attempt = 0;

        do {
            $request_started = microtime(true);
            $request_args = [
                'timeout'   => max(5, min(20, absint(woogram_option('telegram_http_timeout')) ?: 6)),
                'sslverify' => true,
                'headers'   => [
                    'Accept'       => 'application/json',
                    // Send nested Telegram structures as real JSON arrays.
                    // Form encoding can turn reply_markup.inline_keyboard
                    // into a string on some WordPress HTTP transports.
                    'Content-Type' => 'application/json; charset=UTF-8',
                ],
                'body'      => wp_json_encode(self::normalizeValue($params), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            $response = ProxyManager::request($url, $request_args);

            if (is_wp_error($response)) {
                self::logEvent('telegram_api_error', ['method' => $method, 'attempt' => $attempt + 1, 'duration_ms' => (int) round((microtime(true) - $request_started) * 1000), 'error' => $response->get_error_message()]);
                if ($attempt < (int) $retries) {
                    ++$attempt;
                    usleep(250000 * $attempt);
                    continue;
                }
                return $response;
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $body = is_array($body) ? $body : [];

            if ($status === 429 && $attempt < (int) $retries) {
                $retry_after = isset($body['parameters']['retry_after'])
                    ? max(1, min(10, absint($body['parameters']['retry_after'])))
                    : ($attempt + 1);
                $retry_after = min(3, $retry_after);
                ++$attempt;
                sleep($retry_after);
                continue;
            }

            if ($status < 200 || $status >= 300 || empty($body['ok'])) {
                self::logEvent('telegram_api_response_error', ['method' => $method, 'status' => $status, 'duration_ms' => (int) round((microtime(true) - $request_started) * 1000), 'description' => $body['description'] ?? '']);
                return new \WP_Error(
                    'woogram_telegram_api',
                    isset($body['description']) ? sanitize_text_field($body['description']) : __('Telegram API request failed.', 'telenexa-commerce-for-telegram'),
                    [
                        'http_status' => $status,
                        'response'    => $body,
                    ]
                );
            }

            self::logEvent('telegram_api_success', ['method' => $method, 'status' => $status, 'duration_ms' => (int) round((microtime(true) - $request_started) * 1000), 'proxy' => ProxyManager::activeName()]);
            return $body;
        } while ($attempt <= (int) $retries);

        return new \WP_Error('woogram_telegram_retry_exhausted', __('Telegram API retry limit reached.', 'telenexa-commerce-for-telegram'));
    }

    public static function sendInvoice(array $params) {
        return self::call('sendInvoice', $params);
    }

    public static function answerPreCheckoutQuery(array $params) {
        return self::call('answerPreCheckoutQuery', $params);
    }

    public static function refundStarPayment(array $params) {
        return self::call('refundStarPayment', $params);
    }

    public static function setChatMenuButton(array $params) {
        return self::call('setChatMenuButton', $params);
    }

    private static function normalizeParams(array $params) {
        $normalized = [];
        foreach ($params as $key => $value) {
            $value = self::normalizeValue($value);
            $normalized[$key] = is_array($value) || is_object($value)
                ? wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : $value;
        }
        return $normalized;
    }

    private static function logEvent($event, array $context) {
        if (function_exists('woogram_log_event')) woogram_log_event($event, $context);
    }

    private static function normalizeValue($value) {
        if (is_object($value) && method_exists($value, 'getRawData')) {
            $value = $value->getRawData();
        }
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = self::normalizeValue($item);
            }
            return $normalized;
        }
        if (is_object($value)) {
            return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $value;
    }
}
