<?php
if (!defined('ABSPATH')) exit;

woogram_set_option();

function woogram_set_option() {
    $option_ver_new = '1.8';
    $woogram_option_version = get_option('woogram_option_version');

    if (!get_option('woogram_webhook_key')) {
        update_option('woogram_webhook_key', md5(microtime() . wp_rand() . get_site_url()));
    }

    if (version_compare($woogram_option_version, $option_ver_new, '<')) {
        update_option('woogram_option_version', $option_ver_new);

        $defaults = [
            ['testmod', 'no'],
            ['token', ''],
            ['username', ''],
            ['channelusername', ''],
            ['target', 4],
            ['mode', 0],
            // translators: %FIRST_NAME%: User's Telegram first name.
            ['wmuser', __("Hi welcome %FIRST_NAME%\nPlease send your search term or select from list below:", 'telenexa-commerce-for-telegram')],
            // translators: %FIRST_NAME%: User's Telegram first name.
            ['bmuser', __("Bye %FIRST_NAME%.\nFor start again send /start", 'telenexa-commerce-for-telegram')],
            // translators: %FIRST_NAME%: User's Telegram first name.
            ['messagehome', __("Dear %FIRST_NAME%.\nPlease send your search term or select from list below", 'telenexa-commerce-for-telegram')],
            ['contactuspage', __('Contact us', 'telenexa-commerce-for-telegram')],
            ['contactuspageurl', get_site_url()],
            ['botsign', '==================================='],
            ['display_productsperpage', 5],
            ['display_alert_in_bot', 1],
            ['display_categories', 1],
            ['display_productslist', 1],
            ['display_product', 1],
            ['display_product_excerpt', 0],
            ['display_product_excerpt_length', 55],
            ['display_search', 1],
            ['display_photosize', 2],
            ['display_categorycount', 1],
            ['display_hideemptycategory', 1],
            ['display_product_link_type', 1],
            ['hide_products_edge_subcategory', 1],
            ['only_display_instock_products', 1],
            ['display_showqty', 0],
            ['display_price_discounted', ['in', 'out']],
            ['display_price', ['in', 'out']],
            ['display_linkbuyonsite', ['out']],
            ['display_linkquickbuy', ['out']],
            ['display_buttonorder', ['in']],
            ['display_buttonviewonsite', ['in']],
            ['display_howgetqty', "1 2 3 4 5\n6 7 8 9 10"],
            ['order_cart_status', 1],
            ['show_coupon_in_cart', 1],
            ['register_req_email', 0],
            ['register_req_qty', 1],
            ['register_req_state', 1],
            ['register_req_city', 1],
            ['register_req_name', 1],
            ['register_req_phone', 1],
            ['register_req_address', 1],
            ['register_req_zip', 1],
            ['register_req_ordernote', 0],
            ['register_req_shipping', 1],
            ['register_req_payment', 1],
            ['posttemplate', '%TITLE%' . PHP_EOL . PHP_EOL . '%LINK%'],
            ['keyboard', ''],
            ['enabledingroups', 1],
            ['feature_wishlist', 1],
            ['feature_live_search', 1],
            ['feature_menu_commands', 1],
            ['feature_webapp_enable', 1],
            ['feature_abandoned_cart', 1],
            ['abandoned_cart_delay_hours', 3],
            ['default_language', 'fa'],
            ['wpml_language_sync', 1],
            ['webhook_secret_token', ''],
            ['security_rate_limit_enable', 1],
            ['notify_customer_new_order', 1],
            ['notify_admin_new_order', 1],
            ['notify_status_change', 1],
            ['admin_notification_chat_id', ''],
            ['newest_orderby', 'date'],
            ['search_default_sort', 'date'],
            ['search_sku_enable', 1],
            ['search_price_filter_enable', 1],
            ['telegram_stars_enable', 0],
            ['telegram_stars_per_currency_unit', 0],
            ['proxy_status', 0],
            ['proxy_type', 'http'],
            ['proxy_port', 8080],
            ['proxy_status_fallback', 0],
            ['proxy_type_fallback', 'http'],
            ['proxy_port_fallback', 8080],
            ['telegram_http_timeout', 6],
            ['telegram_api_retries', 0],
            ['woogram_logging_enabled', 1],
            ['woogram_log_level', 'all'],
            ['woogram_log_retention_days', 7],
            ['membership_enabled', 0],
            ['membership_apply_to_admins', 1],
            ['membership_requirements', []],
        ];

        $my_options = get_option('woogram_settings', []);
        if (!isset($my_options['search_default_sort'])) {
            $legacy_sort = $my_options['newest_orderby'] ?? 'date';
            $my_options['search_default_sort'] = in_array($legacy_sort, ['date', 'popularity', 'price_asc', 'price_desc', 'on_sale'], true) ? $legacy_sort : 'date';
        }
        if (!in_array($my_options['newest_orderby'] ?? 'date', ['date', 'modified', 'popularity', 'price'], true)) {
            $my_options['newest_orderby'] = ($my_options['newest_orderby'] === 'price_asc') ? 'price' : 'date';
        }
        foreach ($defaults as $def) {
            if (!isset($my_options[$def[0]])) {
                $my_options[$def[0]] = $def[1];
            }
        }
        update_option('woogram_settings', $my_options);
    }
}
