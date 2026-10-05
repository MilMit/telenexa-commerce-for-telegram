<?php
namespace TeleNexa\Bot;

/**
 * Product Reviews and Customer Ratings for WooBot.
 */
trait ReviewsTrait {

    /**
     * Display customer reviews and star rating for a product.
     */
    public static function showProductReviews($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            $message = self::botTranslate('This product is no longer available.', 'telenexa-commerce-for-telegram');
            self::answerCallback($message);
            self::sendMessage(['text' => $message, 'keyboard' => [[self::btnHome()]]]);
            return;
        }

        $lang = self::getLanguage();
        $comments = get_comments([
            'post_id' => $product_id,
            'status'  => 'approve',
            'type'    => 'review',
            'number'  => 5,
        ]);

        $rating = round($product->get_average_rating(), 1);
        $rating_count = $product->get_rating_count();

        $stars = str_repeat('⭐', (int) round($rating));
        $header = "<b>" . esc_html($product->get_name()) . "</b>\n\n"
            . ($rating_count > 0
                ? "{$stars} <b>{$rating} / 5</b> (" . sprintf(self::botText('reviews.count', '%d دیدگاه', '%d reviews', $lang), $rating_count) . ")\n\n"
                : self::botText('reviews.empty', 'هنوز دیدگاهی برای این محصول ثبت نشده است.', 'No reviews yet for this product.', $lang) . "\n\n"
            );

        $body = "";
        if (!empty($comments)) {
            foreach ($comments as $comment) {
                $rating_val = get_comment_meta($comment->comment_ID, 'rating', true);
                $star_str = $rating_val ? str_repeat('⭐', (int)$rating_val) : '';
                $author = esc_html($comment->comment_author);
                $content = esc_html(wp_trim_words($comment->comment_content, 25));
                $body .= "💬 <b>{$author}</b> {$star_str}\n«{$content}»\n\n";
            }
        }

        $keyboard = [
            [
                ['text' => '🛒 ' . self::botText('product.order', 'سفارش', 'Order', $lang), 'callback_data' => "/ADD2CART{$product_id}"],
                ['text' => '🔙 ' . self::botText('navigation.back', 'بازگشت', 'Back', $lang), 'callback_data' => "/P{$product_id}"]
            ],
            [self::btnHome()]
        ];

        self::answerCallback(self::botText('reviews.title', 'دیدگاه‌های محصول', 'Product Reviews', $lang));
        self::sendMessage([
            'text'     => $header . $body,
            'keyboard' => $keyboard,
        ]);
    }
}
