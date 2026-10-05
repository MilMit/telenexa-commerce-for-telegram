<?php

namespace TeleNexa;

use TeleNexa\Bot\PaymentsTrait;
use TeleNexa\Http\TelegramApiAdapter;

/**
 * Telegram Stars pricing, metadata and checkout integration.
 *
 * Provides dedicated per-product and per-variation Telegram Stars pricing
 * while preserving the global currency conversion rate as a fallback.
 */
class Stars {

    const META_PRICE = '_telegram_stars_price';
    const META_LEGACY_PRICE = '_woogram_telegram_stars_price';
    const META_ORDER_STARS_AMOUNT = '_woogram_stars_amount';
    const META_ORDER_PAYLOAD_HASH = '_woogram_stars_payload_hash';
    const META_ORDER_USER_ID = '_woogram_stars_user_id';
    const META_ORDER_CHARGE_ID = '_woogram_telegram_payment_charge_id';
    const META_ORDER_PROVIDER = '_woogram_telegram_payment_provider';
    const META_ORDER_REFUNDED = '_woogram_stars_refunded';

    /**
     * Check whether Telegram Stars payments are globally enabled.
     *
     * @return bool
     */
    public static function isEnabled() {
        return (int) woogram_option('telegram_stars_enable') === 1;
    }

    /**
     * Get the global conversion rate (Stars per store currency unit).
     *
     * @return float
     */
    public static function getGlobalRate() {
        return (float) woogram_option('telegram_stars_per_currency_unit');
    }

    /**
     * Get custom Stars price for a product or variation if set and valid.
     *
     * Returns a positive integer if valid (>= 1).
     * If invalid, zero, negative, or not set, returns null (to trigger global rate fallback).
     *
     * @param mixed $product Product ID, WC_Product, or WC_Product_Variation.
     * @return int|null
     */
    public static function getProductCustomStarsPrice($product) {
        if (is_numeric($product)) {
            $product = function_exists('wc_get_product') ? wc_get_product(absint($product)) : null;
        }

        if (!$product || !is_object($product)) {
            return null;
        }

        $price = null;
        if (method_exists($product, 'get_meta')) {
            $price = $product->get_meta(self::META_PRICE, true);
            if ($price === '' || $price === null) {
                $price = $product->get_meta(self::META_LEGACY_PRICE, true);
            }
        } else {
            $id = method_exists($product, 'get_id') ? $product->get_id() : (isset($product->ID) ? $product->ID : 0);
            if ($id) {
                $price = get_post_meta($id, self::META_PRICE, true);
                if ($price === '' || $price === null) {
                    $price = get_post_meta($id, self::META_LEGACY_PRICE, true);
                }
            }
        }

        // If variation does not have a valid custom Stars price, check parent product if available
        if (($price === '' || $price === null || !is_numeric($price) || (int) $price < 1) && method_exists($product, 'get_parent_id')) {
            $parent_id = $product->get_parent_id();
            if ($parent_id > 0) {
                $parent = function_exists('wc_get_product') ? wc_get_product($parent_id) : null;
                if ($parent && method_exists($parent, 'get_meta')) {
                    $parent_price = $parent->get_meta(self::META_PRICE, true);
                    if ($parent_price === '' || $parent_price === null) {
                        $parent_price = $parent->get_meta(self::META_LEGACY_PRICE, true);
                    }
                    if ($parent_price !== '' && $parent_price !== null && is_numeric($parent_price) && (int) $parent_price >= 1) {
                        $price = $parent_price;
                    }
                }
            }
        }

        // Validate: must be integer >= 1
        if ($price !== '' && $price !== null && is_numeric($price)) {
            $val = (int) $price;
            if ($val >= 1) {
                return $val;
            }
        }

        return null;
    }

    /**
     * Check whether a product is digital and requires no shipping.
     *
     * @param mixed $product Product ID, WC_Product, or WC_Product_Variation.
     * @return bool
     */
    public static function isDigitalProduct($product) {
        if (is_numeric($product)) {
            $product = function_exists('wc_get_product') ? wc_get_product(absint($product)) : null;
        }

        if (!$product || !is_object($product)) {
            return false;
        }

        if (method_exists($product, 'is_purchasable') && !$product->is_purchasable()) {
            return false;
        }

        if (method_exists($product, 'needs_shipping') && $product->needs_shipping()) {
            return false;
        }

        return true;
    }

    /**
     * Check whether an entire cart consists strictly of digital items.
     *
     * @param array|null $cart Cart array [cart_key => quantity].
     * @return bool
     */
    public static function isDigitalCart($cart = null) {
        if ($cart === null && class_exists('\\TeleNexa\\WooBot') && method_exists('\\TeleNexa\\WooBot', 'getCart')) {
            $cart = \TeleNexa\WooBot::getCart();
        }

        if (!is_array($cart) || empty($cart)) {
            return false;
        }

        foreach ($cart as $cart_key => $quantity) {
            if (absint($quantity) < 1) {
                return false;
            }
            $parts = explode('V', (string) $cart_key, 2);
            $product_id = isset($parts[1]) ? absint($parts[1]) : absint($parts[0]);
            $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
            if (!self::isDigitalProduct($product)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether an entire WooCommerce order consists strictly of digital items.
     *
     * @param mixed $order Order ID or WC_Order.
     * @return bool
     */
    public static function isDigitalOrder($order) {
        if (is_numeric($order)) {
            $order = function_exists('wc_get_order') ? wc_get_order(absint($order)) : null;
        }

        if (!$order instanceof \WC_Order || $order->get_item_count() < 1) {
            return false;
        }

        foreach ($order->get_items() as $item) {
            $product = method_exists($item, 'get_product') ? $item->get_product() : null;
            if (!self::isDigitalProduct($product)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate unit Stars price for a single product or variation.
     *
     * Returns null if not digital or cannot be priced in Stars.
     *
     * @param mixed $product
     * @param int   $qty
     * @return int|null
     */
    public static function calculateStarsForProduct($product, $qty = 1) {
        $qty = max(1, absint($qty));
        if (is_numeric($product)) {
            $product = function_exists('wc_get_product') ? wc_get_product(absint($product)) : null;
        }

        if (!self::isDigitalProduct($product)) {
            return null;
        }

        $custom_stars = self::getProductCustomStarsPrice($product);
        if ($custom_stars !== null && $custom_stars >= 1) {
            return (int) ($custom_stars * $qty);
        }

        $rate = self::getGlobalRate();
        if ($rate <= 0) {
            return null;
        }

        $price = method_exists($product, 'get_price') ? (float) $product->get_price() : 0.0;
        $stars = ($price * $qty) * $rate;
        return max(1, (int) round($stars));
    }

    /**
     * Calculate total Stars amount for an existing WC_Order.
     *
     * Rules:
     * - Only digital orders can use Stars.
     * - If item has custom Stars price, use it; discounts are applied proportionally.
     * - If item does not have custom Stars price, use item total * global rate.
     * - If global rate is 0 and any item has no custom Stars price, Stars is unavailable (returns 0).
     * - Final amount is an integer >= 1.
     *
     * @param mixed $order Order ID or WC_Order.
     * @return int Total Stars (0 if ineligible).
     */
    public static function calculateStarsForOrder($order) {
        if (is_numeric($order)) {
            $order = function_exists('wc_get_order') ? wc_get_order(absint($order)) : null;
        }

        if (!$order instanceof \WC_Order) {
            return 0;
        }

        if (!self::isEnabled()) {
            return 0;
        }

        if (!self::isDigitalOrder($order)) {
            return 0;
        }

        $rate = self::getGlobalRate();
        $total_stars_float = 0.0;
        $has_items = false;

        foreach ($order->get_items() as $item) {
            $product = method_exists($item, 'get_product') ? $item->get_product() : null;
            if (!$product) {
                return 0;
            }

            $qty = max(1, absint($item->get_quantity()));
            $has_items = true;

            $custom_stars = self::getProductCustomStarsPrice($product);
            if ($custom_stars !== null && $custom_stars >= 1) {
                $line_stars = (float) ($custom_stars * $qty);
                $subtotal = (float) $item->get_subtotal();
                $total = (float) $item->get_total();

                // Apply line item discount ratio if discounted
                if ($subtotal > 0 && $total < $subtotal) {
                    $ratio = max(0.0, $total / $subtotal);
                    $line_stars = $line_stars * $ratio;
                }
                $total_stars_float += $line_stars;
            } else {
                if ($rate <= 0) {
                    // Global rate is 0 and product has no custom price -> Stars disabled for this cart
                    return 0;
                }
                $line_total = (float) $item->get_total();
                $line_stars = $line_total * $rate;
                $total_stars_float += $line_stars;
            }
        }

        if (!$has_items) {
            return 0;
        }

        // Handle order-level discount if not reflected in line totals
        $order_subtotal = (float) $order->get_subtotal();
        $discount_total = (float) $order->get_discount_total();
        if ($order_subtotal > 0 && $discount_total > 0) {
            $items_total_sum = 0.0;
            $items_subtotal_sum = 0.0;
            foreach ($order->get_items() as $item) {
                $items_total_sum += (float) $item->get_total();
                $items_subtotal_sum += (float) $item->get_subtotal();
            }
            if (abs($items_total_sum - $items_subtotal_sum) < 0.01) {
                $ratio = max(0.0, ($order_subtotal - $discount_total) / $order_subtotal);
                $total_stars_float = $total_stars_float * $ratio;
            }
        }

        return max(1, (int) round($total_stars_float));
    }

    /**
     * Calculate total Stars amount for an in-memory or session cart array.
     *
     * @param array|null $cart Cart array [cart_key => quantity].
     * @param string     $coupon_code Optional coupon code to apply.
     * @return int Total Stars (0 if ineligible).
     */
    public static function calculateStarsForCart($cart = null, $coupon_code = '') {
        if ($cart === null && class_exists('\\TeleNexa\\WooBot') && method_exists('\\TeleNexa\\WooBot', 'getCart')) {
            $cart = \TeleNexa\WooBot::getCart();
        }

        if (!is_array($cart) || empty($cart)) {
            return 0;
        }

        if (!self::isEnabled()) {
            return 0;
        }

        if (!self::isDigitalCart($cart)) {
            return 0;
        }

        $rate = self::getGlobalRate();
        $total_stars_float = 0.0;
        $cart_subtotal_currency = 0.0;
        $has_items = false;

        foreach ($cart as $cart_key => $quantity) {
            $qty = max(1, absint($quantity));
            $parts = explode('V', (string) $cart_key, 2);
            $product_id = isset($parts[1]) ? absint($parts[1]) : absint($parts[0]);
            $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
            if (!self::isDigitalProduct($product)) {
                return 0;
            }

            $has_items = true;
            $price = method_exists($product, 'get_price') ? (float) $product->get_price() : 0.0;
            $line_subtotal_currency = $price * $qty;
            $cart_subtotal_currency += $line_subtotal_currency;

            $custom_stars = self::getProductCustomStarsPrice($product);
            if ($custom_stars !== null && $custom_stars >= 1) {
                $line_stars = (float) ($custom_stars * $qty);
            } else {
                if ($rate <= 0) {
                    return 0;
                }
                $line_stars = $line_subtotal_currency * $rate;
            }
            $total_stars_float += $line_stars;
        }

        if (!$has_items) {
            return 0;
        }

        // Apply coupon discount if present in session or passed
        if (empty($coupon_code) && class_exists('\\TeleNexa\\WooBot') && method_exists('\\TeleNexa\\WooBot', 'getSession')) {
            $coupon_code = \TeleNexa\WooBot::getSession('coupon_code');
        }

        if (!empty($coupon_code) && class_exists('\\WC_Coupon')) {
            try {
                $c_obj = new \WC_Coupon($coupon_code);
                if ($c_obj->get_id() && method_exists($c_obj, 'is_valid') && $c_obj->is_valid()) {
                    $discount = 0.0;
                    if ($c_obj->get_discount_type() === 'percent') {
                        $discount = ($cart_subtotal_currency * $c_obj->get_amount()) / 100;
                    } else {
                        $discount = (float) $c_obj->get_amount();
                    }
                    $discount = min($discount, $cart_subtotal_currency);
                    if ($cart_subtotal_currency > 0 && $discount > 0) {
                        $ratio = max(0.0, ($cart_subtotal_currency - $discount) / $cart_subtotal_currency);
                        $total_stars_float = $total_stars_float * $ratio;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore coupon calculation exception
            }
        }

        return max(1, (int) round($total_stars_float));
    }

    /**
     * Calculate Stars for WooCommerce WC()->cart instance.
     *
     * @return int
     */
    public static function calculateStarsForWcCart() {
        if (!function_exists('WC') || !WC()->cart) {
            return 0;
        }

        $items = WC()->cart->get_cart();
        if (empty($items)) {
            return 0;
        }

        $bot_cart = [];
        foreach ($items as $item) {
            $product_id = !empty($item['variation_id']) ? $item['product_id'] . 'V' . $item['variation_id'] : $item['product_id'];
            $bot_cart[$product_id] = $item['quantity'];
        }

        return self::calculateStarsForCart($bot_cart);
    }

    /**
     * Persist the final Stars amount into the order's metadata.
     *
     * @param mixed    $order Order ID or WC_Order.
     * @param int|null $stars_amount Optional precalculated amount.
     * @return int The saved Stars amount (0 if not applicable).
     */
    public static function saveOrderStarsAmount($order, $stars_amount = null) {
        if (is_numeric($order)) {
            $order = function_exists('wc_get_order') ? wc_get_order(absint($order)) : null;
        }

        if (!$order instanceof \WC_Order) {
            return 0;
        }

        if ($stars_amount === null || $stars_amount < 1) {
            $stars_amount = self::calculateStarsForOrder($order);
        }

        if ($stars_amount >= 1) {
            $order->update_meta_data(self::META_ORDER_STARS_AMOUNT, $stars_amount);
            $order->save();
            return $stars_amount;
        }

        return 0;
    }

    /**
     * Automatically calculate and store Stars amount on new WooCommerce orders.
     *
     * @param int $order_id
     */
    public static function onNewOrder($order_id) {
        if (!self::isEnabled()) {
            return;
        }
        self::saveOrderStarsAmount($order_id);
    }

    /**
     * Render product pricing field for simple products in WooCommerce admin.
     */
    public static function renderProductField() {
        if (!function_exists('woocommerce_wp_text_input')) {
            return;
        }

        echo '<div class="options_group show_if_simple show_if_external">';
        woocommerce_wp_text_input([
            'id'                => self::META_PRICE,
            'label'             => __('Telegram Stars Price', 'telenexa-commerce-for-telegram'),
            'placeholder'       => __('e.g. 50 (optional)', 'telenexa-commerce-for-telegram'),
            'description'       => __('Optional dedicated price in Telegram Stars (integer >= 1). If left empty, the global rate will be used as default.', 'telenexa-commerce-for-telegram'),
            'desc_tip'          => true,
            'type'              => 'number',
            'custom_attributes' => [
                'step' => '1',
                'min'  => '1',
            ],
        ]);
        echo '</div>';
    }

    /**
     * Save product pricing field for simple products.
     *
     * @param int $post_id
     */
    public static function saveProductField($post_id) {
        if (!isset($_POST[self::META_PRICE])) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $raw_value = sanitize_text_field(wp_unslash($_POST[self::META_PRICE]));
        $product = function_exists('wc_get_product') ? wc_get_product($post_id) : null;

        if ($raw_value !== '' && is_numeric($raw_value) && (int) $raw_value >= 1) {
            $stars_price = (int) $raw_value;
            if ($product && method_exists($product, 'update_meta_data')) {
                $product->update_meta_data(self::META_PRICE, $stars_price);
                $product->save_meta_data();
            } else {
                update_post_meta($post_id, self::META_PRICE, $stars_price);
            }
        } else {
            if ($product && method_exists($product, 'delete_meta_data')) {
                $product->delete_meta_data(self::META_PRICE);
                $product->save_meta_data();
            } else {
                delete_post_meta($post_id, self::META_PRICE);
            }
        }
    }

    /**
     * Render pricing field for each variation in WooCommerce admin.
     *
     * @param int                  $loop
     * @param array                $variation_data
     * @param \WC_Product_Variation $variation
     */
    public static function renderVariationField($loop, $variation_data, $variation) {
        if (!function_exists('woocommerce_wp_text_input')) {
            return;
        }

        $variation_id = is_object($variation) && method_exists($variation, 'get_id') ? $variation->get_id() : (isset($variation->ID) ? $variation->ID : 0);
        $value = $variation_id ? get_post_meta($variation_id, self::META_PRICE, true) : '';

        woocommerce_wp_text_input([
            'id'                => self::META_PRICE . '_' . $loop,
            'name'              => self::META_PRICE . '[' . $loop . ']',
            'value'             => $value,
            'label'             => __('Telegram Stars Price', 'telenexa-commerce-for-telegram'),
            'placeholder'       => __('e.g. 50 (optional)', 'telenexa-commerce-for-telegram'),
            'description'       => __('Optional dedicated price in Telegram Stars for this variation (integer >= 1).', 'telenexa-commerce-for-telegram'),
            'desc_tip'          => true,
            'type'              => 'number',
            'wrapper_class'     => 'form-row form-row-full',
            'custom_attributes' => [
                'step' => '1',
                'min'  => '1',
            ],
        ]);
    }

    /**
     * Save pricing field for product variations.
     *
     * @param int $variation_id
     * @param int $i
     */
    public static function saveVariationField($variation_id, $i) {
        if (!isset($_POST[self::META_PRICE]) || !is_array($_POST[self::META_PRICE])) {
            return;
        }

        if (!current_user_can('edit_post', $variation_id)) {
            return;
        }

        if (!isset($_POST[self::META_PRICE][$i])) {
            return;
        }

        $raw_value = sanitize_text_field(wp_unslash($_POST[self::META_PRICE][$i]));
        $variation = function_exists('wc_get_product') ? wc_get_product($variation_id) : null;

        if ($raw_value !== '' && is_numeric($raw_value) && (int) $raw_value >= 1) {
            $stars_price = (int) $raw_value;
            if ($variation && method_exists($variation, 'update_meta_data')) {
                $variation->update_meta_data(self::META_PRICE, $stars_price);
                $variation->save_meta_data();
            } else {
                update_post_meta($variation_id, self::META_PRICE, $stars_price);
            }
        } else {
            if ($variation && method_exists($variation, 'delete_meta_data')) {
                $variation->delete_meta_data(self::META_PRICE);
                $variation->save_meta_data();
            } else {
                delete_post_meta($variation_id, self::META_PRICE);
            }
        }
    }

    /**
     * Display Stars amount in WooCommerce checkout table if applicable.
     */
    public static function renderCheckoutStarsNotice() {
        if (!self::isEnabled()) {
            return;
        }

        $stars = self::calculateStarsForWcCart();
        if ($stars > 0) {
            ?>
            <tr class="order-stars-total">
                <th><?php esc_html_e('Payable with Telegram Stars', 'telenexa-commerce-for-telegram'); ?></th>
                <td><strong><?php echo esc_html(number_format_i18n($stars)); ?> ⭐</strong></td>
            </tr>
            <?php
        }
    }

    /**
     * Display Stars amount on frontend order details / thank you screen.
     *
     * @param \WC_Order $order
     */
    public static function renderOrderStarsNotice($order) {
        if (!$order instanceof \WC_Order) {
            return;
        }

        $stars = (int) $order->get_meta(self::META_ORDER_STARS_AMOUNT, true);
        if ($stars > 0) {
            ?>
            <p class="woogram-order-stars-info">
                <strong><?php esc_html_e('Telegram Stars Amount:', 'telenexa-commerce-for-telegram'); ?></strong>
                <span><?php echo esc_html(number_format_i18n($stars)); ?> ⭐</span>
            </p>
            <?php
        }
    }

    /**
     * Display Stars amount and payment details in WooCommerce admin order billing section.
     *
     * @param \WC_Order $order
     */
    public static function renderAdminOrderStars($order) {
        if (!$order instanceof \WC_Order) {
            return;
        }

        $stars_amount = (int) $order->get_meta(self::META_ORDER_STARS_AMOUNT, true);
        if ($stars_amount > 0) {
            $charge_id = (string) $order->get_meta(self::META_ORDER_CHARGE_ID, true);
            echo '<p><strong>' . esc_html__('Telegram Stars', 'telenexa-commerce-for-telegram') . ':</strong><br>' . esc_html(number_format_i18n($stars_amount)) . ' ⭐';
            if ($charge_id !== '') {
                echo '<br><small>' . esc_html__('Charge ID', 'telenexa-commerce-for-telegram') . ': <code>' . esc_html($charge_id) . '</code></small>';
            }
            echo '</p>';
        }
    }

    /**
     * Automatically trigger Telegram Stars refund when WooCommerce order status changes to refunded.
     *
     * @param int $order_id
     */
    public static function handleOrderRefunded($order_id) {
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        if (!$order instanceof \WC_Order) {
            return;
        }

        $provider = $order->get_meta(self::META_ORDER_PROVIDER, true);
        if ($provider !== 'telegram_stars') {
            return;
        }

        // Check if already refunded to prevent duplicate refund attempts
        if ($order->get_meta(self::META_ORDER_REFUNDED, true) === '1') {
            return;
        }

        $charge_id = (string) $order->get_meta(self::META_ORDER_CHARGE_ID, true);
        $user_id = (int) $order->get_meta(self::META_ORDER_USER_ID, true);

        if ($charge_id !== '' && $user_id > 0 && class_exists('\\TeleNexa\\WooBot') && method_exists('\\TeleNexa\\WooBot', 'refundTelegramStarsPayment')) {
            $result = \TeleNexa\WooBot::refundTelegramStarsPayment($user_id, $charge_id);
            if (!is_wp_error($result) && !empty($result['ok'])) {
                $order->update_meta_data(self::META_ORDER_REFUNDED, '1');
                $order->add_order_note(sprintf(__('Telegram Stars payment successfully refunded (Charge ID: %s).', 'telenexa-commerce-for-telegram'), $charge_id));
                $order->save();
            } else {
                $error_msg = is_wp_error($result) ? $result->get_error_message() : (isset($result['description']) ? $result['description'] : __('Unknown error', 'telenexa-commerce-for-telegram'));
                $order->add_order_note(sprintf(__('Failed to refund Telegram Stars payment: %s', 'telenexa-commerce-for-telegram'), $error_msg));
                $order->save();
            }
        }
    }
}
