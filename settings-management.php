<?php
/** Settings validation, connection synchronization and private diagnostics. */
if (!defined('ABSPATH')) exit;

/**
 * Move persisted data from pre-WooGram releases without dropping a store's
 * bot token, webhook URL, subscribers, carts or wishlist information.
 *
 * Legacy names are deliberately assembled here rather than used elsewhere:
 * this is the single, removable compatibility boundary for upgrades.
 */
function woogram_migrate_legacy_storage() {
    // This file is also loaded by lightweight diagnostics/tests. Migration is
    // meaningful only after the complete WordPress Options API is available.
    if (!function_exists('get_option') || !function_exists('add_option') || !function_exists('update_option')) return;
    if (get_option('woogram_storage_migrated')) return;

    $legacy_settings = 'wp_' . 'sale' . 'telegram' . 'bot';
    $legacy_key = $legacy_settings . '_apikey';
    $legacy_version = $legacy_settings . '_option_ver';
    $legacy_user_type = 'sale' . 'tbot_user';
    $legacy_group_type = 'sale' . 'tbot_group';

    $settings = get_option('woogram_settings', null);
    if (!is_array($settings)) {
        $settings = get_option($legacy_settings, []);
        if (is_array($settings)) add_option('woogram_settings', $settings, '', false);
    }
    if (!get_option('woogram_webhook_key')) {
        $key = get_option($legacy_key);
        if (is_string($key) && $key !== '') add_option('woogram_webhook_key', $key, '', false);
    }
    if (!get_option('woogram_option_version')) {
        $version = get_option($legacy_version);
        if (is_string($version) && $version !== '') add_option('woogram_option_version', $version, '', false);
    }

    global $wpdb;
    if (isset($wpdb) && isset($wpdb->posts, $wpdb->postmeta, $wpdb->usermeta)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy migration.
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s", 'woogram_user', $legacy_user_type));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy migration.
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s", 'woogram_group', $legacy_group_type));
        foreach (['_saved_cart', '_wishlist', '_wishlist_prices', '_stock_subscribers'] as $suffix) {
            $legacy_meta_key = '_' . 'sale' . 'tbot' . $suffix;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy migration.
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s", '_woogram' . $suffix, $legacy_meta_key));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time legacy migration.
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s", '_woogram' . $suffix, $legacy_meta_key));
        }
    }

    $legacy_logs = get_option('wp_' . 'sale' . '_telegram' . '_bot_structured_logs', null);
    if (is_array($legacy_logs) && !get_option('wp_woogram_activity_log')) {
        add_option('wp_woogram_activity_log', $legacy_logs, '', false);
    }
    update_option('woogram_storage_migrated', gmdate('c'), false);
}

function woogram_private_key($key) {
    return (bool) preg_match('/token|password|secret|authorization|api_?key|plugin_license/i', (string) $key);
}

function woogram_export_options(array $options) {
    foreach ($options as $key => $value) {
        if (woogram_private_key($key) || preg_match('/proxy_(address|username)/', $key)) {
            unset($options[$key]);
        } elseif (is_array($value)) {
            $options[$key] = woogram_export_options($value);
        }
    }
    return $options;
}

function woogram_redact($value, $key = '') {
    if (in_array($key, ['command', 'route'], true) && is_string($value) && preg_match('~\A/[A-Za-z_]+[0-9]*(?:V[0-9]+)?\z~', $value)) return $value;
    if (woogram_private_key($key) || preg_match('/\A(?:chat_id|username|phone|email|address|first_name|last_name|input|command|route|text)\z/i', $key)) return '[redacted]';
    if (is_array($value)) {
        foreach ($value as $k => $v) $value[$k] = woogram_redact($v, (string) $k);
        return $value;
    }
    if (!is_string($value)) return $value;
    $options = (array) get_option('woogram_settings', []);
    foreach ($options as $k => $v) {
        if (woogram_private_key($k) && is_scalar($v) && (string) $v !== '') $value = str_replace((string) $v, '[redacted]', $value);
    }
    $value = preg_replace('~https?://[^\s<>"\']+~i', '[url redacted]', $value);
    $value = preg_replace('/\b[0-9]{5,}:[A-Za-z0-9_-]+\b/', '[token redacted]', $value);
    $value = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email redacted]', $value);
    $value = preg_replace('/\b\d{7,}\b/', '[number redacted]', $value);
    return function_exists('mb_substr') ? mb_substr($value, 0, 500) : substr($value, 0, 500);
}

function woogram_validate_settings(array $input, array $previous) {
    $rules = [
        'token' => '/\A[0-9]+:[A-Za-z0-9_-]+\z/',
        'webhook_secret_token' => '/\A[A-Za-z0-9_-]{1,256}\z/',
        'username' => '/\A@?[A-Za-z0-9_]{5,32}\z/',
    ];
    foreach ($input as $key => $value) {
        $invalid = false;
        if (isset($rules[$key])) {
            $invalid = !is_scalar($value) || ((string) $value !== '' && !preg_match($rules[$key], (string) $value));
        } elseif (preg_match('/(?:url|_link)$/', $key)) {
            $invalid = !is_scalar($value) || ((string) $value !== '' && (!filter_var($value, FILTER_VALIDATE_URL) || !in_array(strtolower((string) wp_parse_url($value, PHP_URL_SCHEME)), ['https', 'http'], true)));
        }
        if ($invalid) {
            $input[$key] = $previous[$key] ?? '';
            // translators: %s: Setting field key name.
            add_settings_error('woogram_settings', 'invalid_' . $key, sprintf(__('Invalid value for %s. The previous value was preserved.', 'telenexa-commerce-for-telegram'), $key));
        }
    }
    $ranges = ['display_productsperpage' => [1, 20], 'display_product_excerpt_length' => [0, 1000], 'proxy_port' => [1, 65535], 'proxy_port_fallback' => [1, 65535], 'telegram_http_timeout' => [5, 20], 'telegram_api_retries' => [0, 2], 'woogram_log_retention_days' => [1, 30]];
    foreach ($ranges as $key => $range) {
        if (isset($input[$key])) {
            if (!is_scalar($input[$key]) || !is_numeric($input[$key])) {
                // translators: %s: Setting field key name.
                add_settings_error('woogram_settings', 'invalid_' . $key, sprintf(__('Invalid value for %s. The previous value was preserved.', 'telenexa-commerce-for-telegram'), $key));
                $input[$key] = $previous[$key] ?? $range[0];
            }
            $input[$key] = (string) max($range[0], min($range[1], (int) $input[$key]));
        }
    }
    $enums = [
        'newest_orderby' => ['date', 'modified', 'popularity', 'price'],
        'search_default_sort' => ['date', 'popularity', 'price_asc', 'price_desc', 'on_sale'],
        'woogram_log_level' => ['errors', 'all'],
        'mode' => ['0', '1'],
        'default_language' => ['fa', 'en', 'de', 'es', 'fr', 'it', 'pt', 'tr', 'ar', 'ru', 'zh', 'ja'],
        'wpml_language_sync' => ['0', '1'],
    ];
    foreach ($enums as $key => $values) {
        if (isset($input[$key]) && !in_array((string) $input[$key], $values, true)) $input[$key] = $previous[$key] ?? $values[0];
    }
    return $input;
}

function woogram_sanitize_membership_requirements($requirements) {
    if (!is_array($requirements)) return [];
    $clean = [];
    foreach (array_slice($requirements, 0, 10) as $requirement) {
        if (!is_array($requirement)) continue;
        $chat_id = sanitize_text_field($requirement['chat_id'] ?? '');
        if ($chat_id === '' || !preg_match('/\A(?:@?[A-Za-z0-9_]{4,}|-?\d{5,})\z/', $chat_id)) continue;
        $url = esc_url_raw($requirement['url'] ?? '');
        if ($url !== '' && !in_array(strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true)) $url = '';
        $clean[] = [
            'enabled' => !empty($requirement['enabled']) ? '1' : '0',
            'type'    => in_array(($requirement['type'] ?? 'channel'), ['channel', 'group'], true) ? $requirement['type'] : 'channel',
            'title'   => sanitize_text_field($requirement['title'] ?? ''),
            'chat_id' => $chat_id,
            'url'     => $url,
        ];
    }
    return $clean;
}

function woogram_connection_values($options) {
    $options = (array) $options;
    $values = [];
    foreach (['token', 'webhook_secret_token', 'mode', 'testmod', 'proxy_status', 'proxy_address', 'proxy_port', 'proxy_type', 'proxy_username', 'proxy_password_enc', 'proxy_status_fallback', 'proxy_address_fallback', 'proxy_port_fallback', 'proxy_type_fallback', 'proxy_username_fallback', 'proxy_password_enc_fallback'] as $key) $values[$key] = (string) ($options[$key] ?? '');
    return $values;
}

function woogram_sync_changed_connection($old, $new) {
    if (defined('WOOGRAM_MIGRATING_STORAGE') || (function_exists('did_action') && !did_action('init'))) return;
    if (woogram_connection_values($old) === woogram_connection_values($new) || empty($new['token'])) return;
    if (($old['webhook_secret_token'] ?? '') !== ($new['webhook_secret_token'] ?? '') || ($old['token'] ?? '') !== ($new['token'] ?? '')) delete_option('woogram_webhook_header_status');
    $direct = ($new['mode'] ?? '0') == 0 && ($new['testmod'] ?? 'no') !== 'yes';
    $result = \TeleNexa\WooBot::setHook($direct ? 1 : 0);
    update_option('woogram_webhook_sync_status', ['ok' => $result !== false, 'checked_at' => gmdate('c')], false);
}
add_action('update_option_woogram_settings', 'woogram_sync_changed_connection', 10, 2);
add_action('add_option_woogram_settings', function ($name, $value) { woogram_sync_changed_connection([], $value); }, 10, 2);

function woogram_prune_logs() {
    $days = max(1, min(30, (int) (woogram_option('woogram_log_retention_days') ?: 7)));
    $cutoff = time() - $days * 86400;
    $logs = (array) get_option('wp_woogram_activity_log', []);
    foreach ($logs as &$entry) {
        if (is_array($entry)) {
            if (!isset($entry['timestamp'])) {
                $date = date_create_immutable($entry['time'] ?? '', wp_timezone());
                $entry['timestamp'] = $date ? $date->getTimestamp() : 0;
            }
            $entry = woogram_redact($entry);
        }
    }
    unset($entry);
    $logs = array_values(array_filter($logs, function ($entry) use ($cutoff) {
        return is_array($entry) && isset($entry['timestamp']) && (int) $entry['timestamp'] >= $cutoff;
    }));
    update_option('wp_woogram_activity_log', array_slice($logs, -500), false);
    // Retire duplicate stores that contained unredacted free-form messages.
    delete_option('wp_woogram_log');
    delete_option('wp_woogram_structured_logs');
    return $logs;
}
add_action('woogram_prune_logs', 'woogram_prune_logs');
add_action('admin_init', function () {
    woogram_prune_logs();
    if (!wp_next_scheduled('woogram_prune_logs')) wp_schedule_event(time() + 86400, 'daily', 'woogram_prune_logs');
});
add_action('admin_post_woogram_download_logs', function () {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Permission denied', 'telenexa-commerce-for-telegram'));
    check_admin_referer('woogram_download_logs');
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="woogram-diagnostics.json"');
    echo wp_json_encode(woogram_redact(woogram_prune_logs()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
});
add_action('admin_post_woogram_register_webhook', function () {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Permission denied', 'telenexa-commerce-for-telegram'));
    check_admin_referer('woogram_register_webhook');
    $options = (array) get_option('woogram_settings', []);
    woogram_sync_changed_connection([], $options);
    wp_safe_redirect(admin_url('admin.php?page=woogram_settings&settings-updated=1'));
    exit;
});

woogram_migrate_legacy_storage();
