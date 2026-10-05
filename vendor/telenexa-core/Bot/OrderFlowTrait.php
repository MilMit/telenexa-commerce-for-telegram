<?php
namespace TeleNexa\Bot;

/** OrderFlow behavior for WooBot. */
trait OrderFlowTrait {
    public static function getOrderData()  {
        $result = array();
        $result['email'] = self::getSession('email');
        $result['state'] = self::getSession('state');
        $result['city'] = self::getSession('city');
        $result['name'] = self::getSession('name');
        $result['phone'] = self::getSession('phone');
        $result['address'] = self::getSession('address');
        $result['zip'] = self::getSession('zip');
        $result['ordernote'] = self::getSession('ordernote');
        $states = self::getStates();
        if(!empty($result['state'])) {
            $cities = self::getCities($result['state']);
        }  else  {
            $cities = array();
        }
        $result['state_label'] = !empty($result['state']) && !empty($states[$result['state']])?$states[$result['state']]:'';
        $result['city_label'] = !empty($cities)&&!empty($result['city'])&&!empty($cities[$result['city']])?$cities[$result['city']]:$result['city'];
        return $result;
    }
    public static function checkLastAddress()  {
        $order_data = self::getOrderData();
        $info = "";
        $info .= !empty($order_data['email']) ? sprintf(self::botTranslate('📧 Email: %s', 'telenexa-commerce-for-telegram'), $order_data['email']) . "\n" : "";
        $info .= !empty($order_data['state_label']) ? sprintf(self::botTranslate('🏛️ State: %s', 'telenexa-commerce-for-telegram'), $order_data['state_label']) . "\n" : "";
        $info .= !empty($order_data['city_label']) ? sprintf(self::botTranslate('🏙️ City: %s', 'telenexa-commerce-for-telegram'), $order_data['city_label']) . "\n" : "";
        $info .= !empty($order_data['address']) ? sprintf(self::botTranslate('📍 Address: %s', 'telenexa-commerce-for-telegram'), $order_data['address']) . "\n" : "";
        $info .= !empty($order_data['zip']) ? sprintf(self::botTranslate('📮 Postal Code: %s', 'telenexa-commerce-for-telegram'), $order_data['zip']) . "\n" : "";
        $info .= !empty($order_data['name']) ? sprintf(self::botTranslate('👤 Full Name: %s', 'telenexa-commerce-for-telegram'), $order_data['name']) . "\n" : "";
        $info .= !empty($order_data['phone']) ? sprintf(self::botTranslate('📞 Phone: %s', 'telenexa-commerce-for-telegram'), $order_data['phone']) . "\n" : "";
        if( !empty($info) ) {
            $alert_text = self::botTranslate('Confirm details?', 'telenexa-commerce-for-telegram');
            self::answerCallback($alert_text);
            $message = "<b>" . self::botTranslate('Confirm your details:', 'telenexa-commerce-for-telegram') . "</b>\n\n" . $info . "\n" . self::botTranslate('Should your order be registered with these details?', 'telenexa-commerce-for-telegram');
            $keyboard = [
                [
                    ['text' => self::botTranslate('❌ No', 'telenexa-commerce-for-telegram'), 'callback_data' => '/PREADDRESSNO'],
                    ['text' => self::botTranslate('✅ Yes', 'telenexa-commerce-for-telegram'), 'callback_data' => '/PREADDRESSYES'],
                ],
                [
                    self::btnCancel()
                ],
            ];
            if(self::isCallback()) {
                self::editMessage( ['text'=>$message,'keyboard'=>$keyboard]);
            }  else  {
                self::sendMessage( ['text'=>$message,'keyboard'=>$keyboard]);
            }
        }  else  {
            self::stepsOrderStart();
        }
    }
    public static function requestCoupon($repeat = false)  {
        self::setLastRequest('addcoupon');
        $alert_text = self::botTranslate('Apply discount coupon', 'telenexa-commerce-for-telegram');
        self::answerCallback($alert_text);
        $message = self::botTranslate('🏷️ Please type and send your discount coupon code in the box below:', 'telenexa-commerce-for-telegram');
        $keyboard = [ [ ['text' => self::botTranslate('🔙 Back to Cart', 'telenexa-commerce-for-telegram'), 'callback_data' => '/cart'] ] ];
        if(self::isCallback()) {
            self::editMessage(['text' => $message, 'keyboard' => $keyboard]);
        }  else  {
            self::sendMessage(['text' => $message, 'keyboard' => $keyboard]);
        }
    }
    public static function setCoupon($coupon)  {
        $coupon = wc_format_coupon_code(sanitize_text_field($coupon));
        $lang = self::getLanguage();

        if (!$coupon) {
            self::requestCoupon(true);
            return false;
        }

        $coupon_object = new \WC_Coupon($coupon);
        if (!$coupon_object->get_id()) {
            $msg = self::botText('coupon.invalid', '❌ کد تخفیف وارد شده یافت نشد یا معتبر نیست.', '❌ Coupon code not found or invalid.', $lang);
            self::answerCallback($msg);
            self::sendMessage(['text' => $msg, 'keyboard' => [[['text' => self::botTranslate('Try Again', 'telenexa-commerce-for-telegram'), 'callback_data' => '/ADDCOUPON']], [self::btnHome()]]]);
            return false;
        }

        // Check if expired
        $expiry_date = $coupon_object->get_date_expires();
        if ($expiry_date && current_time('timestamp', true) > $expiry_date->getTimestamp()) {
            $msg = self::botText('coupon.expired', '❌ متأسفانه مهلت استفاده از این کد تخفیف به پایان رسیده است.', '❌ This coupon code has expired.', $lang);
            self::answerCallback($msg);
            self::sendMessage(['text' => $msg, 'keyboard' => [[self::btnHome()]]]);
            return false;
        }

        // Check minimum spend
        $cart = self::getCart();
        $subtotal = 0.0;
        foreach ($cart as $p_id => $qty) {
            $prod = wc_get_product(strpos($p_id, 'V') !== false ? explode('V', $p_id)[0] : $p_id);
            if ($prod) $subtotal += ($prod->get_price() * $qty);
        }

        $min_spend = (float) $coupon_object->get_minimum_amount();
        if ($min_spend > 0 && $subtotal < $min_spend) {
            $msg = sprintf(
                self::botText('coupon.minimum_spend', '❌ حداقل مبلغ خرید برای استفاده از این کد تخفیف %s می‌باشد.', '❌ Minimum purchase amount for this coupon is %s.', $lang),
                self::price($min_spend)
            );
            self::answerCallback($msg);
            self::sendMessage(['text' => $msg, 'keyboard' => [[self::btnHome()]]]);
            return false;
        }

        // Check usage limit
        $usage_limit = $coupon_object->get_usage_limit();
        $usage_count = $coupon_object->get_usage_count();
        if ($usage_limit > 0 && $usage_count >= $usage_limit) {
            $msg = self::botText('coupon.usage_limit', '❌ سقف مجاز استفاده از این کد تخفیف تکمیل شده است.', '❌ Usage limit for this coupon has been reached.', $lang);
            self::answerCallback($msg);
            self::sendMessage(['text' => $msg, 'keyboard' => [[self::btnHome()]]]);
            return false;
        }

        self::setSession('coupon_code', $coupon);
        self::setLastRequest('cart');
        self::sendMessage([
            'text' => sprintf(self::botTranslate('✅ Coupon "%s" applied.', 'telenexa-commerce-for-telegram'), $coupon),
            'keyboard' => [[['text' => self::botTranslate('🛒 View Cart', 'telenexa-commerce-for-telegram'), 'callback_data' => '/cart']]],
        ]);
        return true;
    }
    public static function stepsOrderStart()  {
        if(!self::existPhysicalProduct()) {
            self::log('66');
            self::$register_req_state = 0;
            self::$register_req_city = 0;
            self::$register_req_address = 0;
            self::$register_req_zip = 0;
            self::$register_req_shipping = 0;
        }
        self::step0RequestEmail();
    }
    public static function step0RequestEmail($repeat = false)  {
        if(!self::$register_req_email) {
            self::setSession('email', '');
            self::step1RequestState();
            return;
        }
        self::setLastRequest('email');
        $alert_text = self::botTranslate('Your email address?', 'telenexa-commerce-for-telegram');
        self::answerCallback($alert_text);
        $message = self::botTranslate('✉️ Please type and send your email address in the box below:', 'telenexa-commerce-for-telegram');
        if($repeat) {
            $message = self::botTranslate('❌ The email address entered is invalid.', 'telenexa-commerce-for-telegram') . "\n" . $message;
        }
        $keyboard = [ [ self::btnCancel() ] ];
        if(self::isCallback()) {
            self::editMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }  else  {
            self::sendMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }
    }
    public static function step0SetEmail($email)  {
        if(filter_var($email, FILTER_VALIDATE_EMAIL))  {
            self::setSession('email',$email);
            self::step1RequestState();
        }  else  {
            self::step0RequestEmail(true);
        }
    }
    public static function step1RequestState()  {
        if(!self::$register_req_state)  {
            self::setSession('state', '');
            self::step2RequestCity(0);
            return;
        }
        self::setLastRequest('state');
        $cols = 3;
        $states = self::getStates();
        $keyboard = array();
        $p = 0;
        $p2 = 0;
        $alert_text = self::botTranslate('Select your state?', 'telenexa-commerce-for-telegram');
        self::answerCallback($alert_text);
        if(!empty($states) && is_array($states)) {
            foreach ($states as $state_id=>$state)  {
                if(empty($keyboard[$p])) {
                    $keyboard[$p] = array();
                }
                $keyboard[$p][$p2%$cols] = ['text' => $state, 'callback_data' => (is_numeric($state_id)?"/STATE{$state_id}":"/STATE0{$state_id}")];
                if( ($p2%$cols)== $cols-1 ) {
                    $p++;
                }
                $p2++;
            }
            $keyboard[++$p][0] = self::btnCancel();
            $message = self::botTranslate('🏛️ Please select your province/state:', 'telenexa-commerce-for-telegram');
        }  else  {
            $countries = self::getCountries();
            $base_country = WC()->countries->get_base_country();
            $keyboard = [ [ self::btnCancel() ] ];
            $message = (isset($countries[$base_country]) ? sprintf(self::botTranslate('Country %s: ', 'telenexa-commerce-for-telegram'), $countries[$base_country]) : "") . self::botTranslate('No provinces/states found!', 'telenexa-commerce-for-telegram');
        }
        if(self::isCallback()) {
            self::editMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }  else  {
            self::sendMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }
    }
    public static function step1setState($state_id)  {
        $states = self::getStates();
        if(isset($states[$state_id])) {
            self::setSession('state', $state_id);
            self::step2RequestCity($state_id);
        }  else  {
            return;
        }
    }
    public static function step2RequestCity($state_id)  {
        if(!self::$register_req_city) {
            self::setSession('city', '');
            self::step3RequestName();
            return;
        }
        self::setLastRequest('city');
        $cols = 3;
        $cities = self::getCities($state_id);
        $keyboard = array();
        $alert_text = self::botTranslate('Select your city?', 'telenexa-commerce-for-telegram');
        self::answerCallback($alert_text);
        if(!empty($cities)) {
            $p = 0;
            $p2 = 0;
            foreach ($cities as $city_id=>$city)  {
                if(empty($keyboard[$p])) {
                    $keyboard[$p] = array();
                }
                $keyboard[$p][$p2%$cols] = ['text' => $city, 'callback_data' => "/CITY{$city_id}"];
                if( ($p2%$cols)== $cols-1 ) {
                    $p++;
                }
                $p2++;
            }
            $message = self::botTranslate('🏙️ Please select your city:', 'telenexa-commerce-for-telegram');
        }  else  {
            $p = -1;
            $message = self::botTranslate('🏙️ Please type and send your city name in the box below:', 'telenexa-commerce-for-telegram');
        }
        $keyboard[++$p][0] = self::btnCancel();
        if(self::isCallback()) {
            self::editMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }  else  {
            self::sendMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }
    }
    public static function step2setCity($city_id)  {
        $state_id = self::getSession('state');
        $cities = self::getCities($state_id);
        if(!empty($cities)) {
            $state = self::getSession('state');
            $cities = self::getCities($state);
            if(isset($cities[$city_id])) {
                self::setSession('city', $city_id);
                self::step3RequestName();
            }  else  {
                return;
            }
        }  else  {
            $name = sanitize_text_field($city_id);
            self::setSession('city', $city_id);
            self::step3RequestName();
        }
    }
    public static function step3RequestName()  {
        if(!self::$register_req_name) {
            self::setSession('name', '');
            self::step4RequestPhone();
            return;
        }
        $alert_text = self::botTranslate('Your full name?', 'telenexa-commerce-for-telegram');
        self::answerCallback($alert_text);
        self::setLastRequest('name');
        $message = self::botTranslate('👤 Please type and send your full name in the box below:', 'telenexa-commerce-for-telegram');
        $keyboard = [ [ self::btnCancel() ] ];
        if(self::isCallback()) {
            self::editMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }  else  {
            self::sendMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }
    }
    public static function step3setName($name)  {
        $name = sanitize_text_field($name);
        self::setSession('name',$name);
        self::step4RequestPhone();
    }
    public static function step4RequestPhone($repeat = false)  {
        if(!self::$register_req_phone) {
            self::setSession('phone', '');
            self::step5RequestAddress();
            return;
        }
        self::setLastRequest('phone');
        if(self::$register_req_phone==3) {
            $alert_text = self::botTranslate('Your mobile number?', 'telenexa-commerce-for-telegram');
            $message = self::botTranslate('📱 Please type and send your mobile number (e.g. 09101234567):', 'telenexa-commerce-for-telegram');
            if($repeat)$message = self::botTranslate('❌ The mobile number entered is invalid.', 'telenexa-commerce-for-telegram') . "\n" . $message;
        }  else if(self::$register_req_phone==2) {
            $alert_text = self::botTranslate('Your landline phone?', 'telenexa-commerce-for-telegram');
            $message = self::botTranslate('☎️ Please type and send your landline phone number (e.g. 02133445566):', 'telenexa-commerce-for-telegram');
            if($repeat)$message = self::botTranslate('❌ The phone number entered is invalid.', 'telenexa-commerce-for-telegram') . "\n" . $message;
        }  else  {
            $alert_text = self::botTranslate('Your contact number?', 'telenexa-commerce-for-telegram');
            $message = self::botTranslate('📞 Please type and send your phone number (e.g. 09101234567 or 02133445566):', 'telenexa-commerce-for-telegram');
            if($repeat)$message = self::botTranslate('❌ The contact number entered is invalid.', 'telenexa-commerce-for-telegram') . "\n" . $message;
        }
        self::answerCallback($alert_text);
        $keyboard = [ [ self::btnCancel() ] ];
        if(self::isCallback()) {
            self::editMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }  else  {
            self::sendMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }
    }
    public static function step4SetPhone($phone)  {
        $phone = sanitize_text_field($phone);
        if(self::$register_req_phone==3) $pattern = "/^09[0-9]{9}$/";
        else if(self::$register_req_phone==2) $pattern = "/^0[1-8]{1}[0-9]{9}$/";
        else $pattern = "/^0[1-9]{1}[0-9]{9}$/";
        if(preg_match($pattern, $phone))  {
            self::setSession('phone',$phone);
            self::step5RequestAddress();
        }  else  {
            self::step4RequestPhone(true);
        }
    }
    public static function step5RequestAddress()  {
        if(!self::$register_req_address) {
            self::setSession('address', '');
            self::step6RequestZip();
            return;
        }
        self::setLastRequest('address');
        $alert_text = self::botTranslate('Your address?', 'telenexa-commerce-for-telegram');
        self::answerCallback($alert_text);
        $message = self::botTranslate('📍 Please type and send your detailed address (without province/city) in the box below:', 'telenexa-commerce-for-telegram');
        $keyboard = [ [ self::btnCancel() ] ];
        if(self::isCallback()) {
            self::editMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }  else  {
            self::sendMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }
    }
    public static function step5setAddress($address)  {
        $address = sanitize_text_field($address);
        self::setSession('address',$address);
        self::step6RequestZip();
    }
    public static function step6RequestZip($repeat = false)  {
        if(!self::$register_req_zip) {
            self::setSession('zip', '');
            self::stepsOrderEnd();
            return;
        }
        self::setLastRequest('zip');
        $alert_text = self::botTranslate('Your postal code?', 'telenexa-commerce-for-telegram');
        self::answerCallback($alert_text);
        $message = self::botTranslate('📮 Please type and send your 10-digit postal code (e.g. 1234567890):', 'telenexa-commerce-for-telegram');
        if($repeat) {
            $message = self::botTranslate('❌ The postal code entered is invalid.', 'telenexa-commerce-for-telegram') . "\n" . $message;
        }
        $keyboard = [ [ self::btnCancel() ] ];
        if(self::isCallback()) {
            self::editMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }  else  {
            self::sendMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }
    }
    public static function step6SetZip($zip)  {
        $pattern = "/^[0-9]{10}$/";
        if(preg_match($pattern, $zip))  {
            self::setSession('zip',$zip);
            self::step7RequestShipping();
        }  else  {
            self::step6RequestZip(true);
        }
    }
    public static function stepsOrderEnd()  {
        self::step6_1_RequestOrderNote();
    }
    public static function step6_1_RequestOrderNote()  {
        self::setSession('ordernote','');
        if(!self::$register_req_ordernote) {
            self::step7RequestShipping();
            return;
        }
        $alert_text = self::botTranslate('Order notes?', 'telenexa-commerce-for-telegram');
        self::answerCallback($alert_text);
        self::setLastRequest('ordernote');
        $message = self::botTranslate('📝 If you have any notes or instructions for this order, please type and send them below, or click Continue:', 'telenexa-commerce-for-telegram');
        $keyboard = [ [ self::btnCancel() , ['text'=>self::botTranslate('Continue ⏭️', 'telenexa-commerce-for-telegram'),'callback_data' => '/SKIPNOTE'] ], ];
        if(self::isCallback()) {
            self::editMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }  else  {
            self::sendMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }
    }
    public static function step6_1_SetOrderNote($note)  {
        $note = sanitize_text_field($note);
        self::setSession('ordernote',$note);
        self::step7RequestShipping();
    }
    public static function step7RequestShipping()  {
        if(!self::$register_req_shipping) {
            self::setSession('shipping', '');
            self::step8RequestPayment();
            return;
        }
        self::setLastRequest('shipping');
        $alert_text = self::botTranslate('Shipping method?', 'telenexa-commerce-for-telegram');
        self::answerCallback($alert_text);
        $keyboard = array();
        $shipping = self::wooGetShipping();
        $p=0;
        if(!empty($shipping) && is_array($shipping)) {
            foreach($shipping as $method_base64_id=>$method) {
                $cost_label = $method['cost'] > 0 ? sprintf(self::botTranslate(' - Cost: %s', 'telenexa-commerce-for-telegram'), self::price($method['cost'])) : self::botTranslate(' - Free', 'telenexa-commerce-for-telegram');
                $keyboard[$p] = [ ['text'=>"🚚 {$method['label']}{$cost_label}", 'callback_data'=>"/SHIPPING{$method_base64_id}"] ];
                $p++;
            }
        }
        $keyboard[$p] = [ self::btnCancel() ];
        if(!empty($shipping)) {
            $message = self::botTranslate('🚚 Please select your preferred shipping method:', 'telenexa-commerce-for-telegram');
        }  else  {
            $message = self::botTranslate('🚚 No shipping method found for your address.', 'telenexa-commerce-for-telegram');
        }
        if(self::isCallback()) {
            self::editMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }  else  {
            self::sendMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }
    }
    public static function step7SetShipping($shipping_base64_id)  {
        $shipping = self::wooGetShipping();
        if(!empty($shipping[$shipping_base64_id])) {
            self::setSession('shipping',$shipping_base64_id);
            self::step8RequestPayment();
        }  else  {
            return;
        }
    }
    public static function step8RequestPayment()  {
        if(!self::$register_req_payment) {
            self::setSession('shipping', '');
            self::step8_1_RegisterOrderNoPayment();
            return;
        }

        // Idempotency lock to prevent duplicate orders
        if (!self::acquireOrderLock(self::$chat_id)) {
            self::answerCallback(self::botTranslate('An order is already being processed. Please wait.', 'telenexa-commerce-for-telegram'));
            return;
        }

        self::setLastRequest('payment');
        $keyboard = array();
        $message = self::botTranslate('No payment method found.', 'telenexa-commerce-for-telegram');
        $payment_list = self::wooGetPayments();
        $order_id = 0;

        if(!empty($payment_list)) {
            $result = self::wooCreateOrder();
            $url = isset($result['url'])?$result['url']:'';
            $order_id = isset($result['order_id'])?$result['order_id']:'';
            $order_db_id = isset($result['order_db_id']) ? absint($result['order_db_id']) : absint($order_id);
            $total = isset($result['total'])?$result['total']:0;
            $total = self::fixPersianChar($total);
            $total += 0 ;
            if($total===0)  {
                self::releaseOrderLock(self::$chat_id);
                self::step8_1_RegisterOrderNoPayment();
                return;
            }

            // Telegram Stars are used only for fully digital carts and only
            // when explicitly enabled by the store administrator.
            $stars_enabled = (int) woogram_option('telegram_stars_enable') === 1;
            $stars_order = ($order_db_id > 0) ? wc_get_order($order_db_id) : null;
            $stars_amount = 0;
            if ($stars_enabled && self::isDigitalCart() && $stars_order instanceof \WC_Order) {
                $stars_amount = (int) $stars_order->get_meta('_woogram_stars_amount', true);
                if ($stars_amount < 1 && class_exists('\\TeleNexa\\Stars')) {
                    $stars_amount = \TeleNexa\Stars::calculateStarsForOrder($stars_order);
                }
                if ($stars_amount < 1) {
                    $stars_rate = (float) woogram_option('telegram_stars_per_currency_unit');
                    if ($stars_rate > 0) {
                        $stars_amount = max(1, (int) round(((float) $total) * $stars_rate));
                    }
                }
            }

            if ($stars_enabled && self::isDigitalCart() && $order_db_id > 0 && $stars_amount > 0) {
                $invoice = self::sendStarsInvoiceForOrder(
                    $order_db_id,
                    self::$chat_id,
                    $stars_amount,
                    sprintf(self::botTranslate('TeleNexa order #%s', 'telenexa-commerce-for-telegram'), $order_id),
                    self::botTranslate('Digital product purchase', 'telenexa-commerce-for-telegram')
                );

                if (!is_wp_error($invoice)) {
                    self::emptyCart();
                    self::releaseOrderLock(self::$chat_id);
                    return;
                }

                self::log('Stars invoice failed: ' . $invoice->get_error_message());
            }

            if(!empty($url) && !empty($order_id)) {
                $lang = self::getLanguage();
                $shipping_note = self::$register_req_shipping ? self::botText('payment.shipping_included', ' (شامل هزینه ارسال)', ' (including shipping cost)', $lang) : "";
                $stars_note = ($stars_amount > 0) ? "\n" . sprintf(self::botText('payment.stars_amount', "مبلغ قابل پرداخت با استارز: %s ⭐", "Payable with Stars: %s ⭐", $lang), number_format($stars_amount)) : "";
                $message = "🛒\n" . sprintf(self::botText('payment.invoice_total', "مبلغ صورتحساب%s:\n%s", "Your invoice total%s is:\n%s", $lang), $shipping_note, self::price($total)) . $stars_note . "\n\n" . self::botText('payment.choose_gateway', 'لطفاً برای تکمیل سفارش یکی از درگاه‌های پرداخت زیر را انتخاب کنید 👇', 'Please click one of the payment gateways below to complete your order 👇', $lang);
                $keyboard = [ [ self::btnCancel() ], ];

                // Trigger Order Created notification
                if (method_exists(__CLASS__, 'queueOrderCreated')) {
                    self::queueOrderCreated($order_id);
                }
            }
            self::emptyCart();
        }

        self::releaseOrderLock(self::$chat_id);

        $alert_text = self::botTranslate('Payment method?', 'telenexa-commerce-for-telegram');
        self::answerCallback($alert_text);
        if(self::isCallback()) {
            self::editMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }  else  {
            self::sendMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }
        if(!empty($payment_list) && is_array($payment_list) && !empty($order_id)) {
            $p=1;
            foreach($payment_list as $gateway_id=>$gateway) {
                // Generate cryptographically signed payment URL with expiration
                $signed_pay_url = self::generateSignedPaymentUrl($order_id, $gateway['id']);
                if (empty($signed_pay_url)) {
                    $signed_pay_url = "{$url}{$gateway['id']}";
                }
                $keyboard = [ [ ['text'=>"💳 " . $gateway['title'], 'url'=>$signed_pay_url], ] ];
                self::sendMessage( ['text'=>"{$p}) {$gateway['title']}\n{$gateway['description']}",'keyboard'=>$keyboard]);
                $p++;
            }
        }
    }
    public static function step8_1_RegisterOrderNoPayment()  {
        self::setLastRequest('no_payment');
        $result = self::wooCreateOrder();
        $order_id = isset($result['order_id'])?$result['order_id']:'';
        $order = wc_get_order( $order_id );
        $order->payment_complete();
        self::emptyCart();
        self::step9OrderChange($order_id, 'processing');
    }
    public static function step9OrderChange($order_id, $status)  {
        $order = wc_get_order( $order_id );
        if(!empty($order)) {
            $keyboard = [ [ self::btnHome(), ], ];
            switch($status) {
                case 'received':
                    $message = sprintf(self::botTranslate('✅ Thank you, your order #%s has been received.', 'telenexa-commerce-for-telegram'), $order_id) . "\n";
                    break;
                case 'processing':
                    $message = sprintf(self::botTranslate('🎉 Order #%s was successfully placed.', 'telenexa-commerce-for-telegram'), $order_id) . "\n";
                    break;
                case 'completed':
                    $message = sprintf(self::botTranslate('✅ Order #%s has been completed.', 'telenexa-commerce-for-telegram'), $order_id) . "\n";
                    break;
                case 'refunded':
                    $message = sprintf(self::botTranslate('⚠️ Order #%s has been refunded.', 'telenexa-commerce-for-telegram'), $order_id) . "\n";
                    break;
                case 'cancelled':
                    $message = sprintf(self::botTranslate('🛑 Order #%s has been cancelled.', 'telenexa-commerce-for-telegram'), $order_id) . "\n";
                    break;
            }
            if(!empty($message))  {
                $alert_text = $message;
                self::answerCallback($alert_text);
            }
            // OrdersTrait exposes the canonical order renderer. The old
            // showOrder() call no longer exists after the order UI refactor.
            self::showOrderCard($order);
        }
    }
    public static function getDownloadLinks($order)  {
        $downloads_list = [];
        if ( function_exists('WC') && isset(WC()->version) && version_compare( WC()->version, '3.0.0', '>=' ) )  {
            foreach ( $order->get_items() as $item_id => $item )  {
                if ( is_object( $item ) && $item->is_type( 'line_item' ) && ( $downloads = $item->get_item_downloads() ) )  {
                    foreach ($downloads as $file )  {
                        $downloads_list[] = ['file_name' => $item->get_name()." - ".esc_html( $file['name'] ), 'download_url'=>esc_html( $file['download_url'] )];
                    }
                }
            }
        }  else  {
            $prefix = '';
            $order_get_items = $order->get_items();
            if(!empty($order_get_items) && is_array($order_get_items)) {
                foreach( $order->get_items() as $item_id => $item )  {
                    if ( apply_filters( 'woocommerce_order_item_visible', true, $item ) )  {
                        $product = $order->get_product_from_item( $item );
                        if ( $product && $product->exists() && $product->is_downloadable() && $order->is_download_permitted() )  {
                            $download_files = $order->get_item_downloads( $item );
                            $i = 0;
                            $links = array();
                            foreach ( $download_files as $download_id => $file )  {
                                $i++;
                                $links[] = '<small class="download-url">' . $prefix . ': <a href="' . esc_url( $file['download_url'] ) . '" target="_blank">' . esc_html( $file['name'] ) . '</a></small>' . "\n";
                                $downloads_list[] = ['file_name' => "{$i}) " . esc_html( $file['name'] ) , 'download_url'=>esc_html( $file['download_url'] )];
                            }
                        }
                    }
                }
            }
        }
        return $downloads_list;
    }
    public static function showDownloadLink($order)  {
        $downloads_list = self::getDownloadLinks($order);
        if(!empty($downloads_list)) {
            $keyboard = [ [ self::btnHome() ], ];
            $message = sprintf(self::botTranslate('Download links for order #%s:', 'telenexa-commerce-for-telegram'), $order->get_order_number()) . "\n\n";
            if(!empty($downloads_list) && is_array($downloads_list)) {
                foreach($downloads_list as $down) {
                    $message .= "✅ {$down['file_name']}\n🔗 {$down['download_url']}\n\n";
                }
            }
            self::sendMessage( ['text'=>$message,'keyboard'=>$keyboard]);
        }
    }
}
