<?php

namespace TeleNexa\Bot;

use TeleNexa\Http\TelegramApiAdapter;

/**
 * Telegram payment lifecycle helpers.
 *
 * This trait validates Stars callbacks and keeps payment completion
 * idempotent. Invoice creation is intentionally feature-flagged by callers.
 */
trait PaymentsTrait {

    /** Return true only when every cart item is digital and needs no shipping. */
    public static function isDigitalCart(array $cart = []) {
        if (class_exists('\\TeleNexa\\Stars')) {
            return \TeleNexa\Stars::isDigitalCart($cart);
        }

        if (empty($cart)) {
            $cart = self::getCart();
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
            $product = wc_get_product($product_id);
            if (!$product || !$product->is_purchasable() || $product->needs_shipping()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Create a Telegram Stars invoice for an existing digital WooCommerce
     * order. The caller must calculate and validate the Stars amount.
     */
    public static function sendStarsInvoiceForOrder($order_id, $chat_id, $stars_amount = 0, $title = '', $description = '') {
        $order = wc_get_order(absint($order_id));
        $stars_amount = absint($stars_amount);
        if ($stars_amount < 1 && $order instanceof \WC_Order) {
            $stars_amount = (int) $order->get_meta('_woogram_stars_amount', true);
            if ($stars_amount < 1 && class_exists('\\TeleNexa\\Stars')) {
                $stars_amount = \TeleNexa\Stars::calculateStarsForOrder($order);
            }
        }
        $digital_only = class_exists('\\TeleNexa\\Stars') ? \TeleNexa\Stars::isDigitalOrder($order) : ($order instanceof \WC_Order && $order->get_item_count() > 0);
        if ($digital_only && !class_exists('\\TeleNexa\\Stars')) {
            foreach ($order->get_items() as $item) {
                $item_product = $item->get_product();
                if ($item_product && $item_product->needs_shipping()) {
                    $digital_only = false;
                    break;
                }
            }
        }
        if (!$order instanceof \WC_Order || !$digital_only || $stars_amount < 1) {
            return new \WP_Error('woogram_invalid_stars_order', self::botTranslate('Only valid digital orders can use Telegram Stars.', 'telenexa-commerce-for-telegram'));
        }

        if (empty($title) && $order instanceof \WC_Order) {
            $title = sprintf(self::botTranslate('TeleNexa order #%s', 'telenexa-commerce-for-telegram'), $order->get_order_number());
        }
        if (empty($description)) {
            $description = self::botTranslate('Digital product purchase', 'telenexa-commerce-for-telegram');
        }

        $payload_hash = substr(hash_hmac('sha256', $order->get_id() . '|' . $stars_amount, wp_salt('auth')), 0, 32);
        $payload = 'woogram_stars_' . $order->get_id() . '_' . $payload_hash;
        $order->update_meta_data('_woogram_stars_amount', $stars_amount);
        $order->update_meta_data('_woogram_stars_payload_hash', $payload_hash);
        $order->update_meta_data('_woogram_stars_user_id', (string) $chat_id);
        $order->save();

        $result = TelegramApiAdapter::sendInvoice([
            'chat_id'                  => (string) $chat_id,
            'title'                    => sanitize_text_field($title),
            'description'              => sanitize_text_field($description),
            'payload'                  => $payload,
            'provider_token'           => '',
            'currency'                 => 'XTR',
            'prices'                   => [[
                'label'  => sanitize_text_field($title),
                'amount' => $stars_amount,
            ]],
            'need_name'                => false,
            'need_phone_number'        => false,
            'need_email'               => false,
            'need_shipping_address'    => false,
        ]);

        if (is_wp_error($result)) {
            $order->delete_meta_data('_woogram_stars_amount');
            $order->delete_meta_data('_woogram_stars_payload_hash');
            $order->delete_meta_data('_woogram_stars_user_id');
            $order->save();
        }
        return $result;
    }

    public static function handlePreCheckoutQuery($query) {
        if (!$query) {
            return false;
        }

        $payload = (string) $query->getInvoicePayload();
        $order = self::getStarsOrderFromPayload($payload);
        $error = '';

        if (!$order) {
            $error = self::botTranslate('This payment session is no longer available.', 'telenexa-commerce-for-telegram');
        } elseif ($order->is_paid()) {
            $error = self::botTranslate('This order has already been paid.', 'telenexa-commerce-for-telegram');
        } elseif ((string) $query->getCurrency() !== 'XTR') {
            $error = self::botTranslate('This order requires a Telegram Stars payment.', 'telenexa-commerce-for-telegram');
        } else {
            $expected_user_id = (string) $order->get_meta('_woogram_stars_user_id', true);
            $query_user = $query->getFrom();
            $query_user_id = $query_user ? (string) $query_user->getId() : '';
            if ($expected_user_id !== '' && $query_user_id !== $expected_user_id) {
                $error = self::botTranslate('This payment does not belong to the current Telegram user.', 'telenexa-commerce-for-telegram');
            }
            $expected_amount = (int) $order->get_meta('_woogram_stars_amount', true);
            if ($error === '' && $expected_amount > 0 && (int) $query->getTotalAmount() !== $expected_amount) {
                $error = self::botTranslate('The payment amount is no longer valid.', 'telenexa-commerce-for-telegram');
            }
        }

        $params = [
            'pre_checkout_query_id' => $query->getId(),
            'ok'                    => $error === '' ? 'true' : 'false',
        ];
        if ($error !== '') {
            $params['error_message'] = $error;
        }

        $result = TelegramApiAdapter::answerPreCheckoutQuery($params);
        if (is_wp_error($result)) {
            self::log('Stars pre-checkout error: ' . $result->get_error_message());
            return false;
        }
        return $error === '';
    }

    public static function handleSuccessfulPayment($message) {
        if (!$message) {
            return false;
        }

        $payment = $message->getSuccessfulPayment();
        if (!$payment) {
            return false;
        }

        $payload = (string) $payment->getInvoicePayload();
        $order = self::getStarsOrderFromPayload($payload);
        if (!$order) {
            self::log('Stars payment rejected: unknown invoice payload.');
            return false;
        }

        $charge_id = (string) $payment->getTelegramPaymentChargeId();
        $expected_user_id = (string) $order->get_meta('_woogram_stars_user_id', true);
        $payment_user_id = (string) $message->getChat()->getId();
        if ($expected_user_id !== '' && $payment_user_id !== $expected_user_id) {
            self::log('Stars payment rejected: user mismatch for order #' . $order->get_id());
            return false;
        }
        $saved_charge_id = (string) $order->get_meta('_woogram_telegram_payment_charge_id', true);
        if ($saved_charge_id !== '') {
            return hash_equals($saved_charge_id, $charge_id);
        }
        if ($order->is_paid()) {
            self::log('Stars payment rejected: order #' . $order->get_id() . ' is already paid.');
            return false;
        }

        $expected_amount = (int) $order->get_meta('_woogram_stars_amount', true);
        if ($expected_amount > 0 && (int) $payment->getTotalAmount() !== $expected_amount) {
            self::log('Stars payment rejected: amount mismatch for order #' . $order->get_id());
            return false;
        }

        $order->update_meta_data('_woogram_telegram_payment_charge_id', sanitize_text_field($charge_id));
        $order->update_meta_data('_woogram_telegram_payment_provider', 'telegram_stars');
        $order->set_transaction_id($charge_id);
        $order->payment_complete($charge_id);
        $order->save();

        $chat_id = self::fixPersianChar((string) $message->getChat()->getId());
        if ($chat_id !== '') {
            self::setChatId($chat_id);
            self::sendMessage([
                'chat_id' => $chat_id,
                'text'    => sprintf(self::botTranslate('✅ Payment received for order #%s.', 'telenexa-commerce-for-telegram'), $order->get_order_number()),
                'keyboard' => [[self::btnHome()]],
            ]);
        }
        return true;
    }

    protected static function getStarsOrderFromPayload($payload) {
        if (!preg_match('/^woogram_stars_(\d+)_([a-f0-9]{16,64})$/i', $payload, $matches)) {
            return null;
        }

        $order = wc_get_order((int) $matches[1]);
        if (!$order instanceof \WC_Order) {
            return null;
        }

        $expected_hash = (string) $order->get_meta('_woogram_stars_payload_hash', true);
        if ($expected_hash === '' || !hash_equals($expected_hash, strtolower($matches[2]))) {
            return null;
        }
        return $order;
    }
}
