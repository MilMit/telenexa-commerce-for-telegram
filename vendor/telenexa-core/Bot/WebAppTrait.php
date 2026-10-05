<?php
namespace TeleNexa\Bot;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Telegram Mini App (Web App) Controller and API for WooBot.
 * Enterprise-grade security, initData validation, variation support, shipping/tax math, coupons, and orders.
 */
trait WebAppTrait {

    /**
     * Render the Mini App standalone template.
     */
    public static function renderWebApp() {
        if (woogram_option('feature_webapp_enable') === '0') {
            wp_die(esc_html(self::botTranslate('Telegram Mini App is disabled in settings.', 'telenexa-commerce-for-telegram')));
        }
        $template = dirname(dirname(dirname(__DIR__))) . '/templates/webapp.php';
        if (file_exists($template)) {
            require $template;
            exit;
        }
        wp_die(esc_html(self::botTranslate('Mini App template not found.', 'telenexa-commerce-for-telegram')));
    }

    /**
     * AJAX endpoint to return products for the Mini App with full variation support.
     */
    public static function ajaxGetProducts() {
        $cat_id = isset($_GET['cat_id']) ? absint($_GET['cat_id']) : 0;
        $s = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $requested_lang = isset($_GET['lang']) ? strtolower(substr(sanitize_key(wp_unslash($_GET['lang'])), 0, 2)) : '';
        $lang = preg_match('/\A[a-z]{2}\z/', $requested_lang) ? $requested_lang : self::getLanguage();

        self::switchLanguageContext($lang);

        $results = self::queryProducts([
            's'            => $s,
            'cat_id'       => $cat_id,
            'only_instock' => (bool) woogram_option('only_display_instock_products'),
            'orderby'      => 'date',
            'per_page'     => 50,
        ]);

        $items = [];
        foreach ($results['products'] as $p) {
            if (!$p instanceof \WC_Product) $p = wc_get_product($p);
            if (!$p || !$p->is_visible()) continue;

            $reg = $p->get_regular_price();
            $sale = $p->get_sale_price();
            $discount_pct = 0;
            if ($p->is_on_sale() && $reg && $sale && $reg > 0) {
                $discount_pct = round((($reg - $sale) / $reg) * 100);
            }

            $product_type = $p->get_type();
            $is_variable = $p->is_type('variable');
            $variations_data = [];

            if ($is_variable && $p instanceof \WC_Product_Variable) {
                $available_variations = $p->get_available_variations();
                foreach ($available_variations as $var) {
                    $var_obj = wc_get_product($var['variation_id']);
                    if (!$var_obj) continue;
                    $variations_data[] = [
                        'variation_id' => (int) $var['variation_id'],
                        'attributes'   => $var['attributes'],
                        'price'        => (float) $var['display_price'],
                        'regular_price'=> (float) $var['display_regular_price'],
                        'image'        => !empty($var['image']['url']) ? $var['image']['url'] : wp_get_attachment_image_url($p->get_image_id(), 'medium'),
                        'is_in_stock'  => (bool) $var['is_in_stock'],
                        'max_qty'      => $var['max_qty'] ?: 99,
                    ];
                }
            }

            $items[] = [
                'id'              => $p->get_id(),
                'name'            => $p->get_name(),
                'type'            => $product_type,
                'is_variable'     => $is_variable,
                'variations'      => $variations_data,
                'is_virtual'      => (bool) $p->is_virtual(),
                'is_downloadable' => (bool) $p->is_downloadable(),
                'price'           => (float) $p->get_price(),
                'regular_price'   => $reg ? (float) $reg : null,
                'discount_pct'    => $discount_pct,
                'image'           => wp_get_attachment_image_url($p->get_image_id(), 'medium') ?: wc_placeholder_img_src('medium'),
                'in_stock'        => (bool) $p->is_in_stock(),
                'stock_quantity'  => $p->get_stock_quantity(),
                'sku'             => $p->get_sku(),
                'short_desc'      => wp_strip_all_tags($p->get_short_description()),
            ];
        }

        wp_send_json_success($items);
    }

    /** Synchronize Mini App localStorage with the authenticated Telegram cart. */
    public static function ajaxSyncCart() {
        $requested_chat_id = isset($_POST['chat_id']) ? sanitize_text_field(wp_unslash($_POST['chat_id'])) : '';
        $security_check = self::verifyWebAppSecurity($requested_chat_id, true);
        if (is_wp_error($security_check)) {
            status_header(403);
            wp_send_json_error(['message' => $security_check->get_error_message()]);
        }

        $payload = isset($_POST['cart']) ? json_decode(wp_unslash($_POST['cart']), true) : [];
        $cart = is_array($payload) ? self::normalizeCartPayload($payload) : [];
        self::setChatId($security_check['chat_id']);
        self::initSession();
        self::saveCart($cart);

        wp_send_json_success(['cart' => $cart, 'items_count' => array_sum($cart)]);
    }

    /**
     * AJAX endpoint to calculate shipping options and taxes based on address.
     */
    public static function ajaxGetShippingAndTax() {
        $requested_chat_id = isset($_REQUEST['chat_id']) ? sanitize_text_field(wp_unslash($_REQUEST['chat_id'])) : '';
        $security_check = self::verifyWebAppSecurity($requested_chat_id, true);
        if (is_wp_error($security_check)) {
            status_header(403);
            wp_send_json_error(['message' => $security_check->get_error_message()]);
        }
        $cart_json = isset($_REQUEST['cart']) ? wp_unslash($_REQUEST['cart']) : '{}';
        $cart = json_decode($cart_json, true) ?: [];
        $cart = self::normalizeCartPayload(is_array($cart) ? $cart : []);

        $city = isset($_REQUEST['city']) ? sanitize_text_field(wp_unslash($_REQUEST['city'])) : '';
        $state = isset($_REQUEST['state']) ? sanitize_text_field(wp_unslash($_REQUEST['state'])) : '';
        $country = isset($_REQUEST['country']) ? sanitize_text_field(wp_unslash($_REQUEST['country'])) : 'IR';

        $needs_shipping = false;
        $items_subtotal = 0;

        foreach ($cart as $key => $qty) {
            $p_id = (strpos((string)$key, 'V') !== false) ? (int)explode('V', (string)$key)[1] : (int)$key;
            $product = wc_get_product($p_id);
            if ($product) {
                if ($product->needs_shipping()) {
                    $needs_shipping = true;
                }
                $items_subtotal += ((float)$product->get_price() * max(1, (int)$qty));
            }
        }

        $shipping_methods = [];
        if ($needs_shipping && function_exists('WC')) {
            if (isset(WC()->customer)) {
                WC()->customer->set_shipping_country($country);
                WC()->customer->set_shipping_state($state);
                WC()->customer->set_shipping_city($city);
            }

            $zone = \WC_Shipping_Zones::get_zone_matching_package([
                'destination' => [
                    'country'  => $country,
                    'state'    => $state,
                    'city'     => $city,
                    'postcode' => '',
                ]
            ]);

            if ($zone) {
                $shipping_methods_available = $zone->get_shipping_methods(true);
                foreach ($shipping_methods_available as $sm) {
                    $cost = 0;
                    if (isset($sm->cost)) {
                        $cost = (float) $sm->cost;
                    } elseif (method_exists($sm, 'get_option')) {
                        $cost = (float) $sm->get_option('cost', 0);
                    }
                    $shipping_methods[] = [
                        'id'             => $sm->id . ':' . $sm->instance_id,
                        'title'          => $sm->get_title(),
                        'cost'           => $cost,
                        'formatted_cost' => $cost > 0 ? wc_price($cost) : self::botTranslate('Free', 'telenexa-commerce-for-telegram'),
                    ];
                }
            }
        }

        $tax_total = 0.0;
        if (class_exists('WC_Tax')) {
            $tax_rates = \WC_Tax::find_rates([
                'country'   => strtoupper($country),
                'state'     => strtoupper($state),
                'postcode'  => '',
                'city'      => $city,
                'tax_class' => '',
            ]);
            if (!empty($tax_rates)) {
                $tax_total = array_sum(\WC_Tax::calc_tax($items_subtotal, $tax_rates, false));
            }
        }

        // Enabled payment gateways
        $available_gateways = [];
        if (function_exists('WC') && WC()->payment_gateways()) {
            $gateways = WC()->payment_gateways()->get_available_payment_gateways();
            foreach ($gateways as $id => $gateway) {
                $available_gateways[] = [
                    'id'          => $id,
                    'title'       => $gateway->get_title(),
                    'description' => $gateway->get_description(),
                ];
            }
        }

        wp_send_json_success([
            'needs_shipping'   => $needs_shipping,
            'shipping_methods' => $shipping_methods,
            'payment_gateways' => $available_gateways,
            'subtotal'         => $items_subtotal,
            'tax'              => (float) $tax_total,
            'total'            => (float) ($items_subtotal + $tax_total),
        ]);
    }

    /**
     * AJAX endpoint to validate a coupon code and calculate discount.
     */
    public static function ajaxValidateCoupon() {
        $requested_chat_id = isset($_REQUEST['chat_id']) ? sanitize_text_field(wp_unslash($_REQUEST['chat_id'])) : '';
        $security_check = self::verifyWebAppSecurity($requested_chat_id, true);
        if (is_wp_error($security_check)) {
            status_header(403);
            wp_send_json_error(['message' => $security_check->get_error_message()]);
        }
        $coupon_code = isset($_REQUEST['coupon_code']) ? sanitize_text_field(wp_unslash($_REQUEST['coupon_code'])) : '';
        $subtotal = (float) (isset($_REQUEST['subtotal']) ? $_REQUEST['subtotal'] : 0);

        if (empty($coupon_code)) {
            wp_send_json_error(['message' => esc_html(self::botTranslate('Please enter a coupon code.', 'telenexa-commerce-for-telegram'))]);
        }

        $coupon = new \WC_Coupon($coupon_code);
        if (!$coupon->get_id()) {
            wp_send_json_error(['message' => esc_html(self::botTranslate('Coupon code is invalid.', 'telenexa-commerce-for-telegram'))]);
        }

        // Expiry check
        $expiry = $coupon->get_date_expires();
        if ($expiry && current_time('timestamp', true) > $expiry->getTimestamp()) {
            wp_send_json_error(['message' => esc_html(self::botTranslate('This coupon has expired.', 'telenexa-commerce-for-telegram'))]);
        }

        // Usage limit check
        $usage_limit = $coupon->get_usage_limit();
        $usage_count = $coupon->get_usage_count();
        if ($usage_limit > 0 && $usage_count >= $usage_limit) {
            wp_send_json_error(['message' => esc_html(self::botTranslate('This coupon usage limit has been reached.', 'telenexa-commerce-for-telegram'))]);
        }

        // Min/Max spend
        $min = (float) $coupon->get_minimum_amount();
        $max = (float) $coupon->get_maximum_amount();
        if ($min > 0 && $subtotal < $min) {
            // translators: %s: Minimum spend amount formatted
            wp_send_json_error(['message' => sprintf(esc_html(self::botTranslate('Minimum spend for this coupon is %s.', 'telenexa-commerce-for-telegram')), wc_price($min))]);
        }
        if ($max > 0 && $subtotal > $max) {
            // translators: %s: Maximum spend amount formatted
            wp_send_json_error(['message' => sprintf(esc_html(self::botTranslate('Maximum spend for this coupon is %s.', 'telenexa-commerce-for-telegram')), wc_price($max))]);
        }

        // Calculate discount
        $discount = 0;
        $discount_type = $coupon->get_discount_type();
        $amount = (float) $coupon->get_amount();

        if ($discount_type === 'percent') {
            $discount = round(($subtotal * $amount) / 100);
        } else {
            $discount = min($amount, $subtotal);
        }

        wp_send_json_success([
            'coupon_code'        => $coupon->get_code(),
            'discount'           => $discount,
            'formatted_discount' => wc_price($discount),
            // translators: %s: Coupon code
            'message'            => sprintf(esc_html(self::botTranslate('Coupon "%s" applied successfully!', 'telenexa-commerce-for-telegram')), esc_html($coupon->get_code())),
        ]);
    }

    /**
     * AJAX endpoint for Mini App checkout with security validation and idempotency locks.
     */
    public static function ajaxCheckout() {
        // 1. Security & Anti-Spoofing Verification
        $requested_chat_id = isset($_POST['chat_id']) ? sanitize_text_field(wp_unslash($_POST['chat_id'])) : '';
        $security_check = self::verifyWebAppSecurity($requested_chat_id, true);
        if (is_wp_error($security_check)) {
            status_header(403);
            wp_send_json_error(['message' => $security_check->get_error_message()]);
        }

        $auth_chat_id = $security_check['chat_id'];
        self::setChatId($auth_chat_id);
        self::initSession();

        // 2. Rate limit check
        if (!self::checkRateLimit($auth_chat_id, 30, 60)) {
            wp_send_json_error(['message' => esc_html(self::botTranslate('Too many requests. Please wait a moment before trying again.', 'telenexa-commerce-for-telegram'))]);
        }

        // 3. Idempotency Lock
        if (!self::acquireOrderLock($auth_chat_id, 30)) {
            wp_send_json_error(['message' => esc_html(self::botTranslate('Your order is already being processed. Please wait.', 'telenexa-commerce-for-telegram'))]);
        }

        $cart_json = isset($_POST['cart']) ? wp_unslash($_POST['cart']) : '{}';
        $cart = json_decode($cart_json, true);
        $cart = is_array($cart) ? self::normalizeCartPayload($cart) : [];
        self::saveCart($cart);

        if (empty($cart) || !is_array($cart)) {
            self::releaseOrderLock($auth_chat_id);
            wp_send_json_error(['message' => esc_html(self::botTranslate('Your cart is empty.', 'telenexa-commerce-for-telegram'))]);
        }

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $state = isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '';
        $city = isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '';
        $address = isset($_POST['address']) ? sanitize_text_field(wp_unslash($_POST['address'])) : '';
        $zip = isset($_POST['zip']) ? sanitize_text_field(wp_unslash($_POST['zip'])) : '';
        $note = isset($_POST['note']) ? sanitize_text_field(wp_unslash($_POST['note'])) : '';
        $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field(wp_unslash($_POST['coupon_code'])) : '';
        $shipping_method = isset($_POST['shipping_method']) ? sanitize_text_field(wp_unslash($_POST['shipping_method'])) : '';
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : '';
        $username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';

        if (empty($name) || empty($phone) || empty($address)) {
            self::releaseOrderLock($auth_chat_id);
            wp_send_json_error(['message' => esc_html(self::botTranslate('Please fill in all required recipient fields.', 'telenexa-commerce-for-telegram'))]);
        }

        // Split name into first and last name
        $name_parts = explode(' ', trim($name), 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';

        // Create Order
        $order_args = [
            'customer_note' => $note,
        ];
        $order = wc_create_order($order_args);
        if (is_wp_error($order)) {
            self::releaseOrderLock($auth_chat_id);
            wp_send_json_error(['message' => $order->get_error_message()]);
        }

        $needs_shipping = false;

        // Add line items with variation support and stock checking
        foreach ($cart as $key => $qty) {
            $qty = max(1, absint($qty));
            $product_id = $key;
            $variation_id = 0;
            $variation_args = [];

            if (strpos((string)$key, 'V') !== false) {
                $parts = explode('V', (string)$key);
                $product_id = (int)$parts[0];
                $variation_id = (int)$parts[1];
            } else {
                $product_id = (int)$key;
            }

            $product = wc_get_product($variation_id ?: $product_id);
            if (!$product || !$product->is_purchasable()) {
                self::releaseOrderLock($auth_chat_id);
                $order->delete(true);
                wp_send_json_error(['message' => sprintf(self::botTranslate('Product "%s" is currently unavailable.', 'telenexa-commerce-for-telegram'), $product ? $product->get_name() : $key)]);
            }

            // Real-time stock check
            if (!$product->is_in_stock() || ($product->managing_stock() && $product->get_stock_quantity() < $qty)) {
                self::releaseOrderLock($auth_chat_id);
                $order->delete(true);
                wp_send_json_error(['message' => sprintf(self::botTranslate('Sorry, "%s" does not have enough stock available.', 'telenexa-commerce-for-telegram'), $product->get_name())]);
            }

            if ($product->needs_shipping()) {
                $needs_shipping = true;
            }

            if ($variation_id && $product instanceof \WC_Product_Variation) {
                $variation_args['variation'] = $product->get_variation_attributes();
            }

            $order->add_product($product, $qty, $variation_args);
        }

        // Set customer address
        $address_data = [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'phone'      => $phone,
            'email'      => $email,
            'state'      => $state,
            'city'       => $city,
            'address_1'  => $address,
            'postcode'   => $zip,
            'country'    => 'IR',
        ];
        $order->set_address($address_data, 'billing');
        $order->set_address($address_data, 'shipping');

        // Shipping item if physical order. Never trust the browser's price;
        // resolve the selected method and cost from the active WooCommerce zone.
        if ($needs_shipping) {
            $shipping_method = sanitize_text_field($shipping_method);
            $shipping_item = null;
            $zone = \WC_Shipping_Zones::get_zone_matching_package([
                'destination' => [
                    'country'  => 'IR',
                    'state'    => $state,
                    'city'     => $city,
                    'postcode' => $zip,
                ],
            ]);
            if ($zone) {
                foreach ($zone->get_shipping_methods(true) as $method) {
                    $method_key = $method->id . ':' . $method->instance_id;
                    if ($method_key !== $shipping_method) continue;
                    $cost = method_exists($method, 'get_option')
                        ? (float) $method->get_option('cost', 0)
                        : (isset($method->cost) ? (float) $method->cost : 0.0);
                    $shipping_item = new \WC_Order_Item_Shipping();
                    $shipping_item->set_method_title($method->get_title());
                    $shipping_item->set_method_id($method_key);
                    $shipping_item->set_total($cost);
                    $order->add_item($shipping_item);
                    break;
                }
            }
            if (!$shipping_item) {
                self::releaseOrderLock($auth_chat_id);
                $order->delete(true);
                wp_send_json_error(['message' => self::botTranslate('The selected shipping method is no longer available.', 'telenexa-commerce-for-telegram')]);
            }
        }

        // Apply coupon if valid
        if (!empty($coupon_code)) {
            try {
                if (!$order->apply_coupon($coupon_code)) {
                    self::log('Coupon rejected during WebApp checkout: ' . $coupon_code);
                }
            } catch (\Exception $e) {
                self::log('Coupon error in WebApp checkout: ' . $e->getMessage());
            }
        }

        // Set payment method only from the currently available WooCommerce gateways.
        $available_gateways = function_exists('WC') && WC()->payment_gateways()
            ? WC()->payment_gateways()->get_available_payment_gateways()
            : [];
        if (!empty($payment_method) && !isset($available_gateways[$payment_method])) {
            self::releaseOrderLock($auth_chat_id);
            $order->delete(true);
            wp_send_json_error(['message' => self::botTranslate('The selected payment method is unavailable.', 'telenexa-commerce-for-telegram')]);
        }
        if (!empty($payment_method)) {
            $order->set_payment_method($payment_method);
        }

        // Calculate totals including taxes and discounts
        $order->calculate_totals(true);

        // Store metadata
        $order->update_meta_data('_order_from', 'telegram');
        $order->update_meta_data('_order_platform', 'telegram_webapp');
        $order->update_meta_data('_chat_id', $auth_chat_id);
        if (!empty($username)) {
            $order->update_meta_data('_telegram_username', $username);
        }
        if (class_exists('\\TeleNexa\\Stars')) {
            \TeleNexa\Stars::saveOrderStarsAmount($order);
        }

        // Check referral
        $referrer = self::getUserMeta($auth_chat_id, '_referred_by');
        if (!empty($referrer)) {
            $order->update_meta_data('_referred_by', $referrer);
        }

        $order->save();
        self::releaseOrderLock($auth_chat_id);

        // Generate signed payment URL
        $pay_url = self::generateSignedPaymentUrl($order->get_id(), $payment_method, 7200);
        if (empty($pay_url)) {
            $pay_url = $order->get_checkout_payment_url();
        }

        // Trigger Notification
        if (method_exists(__CLASS__, 'queueOrderCreated')) {
            self::queueOrderCreated($order->get_id());
        }

        wp_send_json_success([
            'order_id'     => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'total'        => (float) $order->get_total(),
            'redirect_url' => $pay_url,
        ]);
    }

    /**
     * AJAX endpoint to fetch orders for authenticated Telegram user with strict ownership protection.
     */
    public static function ajaxGetOrders() {
        $requested_chat_id = isset($_GET['chat_id']) ? sanitize_text_field(wp_unslash($_GET['chat_id'])) : '';
        $security_check = self::verifyWebAppSecurity($requested_chat_id, true);
        if (is_wp_error($security_check)) {
            status_header(403);
            wp_send_json_error(['message' => $security_check->get_error_message()]);
        }

        $auth_chat_id = $security_check['chat_id'];
        $result = self::getCustomerOrders($auth_chat_id, 1, 20);
        $orders_list = [];

        foreach ($result['orders'] as $order) {
            if (!$order instanceof \WC_Order) continue;

            $items_summary = [];
            foreach ($order->get_items() as $item) {
                $items_summary[] = [
                    'name'     => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total'    => wc_price($item->get_total()),
                ];
            }

            $downloads = [];
            if ($order->is_download_permitted()) {
                foreach ($order->get_downloadable_items() as $d) {
                    $downloads[] = [
                        'name' => $d['download_name'] ?: $d['product_name'],
                        'url'  => $d['download_url'],
                    ];
                }
            }

            $pay_url = '';
            if ($order->needs_payment()) {
                $pay_url = self::generateSignedPaymentUrl($order->get_id(), $order->get_payment_method(), 7200);
                if (empty($pay_url)) {
                    $pay_url = $order->get_checkout_payment_url();
                }
            }

            $orders_list[] = [
                'id'             => $order->get_id(),
                'number'         => $order->get_order_number(),
                'status'         => $order->get_status(),
                'status_label'   => wc_get_order_status_name($order->get_status()),
                'total'          => (float) $order->get_total(),
                'formatted_total'=> wc_price($order->get_total()),
                'date'           => wc_format_datetime($order->get_date_created()),
                'items'          => $items_summary,
                'downloads'      => $downloads,
                'pay_url'        => $pay_url,
            ];
        }

        wp_send_json_success($orders_list);
    }
}
