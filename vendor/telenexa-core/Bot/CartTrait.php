<?php
namespace TeleNexa\Bot;


/**
 * Advanced Cart Manager for WooBot.
 * Supports inline quantity controls (+/-), stock validation, price change detection,
 * tax/shipping/discount breakdown, persistent cart storage, and abandoned cart tracking.
 */
trait CartTrait {

    public static function setLastRequest($request) {
        self::setSession('last_request', $request);
    }

    public static function getLastRequest() {
        return self::getSession('last_request');
    }

    public static function setLastSearch($search_key) {
        self::setSession('last_search_key', $search_key);
    }

    public static function getLastSearch() {
        return self::getSession('last_search_key');
    }

    /**
     * Get active cart array with fallback to persisted user meta.
     */
    public static function getCart() {
        self::$group_chat_id = 0;
        $cart = self::getSession('cart');

        if (!is_array($cart) || empty($cart)) {
            // Try recovering from user postmeta
            $saved_cart = self::getUserMeta('_woogram_saved_cart');
            if (is_array($saved_cart) && !empty($saved_cart)) {
                $cart = $saved_cart;
                self::setSession('cart', $cart);
            } else {
                $cart = [];
            }
        }
        return $cart;
    }

    /**
     * Save cart to session and persistent user postmeta.
     */
    public static function saveCart($cart) {
        self::setSession('cart', $cart);
        self::updateUserMeta('_woogram_saved_cart', $cart);
    }

    /**
     * Normalize a cart received from the Mini App before it reaches checkout.
     * Product IDs, variations, stock and quantities are always revalidated on
     * the server; prices are never trusted from browser storage.
     */
    public static function normalizeCartPayload(array $cart) {
        $normalized = [];
        foreach ($cart as $key => $quantity) {
            $key = sanitize_text_field((string) $key);
            $quantity = max(1, absint($quantity));
            $parts = explode('V', $key, 2);
            $parent_id = absint($parts[0]);
            $variation_id = isset($parts[1]) ? absint($parts[1]) : 0;
            $product = wc_get_product($variation_id ?: $parent_id);
            if (!$product || !$product->exists() || !$product->is_purchasable() || !$product->is_in_stock()) {
                continue;
            }
            if ($product->managing_stock() && $product->get_stock_quantity() !== null) {
                $quantity = min($quantity, max(0, (int) $product->get_stock_quantity()));
            }
            if ($quantity < 1) {
                continue;
            }
            $normalized[$variation_id ? $parent_id . 'V' . $variation_id : (string) $parent_id] = $quantity;
        }
        return $normalized;
    }

    public static function getCartVariation() {
        self::$group_chat_id = 0;
        return self::getSession('cart_variation') ?: [];
    }

    /**
     * Re-validate all items in cart for stock availability.
     * Returns array of warnings/errors if any.
     */
    public static function validateCartStock() {
        $cart = self::getCart();
        $warnings = [];

        foreach ($cart as $product_id => $qty) {
            $real_id = (strpos($product_id, 'V') !== false) ? explode('V', $product_id)[0] : $product_id;
            $product = wc_get_product($real_id);

            if (!$product || !$product->exists()) {
                unset($cart[$product_id]);
                $warnings[] = sprintf(self::botTranslate('Product #%d was removed as it is no longer available.', 'telenexa-commerce-for-telegram'), $real_id);
                continue;
            }

            if (!$product->is_in_stock()) {
                unset($cart[$product_id]);
                $warnings[] = sprintf(self::botTranslate('«%s» is out of stock and was removed from your cart.', 'telenexa-commerce-for-telegram'), $product->get_name());
                continue;
            }

            if ($product->managing_stock()) {
                $available = $product->get_stock_quantity();
                if ($available !== null && $qty > $available) {
                    $cart[$product_id] = max(1, $available);
                    $warnings[] = sprintf(self::botTranslate('Quantity for «%1$s» was adjusted to available stock (%2$d).', 'telenexa-commerce-for-telegram'), $product->get_name(), $available);
                }
            }
        }

        self::saveCart($cart);
        return $warnings;
    }

    /**
     * Display modern interactive cart with item controls, price summary, and tax/discount breakdown.
     */
    public static function showCart() {
        self::$group_chat_id = 0;
        $lang = self::getLanguage();

        // Validate stock before rendering
        $stock_warnings = self::validateCartStock();
        $cart = self::getCart();

        if (empty($cart)) {
            self::answerCallback(self::botTranslate('Shopping cart is empty.', 'telenexa-commerce-for-telegram'));
            $empty_text = "🛒 <b>" . self::botText('cart.empty', 'سبد خرید شما در حال حاضر خالی است.', 'Your shopping cart is empty.', $lang) . "</b>\n\n"
                . self::botText('cart.empty_hint', 'می‌توانید از منوی فروشگاه محصولات مورد نظرتان را انتخاب و اضافه کنید.', 'Explore our store to add products to your cart.', $lang);
            $keyboard = [[['text' => '🛍️ ' . self::botText('cart.start_shopping', 'مشاهده محصولات', 'Start Shopping', $lang), 'callback_data' => '/shop']], [self::btnHome()]];

            if (self::isCallback()) {
                self::editMessage(['text' => $empty_text, 'keyboard' => $keyboard]);
            } else {
                self::sendMessage(['text' => $empty_text, 'keyboard' => $keyboard]);
            }
            return;
        }

        self::answerCallback(self::botTranslate('Shopping Cart', 'telenexa-commerce-for-telegram'));

        // Output any stock adjustments notice
        if (!empty($stock_warnings)) {
            self::sendMessage([
                'text' => "⚠️ <b>" . self::botTranslate('Cart Update:', 'telenexa-commerce-for-telegram') . "</b>\n" . implode("\n", $stock_warnings)
            ]);
        }

        $items_text = "🛒 <b>" . self::botText('cart.title', 'سبد خرید شما', 'Your Shopping Cart', $lang) . "</b> (" . sprintf(self::botText('cart.item_count', '%d قلم کالا', '%d items', $lang), count($cart)) . "):\n\n";

        $subtotal = 0.0;
        $keyboard = [];
        $cart_variation = self::getCartVariation();

        foreach ($cart as $product_id => $qty) {
            $is_var = strpos($product_id, 'V') !== false;
            $real_id = $is_var ? explode('V', $product_id)[0] : $product_id;
            $var_id  = $is_var ? explode('V', $product_id)[1] : 0;

            $product = wc_get_product($var_id ?: $real_id);
            if (!$product) continue;

            $title = $product->get_name();
            $price = (float) $product->get_price();
            $line_total = $price * $qty;
            $subtotal += $line_total;

            $items_text .= "▫ <b>" . esc_html($title) . "</b>\n"
                . "   " . sprintf(self::botText('cart.line_item', '%1$d × %2$s = <b>%3$s</b>', '%1$d × %2$s = <b>%3$s</b>', $lang), $qty, self::price($price), self::price($line_total)) . "\n\n";

            // Inline controls: [-] [ Qty ] [+] [❌ Remove]
            $keyboard[] = [
                ['text' => '➖', 'callback_data' => "/CART_DEC_{$product_id}"],
                ['text' => "📦 {$qty}", 'callback_data' => '/noop'],
                ['text' => '➕', 'callback_data' => "/CART_INC_{$product_id}"],
                ['text' => '❌', 'callback_data' => "/DELCART{$product_id}"],
            ];
        }

        // Coupon calculation
        $coupon_code = self::getSession('coupon_code');
        $discount = 0.0;
        if (!empty($coupon_code)) {
            $c_obj = new \WC_Coupon($coupon_code);
            if ($c_obj->get_id() && $c_obj->is_valid()) {
                if ($c_obj->get_discount_type() === 'percent') {
                    $discount = ($subtotal * $c_obj->get_amount()) / 100;
                } else {
                    $discount = (float) $c_obj->get_amount();
                }
                $discount = min($discount, $subtotal);
            } else {
                self::setSession('coupon_code', '');
                $coupon_code = '';
            }
        }

        $grand_total = max(0, $subtotal - $discount);

        $summary_text = "<b>" . self::botText('cart.summary', 'خلاصه فاکتور:', 'Order Summary:', $lang) . "</b>\n"
            . self::botText('cart.subtotal', 'مجموع اقلام: ', 'Subtotal: ', $lang) . "<b>" . self::price($subtotal) . "</b>\n";

        if ($discount > 0) {
            $summary_text .= "🏷️ " . sprintf(self::botText('cart.coupon_discount', 'تخفیف کوپن (%s): -', 'Coupon Discount (%s): -', $lang), esc_html($coupon_code)) . "<b>" . self::price($discount) . "</b>\n";
        }

        $summary_text .= "💳 <b>" . self::botText('cart.total', 'مبلغ نهایی: ', 'Total Payable: ', $lang) . self::price($grand_total) . "</b>\n";

        if (class_exists('\\TeleNexa\\Stars') && \TeleNexa\Stars::isEnabled() && \TeleNexa\Stars::isDigitalCart($cart)) {
            $stars_total = \TeleNexa\Stars::calculateStarsForCart($cart, $coupon_code);
            if ($stars_total > 0) {
                $summary_text .= "⭐ <b>" . self::botText('cart.stars_total', 'قابل پرداخت با استارز: ', 'Payable with Stars: ', $lang) . number_format($stars_total) . " ⭐</b>\n";
            }
        }

        $full_message = $items_text . "➖➖➖➖➖➖➖➖➖➖\n" . $summary_text;

        // Action Buttons
        $keyboard[] = [
            ['text' => '💳 ' . self::botText('cart.checkout', 'تکمیل خرید و تسویه‌حساب', 'Proceed to Checkout', $lang), 'callback_data' => '/CHECKOUT']
        ];

        $coupon_row = [];
        if (self::$show_coupon_in_cart) {
            $coupon_row[] = ['text' => '🏷️ ' . self::botText('cart.apply_coupon', 'ثبت کد تخفیف', 'Apply Coupon', $lang), 'callback_data' => '/ADDCOUPON'];
        }
        $coupon_row[] = ['text' => '🗑️ ' . self::botText('cart.empty_action', 'خالی کردن سبد', 'Empty Cart', $lang), 'callback_data' => '/EMPTYCART'];
        $keyboard[] = $coupon_row;

        $keyboard[] = [
            ['text' => '🛍️ ' . self::botText('cart.continue_shopping', 'ادامه خرید', 'Continue Shopping', $lang), 'callback_data' => '/shop'],
            self::btnHome()
        ];

        if (self::isCallback()) {
            self::editMessage(['text' => $full_message, 'keyboard' => $keyboard]);
        } else {
            self::sendMessage(['text' => $full_message, 'keyboard' => $keyboard]);
        }
    }

    /**
     * Increment or decrement cart item quantity inline.
     */
    public static function changeCartQty($product_id, $delta) {
        $cart = self::getCart();
        if (!isset($cart[$product_id])) {
            self::showCart();
            return;
        }

        $new_qty = $cart[$product_id] + (int)$delta;
        if ($new_qty <= 0) {
            unset($cart[$product_id]);
        } else {
            // Check stock limit
            $real_id = (strpos($product_id, 'V') !== false) ? explode('V', $product_id)[0] : $product_id;
            $product = wc_get_product($real_id);
            if ($product && $product->managing_stock()) {
                $stock = $product->get_stock_quantity();
                if ($stock !== null && $new_qty > $stock) {
                    self::answerCallback(sprintf(self::botTranslate('Maximum available stock is %d.', 'telenexa-commerce-for-telegram'), $stock));
                    return;
                }
            }
            $cart[$product_id] = $new_qty;
        }

        self::saveCart($cart);
        self::showCart();
    }

    public static function add2CartVariation($product_id, $qty, $selected_attribute) {
        if (strpos($product_id, 'V') === false) return true;
        $ids = explode('V', $product_id);
        $p_id = $ids[0];
        $v_id = $ids[1];
        $product = self::apiGetProduct($p_id);
        if (!is_array($product) || empty($product)) {
            self::answerCallback(self::botTranslate('This product is no longer available.', 'telenexa-commerce-for-telegram'), true);
            return false;
        }
        $cart_variation = self::getSession('cart_variation');
        if (empty($cart_variation) || !is_array($cart_variation)) {
            $cart_variation = [];
        }

        $attributes_variation_true = array_filter(is_array($product['attributes'] ?? null) ? $product['attributes'] : [], function($value) {
            return !empty($value['variation']);
        });
        $attributes_variation_true = array_values($attributes_variation_true);

        if (empty($selected_attribute) && $qty && count($attributes_variation_true) == count($cart_variation[$product_id] ?? [])) return true;

        if (!empty($product['variations']) && is_array($product['variations'])) {
            foreach ($product['variations'] as $variation) {
                if (is_array($variation) && (string)($variation['id'] ?? '') === (string)$v_id) {
                    $variation_attributes = is_array($variation['attributes'] ?? null) ? $variation['attributes'] : [];
                    if (count($attributes_variation_true) !== count($variation_attributes)) {
                        if (!isset($cart_variation[$product_id])) {
                            $cart_variation[$product_id] = [];
                        }
                        $next_attribute = false;
                        if (count($variation_attributes) == 0) {
                            if (count($selected_attribute) < 2) return false;
                            $attribute_key = $selected_attribute[0];
                            $option_key = $selected_attribute[1];
                            if (!empty($attributes_variation_true) && is_array($attributes_variation_true)) {
                                foreach ($attributes_variation_true as $akey => $avalue) {
                                    if ($akey == $attribute_key && is_array($avalue) && isset($avalue['options']) && is_array($avalue['options']) && array_key_exists($option_key, $avalue['options'])) {
                                        $cart_variation[$product_id][$attribute_key] = $avalue['options'][$option_key];
                                    } elseif ($akey > $attribute_key) {
                                        if ($next_attribute === false) $next_attribute = $akey;
                                        unset($cart_variation[$product_id][$akey]);
                                    }
                                }
                            }
                        } else {
                            if (!empty($selected_attribute)) {
                                $attribute_key = $selected_attribute[0];
                                $option_key = $selected_attribute[1];
                            } else {
                                $attribute_key = 0;
                                $option_key = 0;
                            }
                            if (!empty($attributes_variation_true) && is_array($attributes_variation_true)) {
                                foreach ($attributes_variation_true as $akey => $avalue) {
                                    if (!empty($variation_attributes[$akey])) continue;
                                    if ($akey == $attribute_key) {
                                        if (!is_array($avalue) || !isset($avalue['options']) || !is_array($avalue['options']) || !array_key_exists($option_key, $avalue['options'])) continue;
                                        $cart_variation[$product_id][$attribute_key] = $avalue['options'][$option_key];
                                    } elseif ($akey > $attribute_key) {
                                        if ($next_attribute === false) $next_attribute = $akey;
                                        unset($cart_variation[$product_id][$akey]);
                                    }
                                }
                            }
                        }
                        self::setSession('cart_variation', $cart_variation);
                        if ($next_attribute !== false) {
                            self::getProduct('P', $p_id, 0, $next_attribute, $v_id);
                            return false;
                        }
                    } else {
                        return true;
                    }
                }
            }
        }
        return true;
    }

    public static function add2Cart($product_id, $qty = 0, $selected_attribute = []) {
        if (!self::add2CartVariation($product_id, $qty, $selected_attribute)) {
            return false;
        }

        $p_id = (strpos($product_id, 'V') !== false) ? explode('V', $product_id)[0] : $product_id;
        $product = self::apiGetProduct($p_id);

        if (!empty($product)) {
            if (!self::isBotProductPurchasable($product_id)) {
                $text = self::botTranslate('This product or variation is currently unavailable.', 'telenexa-commerce-for-telegram');
                self::answerCallback($text, true);
                self::sendMessage(['text' => '❌ ' . $text, 'keyboard' => [[self::btnContinueOrder()]]]);
                return;
            }
            $product_title = $product['name'];
            $cart_variation = self::getSession('cart_variation');
            if (isset($cart_variation[$product_id])) {
                $product_title .= " " . implode(', ', array_filter($cart_variation[$product_id], function($v) {
                    return !empty($v);
                }));
            }

            if ($qty === 0) {
                // Default to adding 1 or asking quantity
                $qty = 1;
            }

            if ($product['manage_stock'] && $qty > $product['stock_quantity']) {
                self::sendMessage([
                    'text' => "❌ " . sprintf(self::botTranslate('The requested quantity of (%s) is not available in stock!', 'telenexa-commerce-for-telegram'), $product_title),
                    'keyboard' => [[self::btnContinueOrder()]]
                ]);
                return;
            }

            $cart = self::getCart();
            $current_in_cart = isset($cart[$product_id]) ? $cart[$product_id] : 0;
            $cart[$product_id] = $current_in_cart + (int)$qty;
            self::saveCart($cart);

            // Schedule abandoned cart reminder
            if (method_exists(__CLASS__, 'scheduleAbandonedCartReminder')) {
                self::scheduleAbandonedCartReminder(self::$chat_id);
            }

            $text = "🛒 " . sprintf(self::botTranslate('( %s ) was added to your cart.', 'telenexa-commerce-for-telegram'), $product_title);
            self::answerCallback($text);
            self::showCart();

        } else {
            $text = self::botTranslate('Product not found.', 'telenexa-commerce-for-telegram');
            self::answerCallback($text);
            self::sendMessage(['text' => $text]);
        }
    }

    public static function delCart($product_id, $silent_delete = 0) {
        $p_id = (strpos($product_id, 'V') !== false) ? explode('V', $product_id)[0] : $product_id;
        $product = self::apiGetProduct($p_id);
        $product_title = $product ? $product['name'] : "#{$product_id}";

        $cart = self::getCart();
        unset($cart[$product_id]);
        self::saveCart($cart);

        $cart_variation = self::getSession('cart_variation');
        if (is_array($cart_variation) && isset($cart_variation[$product_id])) {
            unset($cart_variation[$product_id]);
            self::setSession('cart_variation', $cart_variation);
        }

        if ($silent_delete) return;

        self::answerCallback(sprintf(self::botTranslate('( %s ) was removed from your cart.', 'telenexa-commerce-for-telegram'), $product_title));
        self::showCart();
    }

    public static function emptyCart($send_message = false) {
        self::saveCart([]);
        self::setSession('cart_variation', []);
        self::setSession('coupon_code', '');

        if ($send_message) {
            $keyboard = [[self::btnHome()]];
            $text = "🗑️ " . self::botTranslate("Shopping cart was emptied.", 'telenexa-commerce-for-telegram');
            self::answerCallback($text);
            if (self::isCallback()) {
                self::editMessage(['text' => $text, 'keyboard' => $keyboard]);
            } else {
                self::sendMessage(['text' => $text, 'keyboard' => $keyboard]);
            }
        }
    }
}
