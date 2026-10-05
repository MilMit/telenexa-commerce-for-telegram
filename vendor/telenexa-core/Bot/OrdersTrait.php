<?php
namespace TeleNexa\Bot;

/**
 * High-performance Orders Manager for WooBot.
 * Supports status filtering, one-click reordering, direct signed payment,
 * digital file downloads, and HPOS-compatible pagination.
 */
trait OrdersTrait {

    public static function toPersian($text) {
        $src = array('0','1','2','3','4','5','6','7','8','9');
        $des = array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹');
        return str_ireplace($src, $des, $text);
    }

    /**
     * Redirect payment with HMAC signature validation and expiration check.
     */
    public static function redirectPayment($order_id, $key, $payment_method) {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) return;

        $expires = isset($_GET['expires']) ? (int)$_GET['expires'] : 0;
        $sig     = isset($_GET['sig']) ? sanitize_text_field($_GET['sig']) : '';

        // If signed URL provided, verify it
        if (!empty($sig) && !empty($expires)) {
            if (!self::verifyPaymentSignature($order_id, $key, $payment_method, $expires, $sig)) {
                wp_die(self::botTranslate('Payment link has expired or is invalid. Please request a new link from the bot.', 'telenexa-commerce-for-telegram'));
            }
        }

        $url = $order->get_checkout_payment_url();
        $payment_method_list = self::wooGetPayments();

        if (!empty($url) && !empty($key) && !empty($payment_method) && $key === self::getKeyFromUrl($url) && !empty($payment_method_list[$payment_method])) {
            $pay_method = $payment_method_list[$payment_method]['object'];
            $payment = $pay_method->process_payment($order);
            $url = isset($payment['redirect']) ? $payment['redirect'] : $url;

            if (strpos($url, 'order-received') !== false) {
                self::orderStatusChange($order_id, 'received');
            }

            $order->set_payment_method($payment_method);
            if (method_exists($order, 'save')) $order->save();
            wp_redirect($url);
            exit;
        }
    }

    /**
     * Handle order status change from WooCommerce hooks.
     */
    public static function orderStatusChange($order_id, $status) {
        $order = wc_get_order($order_id);
        if ($order instanceof \WC_Order) {
            self::$chat_id = self::fixPersianChar($order->get_meta('_chat_id', true));
            self::initSession();
            if (method_exists(__CLASS__, 'step9OrderChange')) {
                self::step9OrderChange($order_id, $status);
            }
            if (method_exists(__CLASS__, 'onOrderStatusChanged')) {
                self::onOrderStatusChanged($order_id, '', $status);
            }
            self::updateSession();
        }
    }

    /**
     * Track customer orders with status filter buttons, pagination, and ownership verification.
     */
    public static function trackOrder($page_number = 1, $status_filter = '') {
        $chat_id = self::fixPersianChar((string) self::$chat_id);
        if ($chat_id === '') {
            self::sendMessage(['text' => self::botTranslate('No user identity was found for this chat.', 'telenexa-commerce-for-telegram'), 'keyboard' => [[self::btnHome()]]]);
            return;
        }

        $page_number = max(1, absint($page_number));
        $lang = self::getLanguage();

        // Get filter from session if not explicitly passed
        if (empty($status_filter)) {
            $status_filter = self::getSession('order_track_filter') ?: '';
        } else {
            if ($status_filter === 'all') $status_filter = '';
            self::setSession('order_track_filter', $status_filter);
        }

        $query_result = self::getCustomerOrders($chat_id, $page_number, 4, $status_filter);
        $orders = $query_result['orders'];
        $total = $query_result['total'];
        $max_pages = $query_result['max_pages'];

        // Status Filter Buttons Row
        $filter_keyboard = [
            [
                ['text' => ($status_filter === '' ? '✅ ' : '') . self::botText('orders.all', 'همه', 'All', $lang), 'callback_data' => '/TRACKFILTER_all'],
                ['text' => ($status_filter === 'processing' ? '✅ ' : '') . self::botText('orders.processing', 'جاری', 'Processing', $lang), 'callback_data' => '/TRACKFILTER_processing'],
                ['text' => ($status_filter === 'completed' ? '✅ ' : '') . self::botText('orders.completed', 'تکمیل شده', 'Completed', $lang), 'callback_data' => '/TRACKFILTER_completed'],
                ['text' => ($status_filter === 'cancelled' ? '✅ ' : '') . self::botText('orders.cancelled', 'لغو شده', 'Cancelled', $lang), 'callback_data' => '/TRACKFILTER_cancelled'],
            ]
        ];

        $header_text = "<b>" . self::botText('orders.title', '📦 پیگیری سفارش‌های من', '📦 My Orders', $lang) . "</b> (" . sprintf(self::botText('orders.count', '%d سفارش', '%d orders', $lang), $total) . ")"
            . ($max_pages > 1 ? sprintf(self::botText('pagination.page_of', ' - صفحه %1$d از %2$d', ' - Page %1$d of %2$d', $lang), $page_number, $max_pages) : '');

        self::sendMessage(['text' => $header_text, 'keyboard' => $filter_keyboard]);

        if (empty($orders)) {
            $empty_text = "📦 " . self::botText('orders.empty', 'هیچ سفارشی در این وضعیت یافت نشد.', 'No orders found matching this filter.', $lang);
            self::sendMessage(['text' => $empty_text, 'keyboard' => [[self::btnHome()]]]);
            return;
        }

        foreach ($orders as $order) {
            self::showOrderCard($order);
        }

        // Pagination controls
        if ($max_pages > 1) {
            $paging_keyboard = [];
            $row = [];
            if ($page_number > 1) {
                $prev = $page_number - 1;
                $row[] = ['text' => '◀ ' . self::botText('pagination.previous', 'قبلی', 'Previous', $lang), 'callback_data' => "/TRACK{$prev}"];
            }
            $row[] = ['text' => "📄 {$page_number} / {$max_pages}", 'callback_data' => '/noop'];
            if ($page_number < $max_pages) {
                $next = $page_number + 1;
                $row[] = ['text' => self::botText('pagination.next', 'بعدی', 'Next', $lang) . ' ▶', 'callback_data' => "/TRACK{$next}"];
            }
            $paging_keyboard[] = $row;
            $paging_keyboard[] = [self::btnHome()];

            self::sendMessage([
                'text'     => sprintf(self::botText('pagination.page_of_plain', 'صفحه %1$d از %2$d', 'Page %1$d of %2$d', $lang), $page_number, $max_pages),
                'keyboard' => $paging_keyboard
            ]);
        }
    }

    /**
     * Show detailed order card with action buttons (Pay, Reorder, Downloads).
     */
    public static function showOrderCard(\WC_Order $order) {
        $lang = self::getLanguage();
        $order_id = $order->get_id();
        $order_number = $order->get_order_number();
        $status = $order->get_status();
        $status_label = wc_get_order_status_name($status);
        $total = strip_tags($order->get_formatted_order_total());
        $date = wc_format_datetime($order->get_date_created());

        $status_emojis = [
            'pending'    => '⏳',
            'processing' => '🔄',
            'on-hold'    => '⏸️',
            'completed'  => '✅',
            'cancelled'  => '❌',
            'refunded'   => '💸',
            'failed'     => '⚠️',
        ];
        $emoji = isset($status_emojis[$status]) ? $status_emojis[$status] : '📦';

        $card_text = "{$emoji} <b>" . sprintf(self::botTranslate('Order #%s', 'telenexa-commerce-for-telegram'), $order_number) . "</b>\n"
            . "📅 " . sprintf(self::botTranslate('Date: %s', 'telenexa-commerce-for-telegram'), $date) . "\n"
            . "📌 " . sprintf(self::botTranslate('Status: <b>%s</b>', 'telenexa-commerce-for-telegram'), $status_label) . "\n"
            . "💰 " . sprintf(self::botTranslate('Total: <b>%s</b>', 'telenexa-commerce-for-telegram'), $total) . "\n\n"
            . "<b>" . self::botTranslate('Ordered Items:', 'telenexa-commerce-for-telegram') . "</b>\n";

        foreach ($order->get_items() as $item) {
            $card_text .= "▫ " . esc_html($item->get_name()) . " × " . $item->get_quantity() . " (" . self::price($item->get_total()) . ")\n";
        }

        // Action Buttons
        $keyboard = [];
        $actions_row = [];

        // Direct Pay button if unpaid
        if ($order->needs_payment()) {
            $payment_url = self::generateSignedPaymentUrl($order_id, $order->get_payment_method());
            if ($payment_url) {
                $keyboard[] = [['text' => '💳 ' . self::botTranslate('Pay Now', 'telenexa-commerce-for-telegram'), 'url' => $payment_url]];
            }
        }

        // Reorder button
        $actions_row[] = ['text' => '🔄 ' . self::botTranslate('Reorder', 'telenexa-commerce-for-telegram'), 'callback_data' => "/REORDER_{$order_id}"];

        // Digital downloads if completed and downloadable
        if ($order->is_paid() && $order->has_downloadable_item()) {
            $actions_row[] = ['text' => '📥 ' . self::botTranslate('Downloads', 'telenexa-commerce-for-telegram'), 'callback_data' => "/DOWNLOADS_{$order_id}"];
        }

        if (!empty($actions_row)) {
            $keyboard[] = $actions_row;
        }

        self::sendMessage([
            'text'     => $card_text,
            'keyboard' => $keyboard,
        ]);
    }

    /**
     * Copy all items from a past order into active cart (Reorder).
     */
    public static function reorderItems($order_id) {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) return;

        // Verify ownership
        $chat_id = self::fixPersianChar((string) self::$chat_id);
        $owner_chat_id = self::fixPersianChar((string) $order->get_meta('_chat_id', true));
        if ($chat_id === '' || $owner_chat_id === '' || !hash_equals($owner_chat_id, $chat_id)) {
            self::answerCallback(self::botTranslate('Permission denied.', 'telenexa-commerce-for-telegram'));
            return;
        }

        $cart = self::getCart();
        $added_count = 0;

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_variation_id() ? ($item->get_product_id() . 'V' . $item->get_variation_id()) : $item->get_product_id();
            $qty = $item->get_quantity();

            $cart[$product_id] = (isset($cart[$product_id]) ? $cart[$product_id] : 0) + $qty;
            $added_count++;
        }

        $cart = self::normalizeCartPayload($cart);
        self::saveCart($cart);
        self::answerCallback(sprintf(self::botTranslate('Added %d item(s) to your cart.', 'telenexa-commerce-for-telegram'), $added_count));
        self::showCart();
    }

    /**
     * Display digital download links for an order.
     */
    public static function showOrderDownloads($order_id) {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) return;

        // Verify ownership
        $chat_id = self::fixPersianChar((string) self::$chat_id);
        $owner_chat_id = self::fixPersianChar((string) $order->get_meta('_chat_id', true));
        if ($chat_id === '' || $owner_chat_id === '' || !hash_equals($owner_chat_id, $chat_id)) {
            self::answerCallback(self::botTranslate('Permission denied.', 'telenexa-commerce-for-telegram'));
            return;
        }

        $downloads = $order->get_downloadable_items();
        if (empty($downloads)) {
            self::answerCallback(self::botTranslate('No downloadable files found for this order.', 'telenexa-commerce-for-telegram'));
            return;
        }

        $lang = self::getLanguage();
        $msg = "📥 <b>" . sprintf(self::botTranslate('Downloads for Order #%s', 'telenexa-commerce-for-telegram'), $order->get_order_number()) . ":</b>\n\n";

        $keyboard = [];
        foreach ($downloads as $d) {
            $file_name = !empty($d['download_name']) ? $d['download_name'] : $d['product_name'];
            $msg .= "▫ <b>" . esc_html($file_name) . "</b>\n";
            $keyboard[] = [['text' => '📥 ' . esc_html($file_name), 'url' => $d['download_url']]];
        }
        $keyboard[] = [self::btnHome()];

        self::sendMessage([
            'text'     => $msg,
            'keyboard' => $keyboard,
        ]);
    }
}
