<?php
namespace TeleNexa\Bot;

/**
 * Security and Protection Layer for WooBot.
 * Handles rate-limiting, webhook secret verification, signed payment URLs, and idempotency locks.
 */
trait SecurityTrait {

    /**
     * Check rate limit for a specific chat ID.
     * Prevents flooding and DoS attacks.
     *
     * @param string|int $chat_id
     * @param int $max_requests Default 40 per minute
     * @param int $window_seconds Default 60 seconds
     * @return bool True if allowed, False if exceeded
     */
    public static function checkRateLimit($chat_id, $max_requests = 40, $window_seconds = 60) {
        if (woogram_option('security_rate_limit_enable') === '0') {
            return true;
        }

        $chat_id = self::fixPersianChar((string) $chat_id);
        if ($chat_id === '') return true;

        $transient_key = 'woogram_rate_' . md5($chat_id);
        $current_hits = (int) get_transient($transient_key);

        if ($current_hits >= $max_requests) {
            self::log("RATE_LIMIT: chat_id {$chat_id} exceeded {$max_requests} reqs/{$window_seconds}s");
            return false;
        }

        set_transient($transient_key, $current_hits + 1, $window_seconds);
        return true;
    }

    /**
     * Validate Telegram Webhook Secret Token header (HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN).
     */
    public static function validateWebhookSecretToken() {
        $expected_secret = woogram_option('webhook_secret_token');
        $expected_secret = (string) $expected_secret;
        if ($expected_secret === '') {
            return true; // No secret configured, pass
        }

        $received_secret = isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']) && is_string($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']) ? $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] : '';
        if (function_exists('update_option')) update_option('woogram_webhook_header_status', [
            'status' => $received_secret === '' ? 'missing' : (hash_equals($expected_secret, $received_secret) ? 'verified' : 'mismatch'),
            'checked_at' => gmdate('c'),
        ], false);
        if (!hash_equals((string) $expected_secret, (string) $received_secret)) {
            self::log($received_secret === ''
                ? 'SECURITY_ALERT: Webhook security header missing. Check webhook registration and relay header forwarding.'
                : 'SECURITY_ALERT: Webhook security header mismatch. Re-register webhook with saved settings.');
            return false;
        }
        return true;
    }

    /**
     * Validate Telegram WebApp initData string using HMAC-SHA256 according to Telegram specifications.
     * Prevents chat_id and user impersonation in Mini App requests.
     *
     * @param string $init_data The raw initData query string sent by Telegram.WebApp.initData
     * @param string $bot_token Optional bot token (defaults to plugin option)
     * @return array|false Returns parsed user array on success, false on failure or expiration
     */
    public static function validateTelegramWebAppInitData($init_data, $bot_token = '') {
        if (empty($init_data)) {
            return false;
        }

        if (empty($bot_token)) {
            $bot_token = woogram_option('token');
        }

        if (empty($bot_token)) {
            return false;
        }

        parse_str($init_data, $params);
        if (empty($params['hash'])) {
            return false;
        }

        $received_hash = $params['hash'];
        unset($params['hash']);

        // Check auth_date expiration (valid within 24 hours / 86400 seconds)
        if (empty($params['auth_date']) || abs(time() - (int)$params['auth_date']) > 86400) {
            self::log('SECURITY_ALERT: WebApp initData auth_date expired or missing');
            return false;
        }

        // Sort keys alphabetically
        ksort($params);

        // Build data_check_string
        $data_check_arr = [];
        foreach ($params as $key => $value) {
            $data_check_arr[] = "{$key}={$value}";
        }
        $data_check_string = implode("\n", $data_check_arr);

        // Step 1: secret_key = HMAC-SHA256("WebAppData", bot_token) (raw binary)
        $secret_key = hash_hmac('sha256', $bot_token, 'WebAppData', true);

        // Step 2: calculated_hash = HMAC-SHA256(data_check_string, secret_key) (hex)
        $calculated_hash = hash_hmac('sha256', $data_check_string, $secret_key);

        if (!hash_equals($calculated_hash, $received_hash)) {
            self::log('SECURITY_ALERT: WebApp initData HMAC-SHA256 verification failed');
            return false;
        }

        // Parse user JSON if present
        $user = [];
        if (!empty($params['user'])) {
            $user = json_decode($params['user'], true) ?: [];
        }

        return [
            'user'      => $user,
            'auth_date' => (int) $params['auth_date'],
            'params'    => $params,
        ];
    }

    /**
     * Verify security of a Mini App request via Telegram initData or WordPress nonce.
     * Prevents chat_id spoofing and unauthorized access to customer orders.
     *
     * @param string $requested_chat_id
     * @param bool $require_telegram_auth Require signed Telegram initData.
     * @return array|\WP_Error
     */
    public static function verifyWebAppSecurity($requested_chat_id = '', $require_telegram_auth = false) {
        $init_data = isset($_REQUEST['init_data']) ? wp_unslash($_REQUEST['init_data']) : '';
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field($_REQUEST['nonce']) : '';

        // 1. If Telegram initData is provided, authenticate via Telegram cryptographic signature
        if (!empty($init_data)) {
            $validated = self::validateTelegramWebAppInitData($init_data);
            if ($validated === false) {
                return new \WP_Error('invalid_telegram_auth', self::botTranslate('Invalid or expired Telegram WebApp authentication data.', 'telenexa-commerce-for-telegram'));
            }

            $auth_chat_id = isset($validated['user']['id']) ? (string)$validated['user']['id'] : '';
            if (empty($auth_chat_id)) {
                return new \WP_Error('missing_telegram_user', self::botTranslate('No user identity found in Telegram authentication data.', 'telenexa-commerce-for-telegram'));
            }

            // Strictly enforce chat_id ownership: if customer passed a chat_id, it must match
            if (!empty($requested_chat_id) && (string)$requested_chat_id !== $auth_chat_id) {
                self::log("SECURITY_ALERT: chat_id mismatch in WebApp request. Authenticated: {$auth_chat_id}, Requested: {$requested_chat_id}");
                return new \WP_Error('chat_id_mismatch', self::botTranslate('Access denied: You cannot access or modify orders of another user.', 'telenexa-commerce-for-telegram'));
            }

            return [
                'success' => true,
                'chat_id' => $auth_chat_id,
                'user'    => $validated['user'],
            ];
        }

        // Sensitive endpoints must never trust a browser nonce as a Telegram identity.
        if ($require_telegram_auth) {
            return new \WP_Error('telegram_auth_required', self::botTranslate('Please open the Mini App inside Telegram.', 'telenexa-commerce-for-telegram'));
        }

        // 2. Fallback: for public catalog/shipping previews or administrator testing,
        // verify nonce or administrator capability.
        if (!empty($nonce) && wp_verify_nonce($nonce, 'woogram_webapp_nonce')) {
            return [
                'success' => true,
                'chat_id' => sanitize_text_field($requested_chat_id),
                'user'    => [],
            ];
        }

        if (current_user_can('manage_options')) {
            return [
                'success' => true,
                'chat_id' => sanitize_text_field($requested_chat_id),
                'user'    => [],
            ];
        }

        return new \WP_Error('unauthorized', self::botTranslate('Unauthorized request. Please open the Mini App inside Telegram.', 'telenexa-commerce-for-telegram'));
    }

    /**
     * Generate a cryptographically signed checkout URL with expiration.
     * Prevents order ID tampering and payment hijacking.
     */
    public static function generateSignedPaymentUrl($order_id, $payment_method = '', $ttl_seconds = 7200) {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            return '';
        }

        $order_key = $order->get_order_key();
        $expires = time() + max(300, absint($ttl_seconds));
        $salt = wp_salt('auth');

        $data_to_sign = "{$order_id}|{$order_key}|{$payment_method}|{$expires}";
        $signature = hash_hmac('sha256', $data_to_sign, $salt);

        return add_query_arg([
            'woogram_payment' => $payment_method,
            'order_id'        => (int) $order_id,
            'key'             => $order_key,
            'expires'         => $expires,
            'sig'             => $signature,
        ], home_url('/'));
    }

    /**
     * Verify the signature and timestamp of a payment redirect request.
     */
    public static function verifyPaymentSignature($order_id, $order_key, $payment_method, $expires, $signature) {
        if (empty($signature) || empty($expires) || empty($order_id) || empty($order_key)) {
            return false;
        }

        // Check expiration
        if (time() > (int) $expires) {
            return false;
        }

        $salt = wp_salt('auth');
        $expected_data = "{$order_id}|{$order_key}|{$payment_method}|{$expires}";
        $expected_sig = hash_hmac('sha256', $expected_data, $salt);

        return hash_equals($expected_sig, (string) $signature);
    }

    /**
     * Acquire an idempotency checkout lock to prevent duplicate order placements.
     *
     * @param string|int $chat_id
     * @param int $lock_ttl_seconds
     * @return bool True if acquired, False if already locked
     */
    public static function acquireOrderLock($chat_id, $lock_ttl_seconds = 30) {
        $lock_key = 'woogram_order_lock_' . md5((string) $chat_id);
        if (get_transient($lock_key)) {
            return false;
        }
        set_transient($lock_key, time(), $lock_ttl_seconds);
        return true;
    }

    /**
     * Release the idempotency checkout lock.
     */
    public static function releaseOrderLock($chat_id) {
        $lock_key = 'woogram_order_lock_' . md5((string) $chat_id);
        delete_transient($lock_key);
    }
}
