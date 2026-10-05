<?php
namespace TeleNexa\Bot;

/**
 * High-performance Wishlist and Price Drop Alerts for WooBot.
 * Automatically synchronizes with WooCommerce customer accounts, cleans up deleted products,
 * and tracks price drops for subscribed customers.
 */
trait WishlistTrait {

    /**
     * Get valid wishlist product IDs with automatic cleanup of deleted products.
     */
    public static function getWishlist() {
        $wishlist = self::getSession('wishlist');

        if (!is_array($wishlist) || empty($wishlist)) {
            $saved = self::getUserMeta('_woogram_wishlist');
            if (is_array($saved)) {
                $wishlist = $saved;
                self::setSession('wishlist', $wishlist);
            } else {
                $wishlist = [];
            }
        }

        // Clean up deleted products
        $valid = [];
        $has_changes = false;
        foreach ($wishlist as $p_id) {
            $p_id = absint($p_id);
            if (!$p_id) continue;
            $prod = wc_get_product($p_id);
            if ($prod && $prod->exists()) {
                $valid[] = $p_id;
            } else {
                $has_changes = true;
            }
        }

        if ($has_changes) {
            self::saveWishlist($valid);
        }

        return $valid;
    }

    /**
     * Save wishlist to session, Telegram user postmeta, and linked WooCommerce customer.
     */
    public static function saveWishlist(array $wishlist) {
        $wishlist = array_values(array_unique(array_map('absint', $wishlist)));
        self::setSession('wishlist', $wishlist);
        self::updateUserMeta('_woogram_wishlist', $wishlist);

        // Sync with WooCommerce user if linked
        $wp_user_id = self::getUserMeta('_woocommerce_customer_id');
        if ($wp_user_id && absint($wp_user_id) > 0) {
            update_user_meta($wp_user_id, '_woogram_wishlist', $wishlist);
        }
    }

    public static function isInWishlist($product_id) {
        return in_array(absint($product_id), self::getWishlist(), true);
    }

    /**
     * Toggle product in wishlist and record price for drop tracking.
     */
    public static function toggleWishlist($product_id) {
        $product_id = absint($product_id);
        if (!$product_id) return false;

        $wishlist = self::getWishlist();
        $prices = self::getUserMeta('_woogram_wishlist_prices') ?: [];

        if (in_array($product_id, $wishlist, true)) {
            $wishlist = array_values(array_diff($wishlist, [$product_id]));
            unset($prices[$product_id]);
            $added = false;
        } else {
            $wishlist[] = $product_id;
            $prod = wc_get_product($product_id);
            if ($prod) {
                $prices[$product_id] = (float) $prod->get_price();
            }
            $added = true;
        }

        self::saveWishlist($wishlist);
        self::updateUserMeta('_woogram_wishlist_prices', $prices);
        return $added;
    }

    /**
     * Show interactive wishlist view.
     */
    public static function showWishlist() {
        $wishlist = self::getWishlist();
        $lang = self::getLanguage();

        if (empty($wishlist)) {
            $empty_text = "❤️ <b>" . self::botText('wishlist.empty', 'لیست علاقه‌مندی‌های شما خالی است.', 'Your wishlist is empty.', $lang) . "</b>\n\n"
                . self::botText('wishlist.empty_hint', 'می‌توانید با کلیک روی دکمه قلب در هر محصول، آن را برای بعد در اینجا ذخیره کنید.', 'You can save items by tapping the heart button on any product.', $lang);

            $keyboard = [
                [['text' => '🛍️ ' . self::botText('wishlist.explore', 'مشاهده محصولات', 'Explore Products', $lang), 'callback_data' => '/shop']],
                [self::btnHome()]
            ];

            if (self::isCallback()) {
                self::editMessage(['text' => $empty_text, 'keyboard' => $keyboard]);
            } else {
                self::sendMessage(['text' => $empty_text, 'keyboard' => $keyboard]);
            }
            return;
        }

        self::answerCallback(self::botText('wishlist.title', 'علاقه‌مندی‌ها', 'Wishlist', $lang));

        $header = "❤️ <b>" . self::botText('wishlist.heading', 'لیست محصولات ذخیره‌شده شما', 'Saved Products (Wishlist)', $lang) . ":</b>\n\n";

        $keyboard = [];
        foreach ($wishlist as $p_id) {
            $prod = wc_get_product($p_id);
            if (!$prod) continue;

            $title = $prod->get_name();
            $price = strip_tags($prod->get_price_html());

            $keyboard[] = [
                ['text' => "❤️ {$title} ({$price})", 'callback_data' => "/P{$p_id}"]
            ];
            $keyboard[] = [
                ['text' => '🛒 ' . self::botText('wishlist.add_to_cart', 'افزودن به سبد', 'Add to Cart', $lang), 'callback_data' => "/ADD2CART{$p_id}"],
                ['text' => '💔 ' . self::botText('wishlist.remove', 'حذف', 'Remove', $lang), 'callback_data' => "/WISHLIST{$p_id}"]
            ];
        }

        $keyboard[] = [self::btnHome()];

        if (self::isCallback()) {
            self::editMessage(['text' => $header, 'keyboard' => $keyboard]);
        } else {
            self::sendMessage(['text' => $header, 'keyboard' => $keyboard]);
        }
    }

    public static function btnWishlist($product_id) {
        $saved = self::isInWishlist($product_id);
        $lang = self::getLanguage();
        return [
            'text'          => $saved ? self::botText('wishlist.remove_action', '💔 حذف از علاقه‌ها', '💔 Remove Wishlist', $lang) : self::botText('wishlist.add_action', '❤️ علاقه‌مندی', '❤️ Add to Wishlist', $lang),
            'callback_data' => '/WISHLIST' . absint($product_id),
        ];
    }

    /**
     * Check if product price decreased and alert users who have it in wishlist.
     */
    public static function checkPriceDropAlerts($product_id, $new_price) {
        global $wpdb;
        $product_id = absint($product_id);
        $new_price = (float) $new_price;
        if (!$product_id || $new_price <= 0) return;

        // Query users who have this product in their wishlist
        $users = $wpdb->get_results($wpdb->prepare("
            SELECT post_title as chat_id, meta_value as wishlist_data
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_woogram_wishlist'
            WHERE p.post_type = 'woogram_user'
            AND pm.meta_value LIKE %s
        ", '%"' . $product_id . '"%'));

        if (empty($users)) return;

        $product = wc_get_product($product_id);
        if (!$product) return;

        $msg = "🔥 <b>" . self::botTranslate('Price Drop Alert!', 'telenexa-commerce-for-telegram') . "</b>\n\n"
            . sprintf(self::botTranslate('A product in your wishlist has a price drop: <b>%s</b>', 'telenexa-commerce-for-telegram'), esc_html($product->get_name())) . "\n"
            . sprintf(self::botTranslate('New Price: <b>%s</b>', 'telenexa-commerce-for-telegram'), strip_tags($product->get_price_html())) . "\n\n"
            . self::botTranslate('Order now while the discount is active!', 'telenexa-commerce-for-telegram');

        $keyboard = [
            [['text' => '🛒 ' . self::botTranslate('Order Now', 'telenexa-commerce-for-telegram'), 'callback_data' => "/ADD2CART{$product_id}"]],
            [['text' => '🔎 ' . self::botTranslate('View Product', 'telenexa-commerce-for-telegram'), 'callback_data' => "/P{$product_id}"]],
            [self::btnHome()]
        ];

        foreach ($users as $u) {
            $chat_id = self::fixPersianChar($u->chat_id);
            if (!empty($chat_id)) {
                self::sendMessage([
                    'chat_id'  => $chat_id,
                    'text'     => $msg,
                    'keyboard' => $keyboard,
                ]);
                usleep(30000);
            }
        }
    }
}
