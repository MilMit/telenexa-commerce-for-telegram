<?php
namespace TeleNexa\Bot;

use TeleNexa\Http\TelegramApiAdapter;

/**
 * Multi-image Gallery, Related Products, and Catalog Extras for WooBot.
 */
trait GalleryTrait {

    /**
     * Send product photo gallery using Telegram sendMediaGroup or individual photos.
     */
    public static function showProductGallery($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            $message = self::botTranslate('This product is no longer available.', 'telenexa-commerce-for-telegram');
            self::answerCallback($message);
            self::sendMessage(['text' => $message, 'keyboard' => [[self::btnHome()]]]);
            return;
        }

        $gallery_ids = $product->get_gallery_image_ids();
        $lang = self::getLanguage();

        if (empty($gallery_ids)) {
            $msg = self::botText('gallery.empty', 'تصاویر بیشتری برای این محصول ثبت نشده است.', 'No additional gallery photos for this product.', $lang);
            self::answerCallback($msg);
            self::sendMessage(['text' => $msg, 'keyboard' => [[
                ['text' => '🔙 ' . self::botText('product.back', 'بازگشت به محصول', 'Back to Product', $lang), 'callback_data' => "/P{$product_id}"],
                self::btnHome(),
            ]]]);
            return;
        }

        self::answerCallback(self::botText('gallery.loading', 'در حال بارگذاری گالری...', 'Loading gallery...', $lang));

        $media = [];
        $count = 0;
        foreach ($gallery_ids as $img_id) {
            if ($count >= 6) break; // Telegram max 10, 6 is optimal for fast mobile loading
            $url = wp_get_attachment_image_url($img_id, 'large');
            if ($url) {
                $media[] = [
                    'type'    => 'photo',
                    'media'   => $url,
                    'caption' => ($count === 0) ? "🖼️ " . esc_html($product->get_name()) : '',
                ];
                $count++;
            }
        }

        if (!empty($media)) {
            $chat_id = !empty(self::$group_chat_id) ? self::fixPersianChar(self::$group_chat_id) : self::fixPersianChar(self::$chat_id);
            $result = TelegramApiAdapter::request('sendMediaGroup', [
                'chat_id' => $chat_id,
                'media'   => $media,
            ]);
            self::rememberNavigationMessage($result);
        }

        $keyboard = [
            [
                ['text' => '🛒 ' . self::botText('product.order', 'سفارش', 'Order', $lang), 'callback_data' => "/ADD2CART{$product_id}"],
                ['text' => '🔎 ' . self::botText('product.back', 'بازگشت به محصول', 'Back to Product', $lang), 'callback_data' => "/P{$product_id}"]
            ],
            [self::btnHome()]
        ];

        self::sendMessage([
            'text'     => "🖼️ " . sprintf(self::botText('gallery.title', 'گالری تصاویر «%s»', 'Gallery for "%s"', $lang), esc_html($product->get_name())),
            'keyboard' => $keyboard,
        ]);
    }

    /**
     * Display related products for a given product.
     */
    public static function showRelatedProducts($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            $message = self::botTranslate('This product is no longer available.', 'telenexa-commerce-for-telegram');
            self::answerCallback($message);
            self::sendMessage(['text' => $message, 'keyboard' => [[self::btnHome()]]]);
            return;
        }

        $related_ids = wc_get_related_products($product_id, 4);
        $lang = self::getLanguage();

        if (empty($related_ids)) {
            $message = self::botText('related.empty', 'محصول مرتبطی یافت نشد.', 'No related products found.', $lang);
            self::answerCallback($message);
            self::sendMessage(['text' => $message, 'keyboard' => [[self::btnHome()]]]);
            return;
        }

        self::answerCallback(self::botText('related.title', 'محصولات مرتبط', 'Related products', $lang));

        $keyboard = [];
        foreach ($related_ids as $r_id) {
            $r_prod = wc_get_product($r_id);
            if (!$r_prod || !$r_prod->is_visible()) continue;
            $keyboard[] = [[
                'text'          => '📦 ' . esc_html($r_prod->get_name()) . ' (' . strip_tags($r_prod->get_price_html()) . ')',
                'callback_data' => "/P{$r_id}"
            ]];
        }
        if (empty($keyboard)) {
            $message = self::botText('related.empty', 'محصول مرتبطی یافت نشد.', 'No related products found.', $lang);
            self::answerCallback($message);
            self::sendMessage(['text' => $message, 'keyboard' => [[self::btnHome()]]]);
            return;
        }
        $keyboard[] = [
            ['text' => '🔙 ' . self::botText('product.back', 'بازگشت به محصول', 'Back to Product', $lang), 'callback_data' => "/P{$product_id}"],
            self::btnHome()
        ];

        $title = "🔗 <b>" . self::botText('related.heading', 'محصولات مرتبط و مشابه', 'Related Products', $lang) . ":</b>";
        self::sendMessage(['text' => $title, 'keyboard' => $keyboard]);
    }

    /**
     * Display Best Sellers products.
     */
    public static function showBestSellers($page = 1) {
        $results = self::queryProducts([
            'orderby'      => 'popularity',
            'only_instock' => true,
            'page'         => $page,
            'per_page'     => self::$product_per_page ?: 5,
        ]);

        $lang = self::getLanguage();
        $header = "🔥 <b>" . self::botText('catalog.best_sellers', 'پرفروش‌ترین محصولات فروشگاه', 'Best Selling Products', $lang) . "</b>";
        self::sendMessage(['text' => $header]);

        foreach ($results['products'] as $prod) {
            if (!$prod instanceof \WC_Product) $prod = wc_get_product($prod);
            if ($prod && $prod->is_visible()) {
                self::renderProductCard($prod);
            }
        }
    }

    /**
     * Display On Sale (Discounted) products.
     */
    public static function showOnSaleProducts($page = 1) {
        $results = self::queryProducts([
            'orderby'      => 'on_sale',
            'only_instock' => true,
            'page'         => $page,
            'per_page'     => self::$product_per_page ?: 5,
        ]);

        $lang = self::getLanguage();
        $header = "🏷️ <b>" . self::botText('catalog.sale_products', 'محصولات دارای تخفیف ویژه', 'Products on Special Discount', $lang) . "</b>";
        self::sendMessage(['text' => $header]);

        foreach ($results['products'] as $prod) {
            if (!$prod instanceof \WC_Product) $prod = wc_get_product($prod);
            if ($prod && $prod->is_visible()) {
                self::renderProductCard($prod);
            }
        }
    }
}
