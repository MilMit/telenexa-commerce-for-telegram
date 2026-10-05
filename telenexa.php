<?php
/*
Plugin Name:  TeleNexa Commerce for Telegram
Description:  A professional Telegram storefront and order management bot for WooCommerce.
Requires at least: 5.8
Requires PHP: 7.4
Requires Plugins: woocommerce
Version:      1.0.0
Author:       MilMit
Author URI:   https://milmit.net
License:      GPL-2.0-or-later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  telenexa-commerce-for-telegram
Domain Path:  /languages
*/

if (!defined('ABSPATH')) exit;

$woogram_version = '1.0.0';

function woogram_environment_check() {
    $minimum_php = '7.4.0';
    if (version_compare(phpversion(), $minimum_php, '<')) {
        // translators: 1: Minimum required PHP version, 2: Current PHP version.
        $message = __('TeleNexa Commerce requires PHP version %1$s or higher. You are running %2$s.', 'telenexa-commerce-for-telegram');
        return sprintf($message, $minimum_php, phpversion());
    }
    $required_extensions = ['curl', 'mbstring', 'pdo'];
    $missing_extensions = array_values(array_filter($required_extensions, function($extension) {
        return !extension_loaded($extension);
    }));
    if (!empty($missing_extensions)) {
        return sprintf(
            // translators: %s: Comma-separated list of missing PHP extensions.
            __('TeleNexa Commerce recommends these PHP extensions: %s. Please enable them for full functionality.', 'telenexa-commerce-for-telegram'),
            implode(', ', $missing_extensions)
        );
    }
    return false;
}

function woogram_admin_notices($message, $class = 'notice notice-warning is-dismissible') {
    if (strpos($class, 'is-dismissible') === false) {
        $class .= ' is-dismissible';
    }
    echo "<div class='" . esc_attr($class) . "'><p>" . wp_kses($message, ['a' => ['href' => [], 'target' => [], 'rel' => []]]) . "</p></div>";
}

add_action('plugins_loaded', function() {
    load_plugin_textdomain('telenexa-commerce-for-telegram', false, dirname(plugin_basename(__FILE__)) . '/languages');
    load_plugin_textdomain('telenexa', false, dirname(plugin_basename(__FILE__)) . '/languages');

    if (($message = woogram_environment_check())) {
        add_action('admin_notices', function() use ($message) {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen && !in_array($screen->id, ['plugins', 'toplevel_page_woogram_main', 'telenexa_page_woogram_settings', 'woocommerce_page_woogram_main', 'woocommerce_page_woogram_settings'], true)) {
                return;
            }
            woogram_admin_notices($message, 'notice notice-warning is-dismissible');
        });
    }

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen && !in_array($screen->id, ['plugins', 'toplevel_page_woogram_main', 'telenexa_page_woogram_settings'], true)) {
                return;
            }
            woogram_admin_notices(
                __('TeleNexa Commerce for Telegram requires WooCommerce to be installed and active.', 'telenexa-commerce-for-telegram'),
                'notice notice-warning is-dismissible'
            );
        });
    }
});

// Do not abort plugin loading when an optional PHP extension is missing. The
// Telegram adapter uses WordPress HTTP and can use its stream transport; the
// admin notice above still reports the recommended extensions.

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/settings-management.php';

/**
 * Register administrator-provided bot content in WPML String Translation.
 *
 * gettext can extract strings from source files, but it cannot discover text
 * entered later in the settings page. WPML needs these values registered at
 * runtime so translators can find them under the "TeleNexa Bot" context.
 */
function woogram_register_wpml_strings() {
    if (!function_exists('has_action') || !has_action('wpml_register_single_string')) {
        return;
    }

    $options = get_option('woogram_settings', []);
    if (!is_array($options)) {
        return;
    }

    $dynamic_strings = [
        'setting.wmuser'        => $options['wmuser'] ?? '',
        'setting.bmuser'        => $options['bmuser'] ?? '',
        'setting.messagehome'   => $options['messagehome'] ?? '',
        'setting.contactuspage' => $options['contactuspage'] ?? '',
        'setting.botsign'       => $options['botsign'] ?? '',
    ];

    foreach ($dynamic_strings as $name => $value) {
        if (is_scalar($value) && (string) $value !== '') {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Official WPML string registration action hook.
            do_action('wpml_register_single_string', 'TeleNexa Bot', $name, (string) $value);
        }
    }

    $menu_items = $options['main_menu_items'] ?? [];
    if (is_array($menu_items)) {
        foreach ($menu_items as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (['title_fa', 'title_en'] as $field) {
                if (!empty($item[$field])) {
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Official WPML string registration action hook.
                    do_action(
                        'wpml_register_single_string', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Official WPML string registration action.
                        'TeleNexa Bot',
                        'menu.' . sanitize_key($key) . '.' . substr($field, 6),
                        (string) $item[$field]
                    );
                }
            }
        }
    }

    $custom_buttons = $options['custom_menu_buttons'] ?? [];
    if (is_string($custom_buttons)) {
        $custom_buttons = json_decode($custom_buttons, true);
    }
    if (is_array($custom_buttons)) {
        foreach ($custom_buttons as $index => $button) {
            if (!is_array($button)) {
                continue;
            }
            foreach (['title_fa', 'title_en'] as $field) {
                if (!empty($button[$field])) {
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Official WPML string registration action hook.
                    do_action(
                        'wpml_register_single_string', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Official WPML string registration action.
                        'TeleNexa Bot',
                        'menu.custom.' . (int) $index . '.' . substr($field, 6),
                        (string) $button[$field]
                    );
                }
            }
            foreach (['value_fa', 'value_en'] as $field) {
                if (!empty($button[$field])) {
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Official WPML string registration action hook.
                    do_action(
                        'wpml_register_single_string', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Official WPML string registration action.
                        'TeleNexa Bot',
                        'menu.custom.' . (int) $index . '.value.' . substr($field, 6),
                        (string) $button[$field]
                    );
                }
            }
        }
    }
}
add_action('init', 'woogram_register_wpml_strings', 20);

/** Plugin lifecycle hooks used by WordPress and safe deployment tooling. */
function woogram_activate() {
    $minimum_php = '7.4.0';
    if (version_compare(phpversion(), $minimum_php, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html(sprintf(
                __('TeleNexa Commerce requires PHP version %1$s or higher. You are running %2$s.', 'telenexa-commerce-for-telegram'),
                $minimum_php,
                phpversion()
            ))
        );
    }
    woogram_defaults();
    flush_rewrite_rules(false);
}

function woogram_deactivate() {
    $hooks = [
        'woogram_prune_logs',
        'woogram_check_abandoned_cart',
        'woogram_retry_send_message',
        'woogram_process_broadcast',
    ];
    foreach ($hooks as $hook) {
        wp_clear_scheduled_hook($hook);
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions($hook, [], 'woogram');
        }
    }
    flush_rewrite_rules(false);
}

register_activation_hook(__FILE__, 'woogram_activate');
register_deactivation_hook(__FILE__, 'woogram_deactivate');

add_action('admin_init', function() {
    // Remove activation data left by the former paid edition once on upgrade.
    if (!get_option('woogram_free_edition_migrated')) {
        $saved = get_option('woogram_settings', []);
        if (is_array($saved) && array_key_exists('plugin_license', $saved)) {
            unset($saved['plugin_license']);
            update_option('woogram_settings', $saved);
        }
        delete_option('sa_te_bo_li');
        delete_option('sa_te_bo_li_');
        update_option('woogram_free_edition_migrated', 1, false);
    }
    if (!function_exists('wp_add_privacy_policy_content')) {
        return;
    }
    wp_add_privacy_policy_content(
        __('TeleNexa Commerce for Telegram', 'telenexa-commerce-for-telegram'),
        '<p>' . esc_html__('TeleNexa stores Telegram chat IDs and basic Telegram profile data to deliver bot messages, synchronize carts and associate Telegram orders with customers. It may also store order references, referral attribution and payment transaction IDs required to process orders. When a bot is configured, data needed to operate the bot is sent to the Telegram Bot API. If the optional MilMit relay webhook mode is enabled, webhook traffic is routed through the MilMit relay service before reaching this WordPress site. Store owners should disclose these practices in their site privacy policy and define their own retention period.', 'telenexa-commerce-for-telegram') . '</p>'
    );
});

function woogram_render_main_panel() {
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
    require_once __DIR__ . '/panel/main.php';
}

function woogram_render_settings_panel() {
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
    require_once __DIR__ . '/panel/settings.php';
}

function woogram_get_admin_menu_icon() {
    $svg_path = __DIR__ . '/img/woogram-icon.svg';
    if (is_file($svg_path)) {
        return 'data:image/svg+xml;base64,' . base64_encode((string) file_get_contents($svg_path));
    }
    return plugins_url('img/woogram-icon.png', __FILE__);
}

// WordPress Admin Navigation Menu
add_action('admin_menu', function() {
    $menu_title = __('TeleNexa', 'telenexa-commerce-for-telegram');
    $menu_icon  = woogram_get_admin_menu_icon();

    if (class_exists('WooCommerce')) {
        // Position under WooCommerce menu to follow commerce hierarchy and prevent clutter
        add_submenu_page(
            'woocommerce',
            $menu_title,
            $menu_title,
            'manage_options',
            'woogram_main',
            'woogram_render_main_panel'
        );
        add_submenu_page(
            'woocommerce',
            __('TeleNexa Settings', 'telenexa-commerce-for-telegram'),
            __('TeleNexa Settings', 'telenexa-commerce-for-telegram'),
            'manage_options',
            'woogram_settings',
            'woogram_render_settings_panel'
        );
    } else {
        // Fallback top-level menu only when WooCommerce is not active, placed at unobtrusive position 58.5
        add_menu_page(
            $menu_title,
            $menu_title,
            'manage_options',
            'woogram_main',
            'woogram_render_main_panel',
            $menu_icon,
            58.5
        );
        add_submenu_page(
            'woogram_main',
            __('Dashboard', 'telenexa-commerce-for-telegram'),
            __('Dashboard', 'telenexa-commerce-for-telegram'),
            'manage_options',
            'woogram_main',
            'woogram_render_main_panel'
        );
        add_submenu_page(
            'woogram_main',
            __('Settings', 'telenexa-commerce-for-telegram'),
            __('Settings', 'telenexa-commerce-for-telegram'),
            'manage_options',
            'woogram_settings',
            'woogram_render_settings_panel'
        );
    }
}, 50);

function woogram_log_panel() {
    wp_safe_redirect(admin_url('admin.php?page=woogram_main&tab=logs'));
    exit;
}

add_action('admin_init', function() {
    register_setting('woogram_settings_options', 'woogram_settings', [
        'sanitize_callback' => 'woogram_sanitize_options',
    ]);
    require_once __DIR__ . '/columns.php';
    require_once __DIR__ . '/admin-messages.php';

    // Settings Export Handler
    if (isset($_GET['action']) && sanitize_key(wp_unslash($_GET['action'])) === 'woogram_export_settings') {
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied', 'telenexa-commerce-for-telegram'));
        }
        check_admin_referer('woogram_export_settings_nonce');

        $options = get_option('woogram_settings', []);
        $export_options = woogram_export_options(is_array($options) ? $options : []);
        foreach (['proxy_password', 'proxy_password_enc', 'proxy_password_fallback', 'proxy_password_enc_fallback'] as $secret_key) {
            unset($export_options[$secret_key]);
        }
        $export_data = [
            'generator'   => 'TeleNexa for WooCommerce',
            'version'     => '1.0.0',
            'site_url'    => get_site_url(),
            'exported_at' => gmdate('Y-m-d H:i:s'),
            'settings'    => $export_options,
        ];

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=woogram-settings-' . gmdate('Y-m-d') . '.json');
        echo wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Settings Import Handler
    if (isset($_POST['woogram_import_settings_action']) && sanitize_key(wp_unslash($_POST['woogram_import_settings_action'])) !== '') {
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Permission denied', 'telenexa-commerce-for-telegram'));
        }
        check_admin_referer('woogram_import_settings_nonce', 'woogram_import_nonce');

        if (!empty($_FILES['woogram_import_file']['tmp_name'])) {
            $tmp_file = sanitize_text_field(wp_unslash($_FILES['woogram_import_file']['tmp_name']));
            if (is_uploaded_file($tmp_file)) {
                $json = file_get_contents($tmp_file);
                $data = json_decode($json, true);
                if (is_array($data) && !empty($data['settings']) && is_array($data['settings'])) {
                    $sanitized = woogram_sanitize_options(array_merge((array) get_option('woogram_settings', []), woogram_export_options($data['settings'])));
                    update_option('woogram_settings', $sanitized);
                    wp_safe_redirect(add_query_arg(['page' => 'woogram_settings', 'tab' => 'marketing', 'imported' => '1'], admin_url('admin.php')));
                    exit;
                }
            }
        }
        wp_safe_redirect(add_query_arg(['page' => 'woogram_settings', 'tab' => 'marketing', 'import_error' => '1'], admin_url('admin.php')));
        exit;
    }
});

/**
 * Comprehensive sanitization callback for TeleNexa settings.
 */
function woogram_sanitize_options($options) {
    if (!is_array($options)) {
        return (array) get_option('woogram_settings', []);
    }

    $sanitized = [];
    // Ignore activation keys in imports from the former paid edition.
    unset($options['plugin_license']);
    $previous = get_option('woogram_settings', []);
    $previous = is_array($previous) ? $previous : [];
    $options = woogram_validate_settings($options, $previous);
    $secret_fields = ['proxy_password' => 'proxy_password_enc', 'proxy_password_fallback' => 'proxy_password_enc_fallback'];
    $allowed_html = [
        'b'      => [],
        'strong' => [],
        'i'      => [],
        'em'     => [],
        'u'      => [],
        's'      => [],
        'strike' => [],
        'code'   => [],
        'pre'    => [],
        'a'      => ['href' => []],
    ];

    foreach ($options as $key => $value) {
        $clean_key = sanitize_key($key);

        if (isset($secret_fields[$clean_key])) {
            $encrypted_key = $secret_fields[$clean_key];
            $secret = is_scalar($value) ? trim((string) $value) : '';
            if ($secret !== '' && class_exists('TeleNexa\\Http\\ProxyManager')) {
                $sanitized[$encrypted_key] = \TeleNexa\Http\ProxyManager::encrypt($secret);
            } elseif (!empty($previous[$encrypted_key])) {
                $sanitized[$encrypted_key] = $previous[$encrypted_key];
            } elseif (!empty($previous[$clean_key]) && class_exists('TeleNexa\\Http\\ProxyManager')) {
                // One-time migration for installations that stored the old
                // plaintext proxy password before encrypted storage existed.
                $sanitized[$encrypted_key] = \TeleNexa\Http\ProxyManager::encrypt((string) $previous[$clean_key]);
            }
            continue;
        }
        if (in_array($clean_key, ['proxy_password_enc', 'proxy_password_enc_fallback'], true)) {
            if (!empty($previous[$clean_key])) $sanitized[$clean_key] = $previous[$clean_key];
            continue;
        }

        if (is_array($value)) {
            $clean_sub = [];
            foreach ($value as $sk => $sv) {
                if (is_array($sv)) {
                    $clean_sub[sanitize_key($sk)] = array_map('sanitize_text_field', $sv);
                } else {
                    $clean_sub[sanitize_key($sk)] = sanitize_text_field($sv);
                }
            }
            $sanitized[$clean_key] = $clean_sub;
        } elseif (is_numeric($value)) {
            $sanitized[$clean_key] = (string)$value;
        } elseif (is_bool($value)) {
            $sanitized[$clean_key] = $value ? '1' : '0';
        } else {
            if (in_array($clean_key, ['wmuser', 'bmuser', 'messagehome', 'contactuspage', 'botsign', 'posttemplate'], true) || strpos($clean_key, 'msg_') !== false || strpos($clean_key, 'text') !== false || strpos($clean_key, 'banner') !== false) {
                $sanitized[$clean_key] = wp_kses($value, $allowed_html);
            } else {
                $sanitized[$clean_key] = sanitize_text_field(trim((string) $value));
            }
        }
    }

    foreach (['proxy_port', 'proxy_port_fallback'] as $port_key) {
        if (isset($sanitized[$port_key])) $sanitized[$port_key] = (string) max(1, min(65535, absint($sanitized[$port_key])));
    }
    foreach (['proxy_type', 'proxy_type_fallback'] as $type_key) {
        if (isset($sanitized[$type_key]) && !in_array($sanitized[$type_key], ['http', 'https', 'socks4', 'socks5'], true)) {
            $sanitized[$type_key] = 'http';
        }
    }
    if (isset($sanitized['telegram_http_timeout'])) $sanitized['telegram_http_timeout'] = (string) max(5, min(20, absint($sanitized['telegram_http_timeout'])));
    if (isset($sanitized['telegram_api_retries'])) $sanitized['telegram_api_retries'] = (string) min(2, absint($sanitized['telegram_api_retries']));
    $sanitized['membership_enabled'] = !empty($options['membership_enabled']) ? '1' : '0';
    $sanitized['membership_apply_to_admins'] = !isset($options['membership_apply_to_admins']) || !empty($options['membership_apply_to_admins']) ? '1' : '0';
    $sanitized['membership_requirements'] = woogram_sanitize_membership_requirements($options['membership_requirements'] ?? []);

    return $sanitized;
}

require_once __DIR__ . '/custom-post-types.php';

// Broadcast Dispatcher (Action Scheduler or Direct)
function woogram_sendmessage_touser_switch($target, $message, $ID = 0) {
    if (function_exists('as_enqueue_async_action') && !defined('WOOGRAM_PROCESSING_BROADCAST')) {
        as_enqueue_async_action('woogram_process_broadcast', [$target, $message, (int)$ID], 'woogram');
        return 0;
    }
    return woogram_process_broadcast($target, $message, $ID);
}

function woogram_process_broadcast($target, $message, $ID = 0) {
    $count = 0;
    switch ((int)$target) {
        case 0:
            $count += woogram_sendmessagetoall($message, $ID);
            break;
        case 1:
            $count += woogram_sendmessage_users($message, $ID);
            break;
        case 2:
            $count += woogram_sendmessage_groups($message, $ID);
            break;
        case 3:
            $count += woogram_sendmessage_groups($message, $ID);
            $count += woogram_sendmessage_users($message, $ID);
            break;
        case 4:
            $count += woogram_sendmessage_channel($message, $ID);
            break;
    }
    return $count;
}

add_action('woogram_process_broadcast', function($target, $message, $ID) {
    if (!defined('WOOGRAM_PROCESSING_BROADCAST')) {
        define('WOOGRAM_PROCESSING_BROADCAST', true);
    }
    woogram_process_broadcast($target, $message, $ID);
}, 10, 3);

function woogram_send_post_notification($ID, $post) {
    if (
        !empty($_POST['woogram_m_send']) &&
        isset($_POST['woogram_m_send_target']) &&
        isset($_POST['woogram_product_misc_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woogram_product_misc_nonce'])), 'woogram_product_misc_save')
    ) {
        if (get_post_type($ID) === 'product' && get_post_status($ID) === 'publish') {
            $target = sanitize_text_field(wp_unslash($_POST['woogram_m_send_target']));
            woogram_sendmessage_touser_switch($target, '', $ID);
        }
    }
}

function woogram_defaults() {
    require_once __DIR__ . '/defaults.php';
}

// Front-end Routing (Webhook, Mini App, Signed Payment Redirect)
add_action('template_redirect', function() {
    global $wp_query;

    // Telegram Mini App
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a public Telegram Mini App route; every AJAX mutation verifies its nonce and initData.
    if (isset($_GET['telenexa_webapp']) || isset($_GET['woogram_webapp']) || isset($_GET['sale_bot_webapp'])) {
        status_header(200);
        \TeleNexa\WooBot::renderWebApp();
        exit;
    }

    // Telegram Bot Webhook
    $apikey = get_option('woogram_webhook_key');
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Telegram webhook requests are authenticated by the secret URL/header.
    if (!empty($apikey) && isset($_GET[$apikey]) && !empty($wp_query->query[$apikey])) {
        status_header(200);
        require_once __DIR__ . '/parse.php';
        exit;
    }

    // Signed Payment Gateway Redirection
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed payment redirects use the order key/HMAC instead of a WordPress nonce.
    if ((isset($_GET['telenexa_payment']) || isset($_GET['woogram_payment']) || isset($_GET['sale_bot_payment'])) && isset($_GET['order_id']) && isset($_GET['key'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed payment redirects are authenticated by the WooCommerce order key/HMAC.
        $payment_method = isset($_GET['telenexa_payment']) ? sanitize_text_field(wp_unslash($_GET['telenexa_payment'])) : (isset($_GET['woogram_payment']) ? sanitize_text_field(wp_unslash($_GET['woogram_payment'])) : sanitize_text_field(wp_unslash($_GET['sale_bot_payment'])));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed payment redirects are authenticated by the WooCommerce order key/HMAC.
        $order_id = absint(wp_unslash($_GET['order_id']));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed payment redirects are authenticated by the WooCommerce order key/HMAC.
        $order_key = sanitize_text_field(wp_unslash($_GET['key']));
        \TeleNexa\WooBot::redirectPayment($order_id, $order_key, $payment_method);
        exit;
    }
});

// Mini App AJAX Endpoints
add_action('wp_ajax_woogram_webapp_products', ['\\TeleNexa\\WooBot', 'ajaxGetProducts']);
add_action('wp_ajax_nopriv_woogram_webapp_products', ['\\TeleNexa\\WooBot', 'ajaxGetProducts']);
add_action('wp_ajax_woogram_webapp_checkout', ['\\TeleNexa\\WooBot', 'ajaxCheckout']);
add_action('wp_ajax_nopriv_woogram_webapp_checkout', ['\\TeleNexa\\WooBot', 'ajaxCheckout']);
add_action('wp_ajax_woogram_webapp_sync_cart', ['\\TeleNexa\\WooBot', 'ajaxSyncCart']);
add_action('wp_ajax_nopriv_woogram_webapp_sync_cart', ['\\TeleNexa\\WooBot', 'ajaxSyncCart']);
add_action('wp_ajax_woogram_webapp_orders', ['\\TeleNexa\\WooBot', 'ajaxGetOrders']);
add_action('wp_ajax_nopriv_woogram_webapp_orders', ['\\TeleNexa\\WooBot', 'ajaxGetOrders']);
add_action('wp_ajax_woogram_webapp_shipping_tax', ['\\TeleNexa\\WooBot', 'ajaxGetShippingAndTax']);
add_action('wp_ajax_nopriv_woogram_webapp_shipping_tax', ['\\TeleNexa\\WooBot', 'ajaxGetShippingAndTax']);
add_action('wp_ajax_woogram_webapp_validate_coupon', ['\\TeleNexa\\WooBot', 'ajaxValidateCoupon']);
add_action('wp_ajax_nopriv_woogram_webapp_validate_coupon', ['\\TeleNexa\\WooBot', 'ajaxValidateCoupon']);

// Admin Diagnostic & Health-Check Endpoints
add_action('wp_ajax_woogram_test_connection', function() {
    check_ajax_referer('woogram_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => esc_html__('Access denied.', 'telenexa-commerce-for-telegram')]);
    }
    \TeleNexa\WooBot::init('out');
    try {
        $result = \TeleNexa\Http\TelegramApiAdapter::request('getMe');
        if ($result && $result->isOk()) {
            $user = $result->getResult();
            // translators: 1: Telegram Bot ID, 2: Bot first name, 3: Bot username
            $msg = sprintf(esc_html__('Connected successfully! Bot ID: %1$s | Name: %2$s | Username: @%3$s', 'telenexa-commerce-for-telegram'), esc_html($user->getId()), esc_html($user->getFirstName()), esc_html($user->getUsername()));
            wp_send_json_success([
                'message' => $msg
            ]);
        } else {
            $desc = ($result && method_exists($result, 'getDescription')) ? $result->getDescription() : esc_html__('Unknown API response', 'telenexa-commerce-for-telegram');
            wp_send_json_error([
                'message' => esc_html__('Telegram API Error: ', 'telenexa-commerce-for-telegram') . esc_html($desc)
            ]);
        }
    } catch (\Exception $e) {
        wp_send_json_error([
            'message' => esc_html__('Connection Exception: ', 'telenexa-commerce-for-telegram') . esc_html($e->getMessage())
        ]);
    }
});

add_action('wp_ajax_woogram_test_webhook', function() {
    check_ajax_referer('woogram_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => esc_html__('Access denied.', 'telenexa-commerce-for-telegram')]);
    }
    \TeleNexa\WooBot::init('out');
    try {
        $result = \TeleNexa\Http\TelegramApiAdapter::request('getWebhookInfo');
        if ($result && $result->isOk()) {
            $info = $result->getResult();
            $url = $info->getUrl();
            $pending = $info->getPendingUpdateCount();
            $last_error = $info->getLastErrorMessage();
            $last_error_date = $info->getLastErrorDate();

            $safe_url = $url ? (string) wp_parse_url($url, PHP_URL_HOST) : esc_html__('Not set', 'telenexa-commerce-for-telegram');
            // translators: 1: Webhook host URL, 2: Pending update count
            $msg = sprintf(esc_html__('Webhook URL: %1$s | Pending Updates: %2$d', 'telenexa-commerce-for-telegram'), esc_html($safe_url), (int) $pending);
            if (!empty($last_error)) {
                // translators: 1: Last error timestamp, 2: Error description
                $msg .= ' | ' . sprintf(esc_html__('Last Error (%1$s): %2$s', 'telenexa-commerce-for-telegram'), $last_error_date ? gmdate('Y-m-d H:i:s', $last_error_date) : '', esc_html($last_error));
            }
            $header = (array) get_option('woogram_webhook_header_status', []);
            $msg .= ' | ' . esc_html__('Security header', 'telenexa-commerce-for-telegram') . ': ' . (woogram_option('webhook_secret_token') === '' ? 'disabled' : ($header['status'] ?? 'not observed'));
            if (!empty($header['checked_at'])) {
                $msg .= ' (' . esc_html($header['checked_at']) . ')';
            }
            wp_send_json_success(['message' => esc_html(woogram_redact($msg)), 'pending_updates' => (int) $pending]);
        } else {
            $desc = ($result && method_exists($result, 'getDescription')) ? $result->getDescription() : esc_html__('Unknown API response', 'telenexa-commerce-for-telegram');
            wp_send_json_error([
                'message' => esc_html__('Telegram API Error: ', 'telenexa-commerce-for-telegram') . esc_html($desc)
            ]);
        }
    } catch (\Exception $e) {
        wp_send_json_error([
            'message' => esc_html__('Webhook Check Exception: ', 'telenexa-commerce-for-telegram') . esc_html($e->getMessage())
        ]);
    }
});

add_action('wp_ajax_woogram_test_proxy', function() {
    check_ajax_referer('woogram_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => esc_html__('Access denied.', 'telenexa-commerce-for-telegram')]);
    }
    \TeleNexa\WooBot::init('out');
    try {
        $result = \TeleNexa\Http\ProxyManager::test();
        if (!empty($result['ok'])) {
            // translators: 1: Proxy address, 2: Response time in milliseconds
            wp_send_json_success(['message' => sprintf(esc_html__('Proxy connection successful via %1$s (%2$d ms).', 'telenexa-commerce-for-telegram'), esc_html($result['proxy']), (int) $result['ms'])]);
        }
        // translators: 1: Proxy address, 2: Failure message
        wp_send_json_error(['message' => sprintf(esc_html__('Proxy test failed via %1$s: %2$s', 'telenexa-commerce-for-telegram'), esc_html($result['proxy']), esc_html($result['message']))]);
    } catch (\Throwable $e) {
        wp_send_json_error(['message' => esc_html__('Proxy test exception: ', 'telenexa-commerce-for-telegram') . esc_html($e->getMessage())]);
    }
});

add_action('wp_ajax_woogram_run_tests', function() {
    check_ajax_referer('woogram_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => esc_html__('Access denied.', 'telenexa-commerce-for-telegram')]);
    }
    $suite_file = __DIR__ . '/tests/woogram_test_suite.php';
    if (!file_exists($suite_file)) {
        wp_send_json_error(['message' => esc_html__('Test suite file not found in package.', 'telenexa-commerce-for-telegram')]);
    }
    require_once $suite_file;
    $suite = new \SaleBotTestSuite();
    $report = $suite->runAll();
    wp_send_json_success($report);
});

// Automated Notifications & Event Hooks. Telegram calls are dispatched through
// Action Scheduler (or WP-Cron fallback) so checkout/admin requests stay fast.
add_action('woocommerce_new_order', ['\\TeleNexa\\WooBot', 'queueOrderCreated'], 20, 1);
add_action('woocommerce_order_status_changed', ['\\TeleNexa\\WooBot', 'queueOrderStatusChanged'], 20, 3);
add_action('woocommerce_payment_complete', ['\\TeleNexa\\WooBot', 'queuePaymentCompleted'], 20, 1);

add_action('woocommerce_product_set_stock_status', function($product_id, $status) {
    if ($status === 'instock') {
        \TeleNexa\WooBot::queueBackInStock($product_id);
    }
}, 20, 2);

add_action('woocommerce_update_product_price', function($product_id, $price) {
    \TeleNexa\WooBot::checkPriceDropAlerts($product_id, $price);
}, 20, 2);

add_action('woogram_notify_order_created', ['\\TeleNexa\\WooBot', 'onOrderCreated'], 10, 1);
add_action('woogram_notify_order_status_changed', ['\\TeleNexa\\WooBot', 'onOrderStatusChanged'], 10, 3);
add_action('woogram_notify_payment_completed', ['\\TeleNexa\\WooBot', 'onPaymentCompleted'], 10, 1);
add_action('woogram_notify_back_in_stock', ['\\TeleNexa\\WooBot', 'notifyBackInStock'], 10, 1);

add_action('woogram_check_abandoned_cart', ['\\TeleNexa\\WooBot', 'sendAbandonedCartReminder'], 10, 1);
add_action('woogram_retry_send_message', ['\\TeleNexa\\WooBot', 'executeRetrySendMessage'], 10, 1);

add_filter('query_vars', function($vars) {
    $vars[] = get_option('woogram_webhook_key');
    return $vars;
});

function woogram_option($name) {
    $options = get_option('woogram_settings');
    if (isset($options[$name])) {
        return $options[$name];
    }
    return false;
}

add_filter('user_can_richedit', function($default) {
    global $post;
    if ($post && 'telegram_commands' == get_post_type($post)) return false;
    return $default;
});

function woogram_log($action, $chat_id, $text) {
    if (preg_match('/error|fail|security_alert/i', (string) $text)) {
        woogram_log_event('legacy_error', ['action' => $action, 'description' => $text]);
    }
}

/** Unified structured activity log for webhook, button, API and proxy events. */
function woogram_log_event($event, array $context = []) {
    if (function_exists('woogram_option') && woogram_option('woogram_logging_enabled') === '0') return;
    if (woogram_option('woogram_log_level') === 'errors' && !preg_match('/error|fail|exception|exceeded|unresolved|security/i', $event)) return;
    $context = woogram_redact($context);
    $logs = get_option('wp_woogram_activity_log', []);
    $logs = is_array($logs) ? array_slice($logs, -499) : [];
    $logs[] = ['timestamp' => time(), 'time' => current_time('mysql'), 'event' => sanitize_key($event), 'chat_id' => '', 'context' => $context];
    update_option('wp_woogram_activity_log', $logs, false);
}

function woogram_sendmessagetoall($message, $ID = 0) {
    $count = 0;
    $count += woogram_sendmessage_channel($message, $ID);
    $count += woogram_sendmessage_groups($message, $ID);
    $count += woogram_sendmessage_users($message, $ID);
    return $count;
}

// Efficient chunked broadcast without limit=-1
function woogram_sendmessage_users($message, $ID = 0) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $chat_ids = $wpdb->get_col("SELECT DISTINCT post_title FROM {$wpdb->posts} WHERE post_type = 'woogram_user' AND post_status = 'private'");
    $count = 0;

    if (!empty($chat_ids)) {
        foreach ($chat_ids as $chat_id) {
            $chat_id = \TeleNexa\WooBot::fixPersianChar($chat_id);
            if (empty($chat_id)) continue;

            if (!empty($ID)) {
                \TeleNexa\WooBot::setChatId($chat_id);
                \TeleNexa\WooBot::commands();
                \TeleNexa\WooBot::getProduct('P', $ID);
            } else {
                \TeleNexa\WooBot::sendMessage([
                    'chat_id'  => $chat_id,
                    'text'     => $message,
                    'keyboard' => [[\TeleNexa\WooBot::btnHome()]]
                ]);
            }
            $count++;
            usleep(25000); // 25ms throttle
        }
    }
    return $count;
}

// Efficient chunked group broadcast without limit=-1
function woogram_sendmessage_groups($message, $ID = 0) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $group_ids = $wpdb->get_col("SELECT DISTINCT post_title FROM {$wpdb->posts} WHERE post_type = 'woogram_group' AND post_status = 'private'");
    $count = 0;

    if (!empty($group_ids)) {
        foreach ($group_ids as $group_id) {
            $group_id = \TeleNexa\WooBot::fixPersianChar($group_id);
            if (empty($group_id)) continue;

            if (!empty($ID)) {
                \TeleNexa\WooBot::setChatId($group_id);
                \TeleNexa\WooBot::getProduct('P', $ID);
            } else {
                \TeleNexa\WooBot::sendMessage([
                    'chat_id'  => $group_id,
                    'text'     => $message,
                    'keyboard' => [[\TeleNexa\WooBot::btnHome()]]
                ]);
            }
            $count++;
            usleep(25000);
        }
    }
    return $count;
}

function woogram_delete_custom_posts($post_type = 'woogram_user') {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $result = $wpdb->query($wpdb->prepare("
        DELETE posts, pt, pm 
        FROM {$wpdb->posts} posts 
        LEFT JOIN {$wpdb->term_relationships} pt ON pt.object_id = posts.ID 
        LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = posts.ID 
        WHERE posts.post_type = %s
    ", $post_type));
    return $result !== false;
}

function woogram_sendmessage_channel($message = '', $ID = 0) {
    if (woogram_option('channelusername')) {
        if (!empty($ID)) {
            \TeleNexa\WooBot::setChatId(woogram_option('channelusername'));
            \TeleNexa\WooBot::commands();
            \TeleNexa\WooBot::getProduct('P', $ID);
        } else {
            \TeleNexa\WooBot::sendMessage([
                'chat_id' => woogram_option('channelusername'),
                'text'    => $message,
            ]);
        }
        return 1;
    }
    return 0;
}

add_filter('enter_title_here', function($input) {
    global $post_type;
    if (is_admin() && 'telegram_commands' == $post_type) {
        return esc_html__('Type here your command', 'telenexa-commerce-for-telegram') . ' <small><small>(eg. <b>/contacts</b> or <b>help</b>)</small></small>';
    }
    return $input;
});

add_action('admin_print_footer_scripts', function() {
    global $post;
    if (wp_script_is('quicktags') && isset($post) && is_object($post) && $post->post_type === 'telegram_commands') {
        $quicktags_script = "if (typeof QTags !== 'undefined') {
            QTags.addButton('wpt_bold', '*Bold*', '*', '*', 'b', 'Bold', 1);
            QTags.addButton('wpt_italic', '_Italic_', '_', '_', 'i', 'Italic', 2);
            QTags.addButton('wpt_link', '[text](url)', '[text](', ')', 'l', 'Link', 3);
        }";
        wp_add_inline_script('quicktags', $quicktags_script);
    }
});

add_filter('quicktags_settings', function($qtInit) {
    global $post;
    if ($post && $post->post_type == 'telegram_commands') {
        $qtInit['buttons'] = ',';
    }
    return $qtInit;
}, 10, 1);

add_action('widgets_init', function() {
    require_once __DIR__ . '/widget.php';
    register_widget('TeleNexa_Widget');
});

add_action('admin_enqueue_scripts', function($hook) {
    if (!is_admin()) {
        return;
    }
    global $pagenow, $post_type;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

    // Menu icon styling if standalone menu is active
    if (!class_exists('WooCommerce')) {
        wp_enqueue_style('telenexa-admin-menu', plugins_url('assets/css/admin-menu.css', __FILE__), [], '1.0.0');
    }

    // Product edit screen metabox toggle
    if (($pagenow === 'post.php' || $pagenow === 'post-new.php') && ($post_type === 'product' || (isset($_GET['post']) && get_post_type((int)$_GET['post']) === 'product'))) {
        wp_enqueue_script('telenexa-admin-product', plugins_url('assets/js/admin-product.js', __FILE__), ['jquery'], '1.0.0', true);
    }

    // TeleNexa Dashboard / Main Hub
    if ($page === 'woogram_main' || $page === 'telenexa_main') {
        wp_enqueue_style('telenexa-admin-main', plugins_url('assets/css/admin-main.css', __FILE__), [], '1.0.0');
        wp_enqueue_script('telenexa-admin-main', plugins_url('assets/js/admin-main.js', __FILE__), ['jquery'], '1.0.0', true);
        wp_localize_script('telenexa-admin-main', 'telenexaMainI18n', [
            'previewPlaceholder' => __('Your live message preview will appear here in real time as you type...', 'telenexa-commerce-for-telegram'),
            'copied'             => __('Copied!', 'telenexa-commerce-for-telegram'),
        ]);
    }

    // TeleNexa Settings
    if ($page === 'woogram_settings' || $page === 'telenexa_settings' || $page === 'woogram_send') {
        wp_enqueue_script('jquery');
        wp_enqueue_style('telenexa-admin-settings', plugins_url('assets/css/admin-settings.css', __FILE__), ['dashicons'], '1.0.0');
        wp_enqueue_script('telenexa-admin-settings', plugins_url('assets/js/admin-settings.js', __FILE__), ['jquery'], '1.0.0', true);
        wp_localize_script('telenexa-admin-settings', 'telenexaSettings', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('woogram_admin_nonce'),
            'i18n'     => [
                'tokenGenerated'   => __('Secret token generated! Remember to save settings.', 'telenexa-commerce-for-telegram'),
                'connecting'       => __('Connecting to Telegram API...', 'telenexa-commerce-for-telegram'),
                'connectionFailed' => __('Connection failed', 'telenexa-commerce-for-telegram'),
                'networkError'     => __('Server network error occurred.', 'telenexa-commerce-for-telegram'),
                'checkingWebhook'  => __('Checking webhook status from Telegram...', 'telenexa-commerce-for-telegram'),
                'webhookFailed'    => __('Webhook check failed', 'telenexa-commerce-for-telegram'),
                'testingProxy'     => __('Testing proxy and fallback...', 'telenexa-commerce-for-telegram'),
                'proxyFailed'      => __('Proxy test failed.', 'telenexa-commerce-for-telegram'),
                'executingTests'   => __('Executing diagnostic unit tests...', 'telenexa-commerce-for-telegram'),
                'testSummary'      => __('Automated Diagnostic Tests Summary:', 'telenexa-commerce-for-telegram'),
                'suiteFailed'      => __('Failed to execute diagnostic test suite.', 'telenexa-commerce-for-telegram'),
                'ajaxFailed'       => __('Test suite AJAX request failed.', 'telenexa-commerce-for-telegram'),
            ],
        ]);
    }
});

function woogram_fix_emoji_input($text, $nofix = 0) {
    if ($nofix) return $text;
    $text = str_ireplace("<br>", "\n", $text);
    return wp_strip_all_tags($text);
}

function woogram_footer_text_call() {
    add_filter('admin_footer_text', 'woogram_footer_text');
}

function woogram_footer_text($default) {
    // translators: 1: Developer link, 2: Plugin title
    $text = sprintf(__('Developed and supported by %1$s | %2$s', 'telenexa-commerce-for-telegram'), '<a href="https://milmit.net" target="_blank" rel="noopener noreferrer" style="font-weight:600;color:#229ed9;">MilMit</a>', esc_html__('TeleNexa Commerce for Telegram', 'telenexa-commerce-for-telegram'));
    return wp_kses($text, ['a' => ['href' => [], 'target' => [], 'rel' => [], 'style' => []]]);
}

function woogram_get_plugin_uri() {
    return plugin_dir_url(__FILE__);
}

add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
    $order_from = $order->get_meta('_order_from', true) == 'telegram' ? esc_html__('Telegram bot', 'telenexa-commerce-for-telegram') : esc_html__('Web site', 'telenexa-commerce-for-telegram');
    echo '<p><strong>' . esc_html__('From', 'telenexa-commerce-for-telegram') . ':</strong><br>' . esc_html($order_from) . '</p>';
    if (class_exists('\\TeleNexa\\Stars')) {
        \TeleNexa\Stars::renderAdminOrderStars($order);
    }
}, 10, 1);

function woogram_add_hide_cat_field($term = '') {
    $t_id = (!empty($term) && isset($term->term_id)) ? (int) $term->term_id : 0;
    $term_meta = ['woogram_hide_cat' => 0];
    if ($t_id) {
        $saved = get_option("taxonomy_$t_id");
        if (is_array($saved)) {
            $term_meta = $saved;
        }
    }
    wp_nonce_field('woogram_cat_meta_save', 'woogram_cat_meta_nonce');
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="term_meta[woogram_hide_cat]"><?php esc_html_e('Show in TeleNexa bot', 'telenexa-commerce-for-telegram'); ?></label></th>
        <td>
            <select name="term_meta[woogram_hide_cat]" id="term_meta[woogram_hide_cat]" class="postform">
                <option value="0" <?php selected(!empty($term_meta['woogram_hide_cat']) ? $term_meta['woogram_hide_cat'] : 0, 0); ?>><?php esc_html_e('Yes', 'telenexa-commerce-for-telegram'); ?></option>
                <option value="1" <?php selected(!empty($term_meta['woogram_hide_cat']) ? $term_meta['woogram_hide_cat'] : 0, 1); ?>><?php esc_html_e('No', 'telenexa-commerce-for-telegram'); ?></option>
            </select>
        </td>
    </tr>
<?php }
add_action('product_cat_add_form_fields', 'woogram_add_hide_cat_field', 10, 2);
add_action('product_cat_edit_form_fields', 'woogram_add_hide_cat_field', 10, 2);

function woogram_save_hide_cat_field($term_id = 0) {
    if (
        !empty($term_id) &&
        isset($_POST['term_meta']) &&
        isset($_POST['woogram_cat_meta_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woogram_cat_meta_nonce'])), 'woogram_cat_meta_save')
    ) {
        if (!current_user_can('edit_term', (int) $term_id)) {
            return;
        }
        $t_id = (int) $term_id;
        $term_meta = get_option("taxonomy_$t_id");
        if (!is_array($term_meta)) {
            $term_meta = [];
        }
        $raw_term_meta = isset($_POST['term_meta']) && is_array($_POST['term_meta'])
            ? map_deep(wp_unslash($_POST['term_meta']), 'sanitize_text_field')
            : [];
        $raw_meta = is_array($raw_term_meta) ? $raw_term_meta : [];
        foreach ($raw_meta as $key => $val) {
            if (is_scalar($val)) {
                $term_meta[sanitize_key($key)] = sanitize_text_field($val);
            }
        }
        update_option("taxonomy_{$t_id}", $term_meta);
    }
}
add_action('edited_product_cat', 'woogram_save_hide_cat_field', 10, 2);
add_action('create_product_cat', 'woogram_save_hide_cat_field', 10, 2);

function woogram_check_for_fatal() {
    $error = error_get_last();
    if ($error && $error["type"] == E_ERROR) {
        woogram_log_error($error["type"], $error["message"], $error["file"], $error["line"]);
    }
}
function woogram_log_error($num, $str, $file, $line, $context = null) {
    woogram_log_exception(new ErrorException($str, 0, $num, $file, $line));
}
function woogram_log_exception(Exception $e) {
    $message = gmdate('Y/m/d H:i:s >') . " Type: " . get_class($e) . "; Message: {$e->getMessage()}; File: {$e->getFile()}; Line: {$e->getLine()};";
    \TeleNexa\WooBot::log($message);
}

add_action('init', function() {
    if (woogram_option('testmod') === 'yes') {
        register_shutdown_function('woogram_check_for_fatal');
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Opt-in diagnostics mode only.
        set_error_handler('woogram_log_error');
        set_exception_handler('woogram_log_exception');
    }
    if (!class_exists('WooCommerce')) return;
    woogram_defaults();
    require_once __DIR__ . '/WP_REST_Server_Rewrite.php';
    \TeleNexa\WooBot::init('out');
    add_action('woocommerce_process_product_meta', 'woogram_send_post_notification', 30, 2);

    // Register Telegram Stars product and checkout hooks
    if (class_exists('\\TeleNexa\\Stars')) {
        add_action('woocommerce_product_options_pricing', ['\\TeleNexa\\Stars', 'renderProductField']);
        add_action('woocommerce_process_product_meta', ['\\TeleNexa\\Stars', 'saveProductField'], 20, 1);
        add_action('woocommerce_variation_options_pricing', ['\\TeleNexa\\Stars', 'renderVariationField'], 10, 3);
        add_action('woocommerce_save_product_variation', ['\\TeleNexa\\Stars', 'saveVariationField'], 10, 2);
        add_action('woocommerce_review_order_after_order_total', ['\\TeleNexa\\Stars', 'renderCheckoutStarsNotice']);
        add_action('woocommerce_order_details_after_order_table', ['\\TeleNexa\\Stars', 'renderOrderStarsNotice']);
        add_action('woocommerce_order_status_refunded', ['\\TeleNexa\\Stars', 'handleOrderRefunded'], 10, 1);
        add_action('woocommerce_checkout_order_created', ['\\TeleNexa\\Stars', 'saveOrderStarsAmount'], 20, 1);
    }
});
