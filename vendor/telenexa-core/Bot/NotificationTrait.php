<?php
namespace TeleNexa\Bot;


/**
 * Automated Notification Engine for WooBot.
 * Handles new orders, status changes, successful payments, price drops, back-in-stock,
 * new product releases, and abandoned cart reminders via Action Scheduler or WP-Cron.
 */
trait NotificationTrait {

    /**
     * Dispatch notification asynchronously via Action Scheduler or fallback.
     */
    public static function queueNotification($hook, array $args = []) {
        if (function_exists('as_enqueue_async_action')) {
            if (function_exists('as_has_scheduled_action') && as_has_scheduled_action($hook, $args, 'telenexa-commerce-for-telegram')) {
                return true;
            }
            as_enqueue_async_action($hook, $args, 'telenexa-commerce-for-telegram');
            return true;
        }
        // Fallback to WP single event
        wp_schedule_single_event(time() + 5, $hook, $args);
        return true;
    }

    /** Queue WooCommerce notifications so checkout requests never wait for Telegram. */
    public static function queueOrderCreated($order_id) {
        return self::queueNotification('woogram_notify_order_created', [(int) $order_id]);
    }

    public static function queueOrderStatusChanged($order_id, $from_status, $to_status) {
        return self::queueNotification('woogram_notify_order_status_changed', [
            (int) $order_id,
            sanitize_key($from_status),
            sanitize_key($to_status),
        ]);
    }

    public static function queuePaymentCompleted($order_id) {
        return self::queueNotification('woogram_notify_payment_completed', [(int) $order_id]);
    }

    public static function queueBackInStock($product_id) {
        return self::queueNotification('woogram_notify_back_in_stock', [(int) $product_id]);
    }

    /**
     * Notify customer and admin of a new order.
     */
    public static function onOrderCreated($order_id) {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) return;

        // WooCommerce may fire this event more than once (and custom checkout
        // flows can also dispatch it). Keep Telegram from receiving duplicates.
        if ($order->get_meta('_woogram_order_created_notified', true)) return;
        $lock_key = 'woogram_order_created_' . absint($order_id);
        if (get_transient($lock_key)) return;
        set_transient($lock_key, 1, 5 * MINUTE_IN_SECONDS);

        $chat_id = self::fixPersianChar($order->get_meta('_chat_id', true));
        $order_number = $order->get_order_number();
        $total = $order->get_formatted_order_total();
        if (!empty($chat_id)) {
            self::$chat_id = $chat_id;
            self::initSession();
        }
        $lang = self::getLanguage();

        // Customer Notification
        if (!empty($chat_id) && woogram_option('notify_customer_new_order') !== '0') {
            $msg = "🎉 <b>" . self::botText('order.registered', 'سفارش با موفقیت ثبت شد!', 'Order Registered Successfully!', $lang) . "</b>\n\n"
                . sprintf(self::botText('order.number', 'شماره سفارش: <b>#%s</b>', 'Order Number: <b>#%s</b>', $lang), $order_number) . "\n"
                . sprintf(self::botText('order.total', 'مبلغ کل: <b>%s</b>', 'Order Total: <b>%s</b>', $lang), strip_tags($total)) . "\n"
                . sprintf(self::botText('order.status', 'وضعیت: <b>%s</b>', 'Status: <b>%s</b>', $lang), self::botOrderStatusText($order->get_status(), $lang)) . "\n\n"
                . self::botText('order.thanks_tracking', 'از خرید شما سپاسگزاریم! از طریق دکمه زیر می‌توانید وضعیت سفارش خود را پیگیری کنید.', 'Thank you for shopping with us! You can track your order status anytime using the button below.', $lang);

            $keyboard = [
                [['text' => '📦 ' . self::botText('button.track_order', 'پیگیری سفارش', 'Track Order', $lang), 'callback_data' => '/TRACK1']],
                [self::btnHome()]
            ];

            self::sendMessage([
                'chat_id'  => $chat_id,
                'text'     => $msg,
                'keyboard' => $keyboard,
            ]);
        }

        // Admin Notification
        $admin_chat_id = woogram_option('admin_notification_chat_id');
        if (!empty($admin_chat_id) && woogram_option('notify_admin_new_order') !== '0') {
            $admin_msg = "🔔 <b>" . self::botTranslate('New Order in Store!', 'telenexa-commerce-for-telegram') . "</b>\n\n"
                . sprintf(self::botTranslate('Order: <b>#%s</b>', 'telenexa-commerce-for-telegram'), $order_number) . "\n"
                . sprintf(self::botTranslate('Customer: <b>%s</b>', 'telenexa-commerce-for-telegram'), esc_html($order->get_formatted_billing_full_name())) . "\n"
                . sprintf(self::botTranslate('Amount: <b>%s</b>', 'telenexa-commerce-for-telegram'), strip_tags($total)) . "\n"
                . sprintf(self::botTranslate('Payment Method: <b>%s</b>', 'telenexa-commerce-for-telegram'), esc_html($order->get_payment_method_title())) . "\n"
                . sprintf(self::botTranslate('Channel: <b>%s</b>', 'telenexa-commerce-for-telegram'), $order->get_meta('_order_from', true) === 'telegram' ? 'Telegram Bot' : 'Website');

            self::sendMessage([
                'chat_id' => $admin_chat_id,
                'text'    => $admin_msg,
            ]);
        }

        $order->update_meta_data('_woogram_order_created_notified', gmdate('c'));
        $order->save();
    }

    /**
     * Notify customer on order status change.
     */
    public static function onOrderStatusChanged($order_id, $from_status, $to_status) {
        if (woogram_option('notify_status_change') === '0') return;

        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) return;

        $chat_id = self::fixPersianChar($order->get_meta('_chat_id', true));
        if (empty($chat_id)) return;
        self::$chat_id = $chat_id;
        self::initSession();
        $lang = self::getLanguage();

        $status_label = self::botOrderStatusText($to_status, $lang);

        $status_emojis = [
            'pending'    => '⏳',
            'processing' => '🔄',
            'on-hold'    => '⏸️',
            'completed'  => '✅',
            'cancelled'  => '❌',
            'refunded'   => '💸',
            'failed'     => '⚠️',
        ];
        $emoji = isset($status_emojis[$to_status]) ? $status_emojis[$to_status] : '📦';

        $msg = "{$emoji} <b>" . self::botText('order.status_update', 'بروزرسانی وضعیت سفارش', 'Order Status Update', $lang) . "</b>\n\n"
            . sprintf(self::botText('order.label', 'سفارش: <b>#%s</b>', 'Order: <b>#%s</b>', $lang), $order->get_order_number()) . "\n"
            . sprintf(self::botText('order.new_status', 'وضعیت جدید: <b>%s</b>', 'New Status: <b>%s</b>', $lang), $status_label) . "\n\n";

        if ($to_status === 'completed') {
            $msg .= self::botText('order.completed_message', 'سفارش شما تکمیل و ارسال شد. از خرید شما سپاسگزاریم!', 'Your order has been completed and dispatched. Thank you for your purchase!', $lang);
            if (woogram_option('enable_order_rating') !== '0') {
                $msg .= "\n\n⭐️ <b>" . self::botText('order.rate_prompt', 'تجربه خرید شما چطور بود؟ لطفاً به این سفارش امتیاز دهید:', 'How was your shopping experience? Please rate this order:', $lang) . "</b>";
                $keyboard = [
                    [
                        ['text' => '⭐⭐⭐⭐⭐', 'callback_data' => "/RATE_{$order_id}_5"],
                        ['text' => '⭐⭐⭐⭐', 'callback_data' => "/RATE_{$order_id}_4"],
                        ['text' => '⭐⭐⭐', 'callback_data' => "/RATE_{$order_id}_3"],
                    ],
                    [
                        ['text' => '⭐⭐', 'callback_data' => "/RATE_{$order_id}_2"],
                        ['text' => '⭐', 'callback_data' => "/RATE_{$order_id}_1"],
                    ],
                    [['text' => '📦 ' . self::botText('button.view_order', 'مشاهده جزئیات سفارش', 'View Order Details', $lang), 'callback_data' => '/TRACK1']],
                    [self::btnHome()]
                ];
            } else {
                $keyboard = [
                    [['text' => '📦 ' . self::botText('button.view_order', 'مشاهده جزئیات سفارش', 'View Order Details', $lang), 'callback_data' => '/TRACK1']],
                    [self::btnHome()]
                ];
            }
        } elseif ($to_status === 'processing') {
            $msg .= self::botText('order.processing_message', 'پرداخت تأیید شد! سفارش شما در حال آماده‌سازی است.', 'Payment confirmed! Your order is currently being prepared.', $lang);
            $keyboard = [
                [['text' => '📦 ' . self::botText('button.view_order', 'مشاهده جزئیات سفارش', 'View Order Details', $lang), 'callback_data' => '/TRACK1']],
                [self::btnHome()]
            ];
        } elseif ($to_status === 'cancelled') {
            $msg .= self::botText('order.cancelled_message', 'سفارش شما لغو شد. در صورت نیاز با پشتیبانی تماس بگیرید.', 'Your order has been cancelled. Please contact support if you need assistance.', $lang);
            $keyboard = [
                [['text' => '📦 ' . self::botText('button.view_order', 'مشاهده جزئیات سفارش', 'View Order Details', $lang), 'callback_data' => '/TRACK1']],
                [self::btnHome()]
            ];
        } else {
            $keyboard = [
                [['text' => '📦 ' . self::botText('button.view_order', 'مشاهده جزئیات سفارش', 'View Order Details', $lang), 'callback_data' => '/TRACK1']],
                [self::btnHome()]
            ];
        }

        self::sendMessage([
            'chat_id'  => $chat_id,
            'text'     => $msg,
            'keyboard' => $keyboard,
        ]);
    }

    /**
     * Notify customer on successful payment completion.
     */
    public static function onPaymentCompleted($order_id) {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) return;

        if ($order->get_meta('_woogram_payment_notified', true)) return;
        $lock_key = 'woogram_payment_' . absint($order_id);
        if (get_transient($lock_key)) return;
        set_transient($lock_key, 1, 5 * MINUTE_IN_SECONDS);

        $chat_id = self::fixPersianChar($order->get_meta('_chat_id', true));
        if (empty($chat_id)) return;
        self::$chat_id = $chat_id;
        self::initSession();
        $lang = self::getLanguage();

        $msg = "💳 <b>" . self::botText('payment.success', 'پرداخت موفق بود!', 'Payment Successful!', $lang) . "</b>\n\n"
            . sprintf(self::botText('order.number', 'شماره سفارش: <b>#%s</b>', 'Order Number: <b>#%s</b>', $lang), $order->get_order_number()) . "\n"
            . sprintf(self::botText('payment.amount_paid', 'مبلغ پرداخت‌شده: <b>%s</b>', 'Amount Paid: <b>%s</b>', $lang), strip_tags($order->get_formatted_order_total())) . "\n"
            . sprintf(self::botText('payment.transaction_id', 'شناسه تراکنش: <code>%s</code>', 'Transaction ID: <code>%s</code>', $lang), $order->get_transaction_id() ?: self::botText('payment.approved', 'تأیید شد', 'Approved', $lang)) . "\n\n"
            . self::botText('payment.processing_message', 'پرداخت شما دریافت شد و سفارش در حال پردازش است.', 'We have received your payment and are processing your order.', $lang);

        $keyboard = [
            [['text' => '📦 ' . self::botText('button.track_order', 'پیگیری سفارش', 'Track Order', $lang), 'callback_data' => '/TRACK1']],
            [self::btnHome()]
        ];

        self::sendMessage([
            'chat_id'  => $chat_id,
            'text'     => $msg,
            'keyboard' => $keyboard,
        ]);

        $order->update_meta_data('_woogram_payment_notified', gmdate('c'));
        $order->save();
    }

    /**
     * Subscribe user to back-in-stock alerts for a product.
     */
    public static function subscribeToStockAlert($product_id) {
        $product_id = absint($product_id);
        $chat_id = self::fixPersianChar((string) self::$chat_id);
        if (!$product_id || empty($chat_id)) return false;

        $subscribers = get_post_meta($product_id, '_woogram_stock_subscribers', true);
        if (!is_array($subscribers)) $subscribers = [];

        if (!in_array($chat_id, $subscribers, true)) {
            $subscribers[] = $chat_id;
            update_post_meta($product_id, '_woogram_stock_subscribers', $subscribers);
        }

        $lang = self::getLanguage();
        $msg = "🔔 " . self::botText(
            'notification.stock_alert_subscribed',
            'به محض موجود شدن این محصول در انبار، در همین جا به شما پیام داده خواهد شد!',
            'You will be notified via Telegram as soon as this product is back in stock!',
            $lang
        );

        self::answerCallback($msg);
        self::sendMessage(['text' => $msg, 'keyboard' => [[self::btnHome()]]]);
        return true;
    }

    /**
     * Trigger back-in-stock notifications when product status changes to instock.
     */
    public static function notifyBackInStock($product_id) {
        $product_id = absint($product_id);
        $subscribers = get_post_meta($product_id, '_woogram_stock_subscribers', true);
        if (empty($subscribers) || !is_array($subscribers)) return;

        $product = wc_get_product($product_id);
        if (!$product || !$product->is_in_stock()) return;

        $title = $product->get_name();
        $price = strip_tags($product->get_price_html());

        $text = "🎉 <b>" . self::botTranslate('Product Back in Stock!', 'telenexa-commerce-for-telegram') . "</b>\n\n"
            . sprintf(self::botTranslate('Product: <b>%s</b>', 'telenexa-commerce-for-telegram'), esc_html($title)) . "\n"
            . sprintf(self::botTranslate('Price: <b>%s</b>', 'telenexa-commerce-for-telegram'), esc_html($price)) . "\n\n"
            . self::botTranslate('The item you requested is now available. Order before it sells out!', 'telenexa-commerce-for-telegram');

        $keyboard = [
            [['text' => '🛒 ' . self::botTranslate('Order Now', 'telenexa-commerce-for-telegram'), 'callback_data' => "/ADD2CART{$product_id}"]],
            [['text' => '🔎 ' . self::botTranslate('View Product', 'telenexa-commerce-for-telegram'), 'callback_data' => "/P{$product_id}"]],
            [self::btnHome()]
        ];

        foreach ($subscribers as $chat_id) {
            self::sendMessage([
                'chat_id'  => $chat_id,
                'text'     => $text,
                'keyboard' => $keyboard,
            ]);
            usleep(30000); // 30ms throttle
        }

        // Clear subscribers list after sending
        delete_post_meta($product_id, '_woogram_stock_subscribers');
    }

    /**
     * Schedule an abandoned cart reminder for a user.
     */
    public static function scheduleAbandonedCartReminder($chat_id) {
        if (woogram_option('feature_abandoned_cart') === '0') return;

        $chat_id = self::fixPersianChar((string) $chat_id);
        if (empty($chat_id)) return;

        $delay_hours = max(1, absint(woogram_option('abandoned_cart_delay_hours') ?: 3));
        $timestamp = time() + ($delay_hours * 3600);

        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action($timestamp, 'woogram_check_abandoned_cart', [$chat_id], 'telenexa-commerce-for-telegram');
        } elseif (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('woogram_check_abandoned_cart', [$chat_id], 'telenexa-commerce-for-telegram');
        } else {
            wp_schedule_single_event($timestamp, 'woogram_check_abandoned_cart', [$chat_id]);
        }
    }

    /**
     * Execute abandoned cart reminder if cart is still not empty and no order placed.
     */
    public static function sendAbandonedCartReminder($chat_id) {
        $chat_id = self::fixPersianChar((string) $chat_id);
        if (empty($chat_id)) return;

        self::$chat_id = $chat_id;
        self::initSession();

        $cart = self::getSession('cart');
        if (empty($cart) || !is_array($cart)) return; // Cart was completed or emptied

        $lang = self::getLanguage();
        $msg = "🛒 <b>" . self::botText('notification.abandoned_cart.title', 'آیا سبد خرید خود را فراموش کرده‌اید؟', 'Did you forget something in your cart?', $lang) . "</b>\n\n"
            . self::botText(
                'notification.abandoned_cart.body',
                'شما هنوز محصولاتی در سبد خرید خود دارید. جهت تکمیل سفارش و جلوگیری از اتمام موجودی، روی دکمه زیر کلیک کنید:',
                'You still have items waiting in your shopping cart. Complete your purchase now before items run out of stock!',
                $lang
            );

        $keyboard = [
            [['text' => '🛒 ' . self::botText('notification.abandoned_cart.action', 'تکمیل و پرداخت سبد خرید', 'Complete Checkout', $lang), 'callback_data' => '/cart']],
            [self::btnHome()]
        ];

        self::sendMessage([
            'chat_id'  => $chat_id,
            'text'     => $msg,
            'keyboard' => $keyboard,
        ]);
    }
}
