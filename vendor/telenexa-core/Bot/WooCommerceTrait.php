<?php
namespace TeleNexa\Bot;

/** WooCommerce behavior for WooBot. */
trait WooCommerceTrait {
    public static function getCountries()  {
        return WC()->countries->get_allowed_countries();
    }
    public static function getStates()  {
        $default_country = WC()->countries->get_base_country();
        $default_country = !empty($default_country)?$default_country:'IR';
        $states = WC()->countries->get_states( $default_country );
        return $states;
    }
    public static function getCities($state_id)  {
        $state_id = absint($state_id);
        if(!$state_id) {
            return;
        }
        $cities = get_terms(array('taxonomy' => 'state_city', 'hide_empty' => false, 'child_of' => $state_id));
        if(is_wp_error($cities)) {
            return;
        }
        $cities = wp_list_pluck($cities, 'name', 'term_id');
        return $cities;
    }
    public static function wooAdd2Cart($product_id, $quantity)  {
        $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
        $product_status = get_post_status( $product_id );
        if ( $passed_validation && WC()->cart->add_to_cart( $product_id, $quantity ) && 'publish' === $product_status )  {
            return true;
        }  else  {
            return false;
        }
    }
    public static function wooGetShipping()  {
        $order_data = self::getOrderData();
        $cart = self::normalizeCartPayload(self::getCart());
        if(empty($order_data) OR empty($cart)) {
            return array();
        }
        $added_items = 0;
        if(!empty($cart) && is_array($cart)) {
            foreach($cart as $product_id=>$qty) {
                $parent_id = $product_id;
                $variation_id = 0;
                if(strpos($product_id,'V')!==false) {
                    $ids = explode('V', $product_id);
                    $parent_id = absint($ids[0]);
                    $variation_id = absint($ids[1]);
                }
                $variation_attributes = [];
                if ($variation_id && function_exists('wc_get_product')) {
                    $variation = wc_get_product($variation_id);
                    if ($variation && method_exists($variation, 'get_variation_attributes')) {
                        $variation_attributes = $variation->get_variation_attributes();
                    }
                }
                if ($variation_id && WC()->cart) {
                    WC()->cart->add_to_cart($parent_id, $qty, $variation_id, $variation_attributes);
                } else {
                    self::wooAdd2Cart($parent_id, $qty);
                }
            }
        }
        $default_country = WC()->countries->get_base_country();
        WC()->customer->set_shipping_country( $default_country );
        WC()->customer->calculated_shipping( true );
        WC()->customer->set_shipping_state( $order_data['state'] );
        WC()->customer->set_shipping_city( $order_data['city'] );
        WC()->customer->set_shipping_postcode( $order_data['zip'] );
        WC()->cart->calculate_shipping();
        $res = array();
        if(WC()->cart->needs_shipping() && WC()->cart->show_shipping()) {
            $packages = WC()->shipping->get_packages();
            foreach ( $packages as $i => $package )  {
                $res = $package['rates'];
                break;
            }
        }
        WC()->cart->empty_cart();
        $methods = array();
        if(!empty($res) && is_array($res)) {
            foreach($res as $method) {
                $base = base64_encode($method->id);
                $methods[$base] = ['id'=>$method->id, 'label'=>$method->label, 'cost'=>$method->cost,'rate'=>$method];
            }
        }
        return $methods;
    }
    public static function wooGetPayments()  {
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $gateway = [];
        $gateway_list = [];
        if(!empty($available_gateways) && is_array($available_gateways)) {
            foreach($available_gateways as $gateway_id => $gateway) {
                if($gateway->enabled==='yes') {
                    $gateway_list[$gateway->id] = ['id'=>$gateway->id,'title'=>$gateway->title,'description'=>$gateway->description,'object'=>$gateway];
                }
            }
        }
        return $gateway_list;
    }
    public static function wooCreateOrder()  {
        $order_data = self::getOrderData();
        $cart = self::getCart();
        $cart_variation = self::getCartVariation();
        $shipping_list = self::wooGetShipping();
        $shipping = self::getSession('shipping');
        if(empty($cart)) {
            return false;
        }
        $address = [ 'first_name' => '', 'last_name' => $order_data['name'], 'company' => '', 'email' => $order_data['email'], 'phone' => $order_data['phone'], 'address_1' => $order_data['address'], 'address_2' => '', 'city' => $order_data['city'], 'state' => $order_data['state'], 'postcode' => $order_data['zip'], 'country' => (new \WC_Countries)->get_base_country(), ];
        $order_args = array( 'customer_note' => trim((string) ($order_data['ordernote'] ?? '')), );
        $order = wc_create_order($order_args);
        if (is_wp_error($order)) {
            self::log('Order creation failed: ' . $order->get_error_message());
            return false;
        }
        do_action( 'woocommerce_checkout_update_order_meta', $order->get_id(), $order_args );
        if(!empty($cart) && is_array($cart)) {
            foreach($cart as $product_id=>$qty) {
                $variation_id = 0;
                if(strpos($product_id,'V')!==false) {
                    $ids = explode('V', $product_id);
                    $variation_id = $ids[1];
                    $p_id = $ids[0];
                }  else  {
                    $p_id = $product_id;
                }
                $product = self::apiGetProduct($product_id);
                $args = [];
                if(isset($cart_variation[$product_id])) {
                    $args['name'] = $product['name'] .= " ".implode(', ',array_filter($cart_variation[$product_id],function($v) {
                        return !empty($v)?true:false;
                    }
                    )) ;
                }
                if(function_exists('wc_get_product')) {
                    $add_product = wc_get_product( $variation_id?$variation_id:$p_id );
                }  else  {
                    $add_product = get_product( $variation_id?$variation_id:$p_id );
                }
                if (!$add_product || (method_exists($add_product, 'is_purchasable') && !$add_product->is_purchasable())) {
                    self::log('Skipped unavailable product in order: ' . $product_id);
                    continue;
                }
                $order->add_product( $add_product, $qty, $args );
                $added_items++;
            }
        }
        if ($added_items < 1) {
            $order->delete(true);
            self::log('Order creation stopped because no cart items were valid.');
            return false;
        }
        $order->set_address( $address, 'billing' );
        $order->set_address( $address, 'shipping' );
        if (self::$register_req_shipping && isset($shipping_list[$shipping]['rate'])) {
            $rate = $shipping_list[$shipping]['rate'];
            $shipping_item = new \WC_Order_Item_Shipping();
            $shipping_item->set_method_title($rate->get_label());
            $shipping_item->set_method_id($rate->get_method_id());
            $shipping_item->set_total($rate->get_cost());
            $shipping_item->set_taxes(['total' => $rate->get_taxes()]);
            $order->add_item($shipping_item);
        }
        $coupon_code = self::getSession('coupon_code');
        if ($coupon_code && method_exists($order, 'apply_coupon')) {
            try {
                $order->apply_coupon($coupon_code);
            } catch (\Exception $e) {
                self::log('Coupon could not be applied: ' . $e->getMessage());
            }
        }
        $order->calculate_totals();
        $order->save();
        $total = $order->get_total();
        $order->update_meta_data('_chat_id', self::$chat_id);
        $order->update_meta_data('_order_from', 'telegram');
        if (class_exists('\\TeleNexa\\Stars')) {
            \TeleNexa\Stars::saveOrderStarsAmount($order);
        }
        $order->save();
        return [
            'total'       => $total,
            'order_id'    => $order->get_order_number(),
            'order_db_id' => $order->get_id(),
            'url'         => self::getPaymentUrl($order),
        ];
    }
    public static function getPaymentUrl($order)  {
        $url = $order->get_checkout_payment_url();
        $key = self::getKeyFromUrl($url);
        $payment_url = '';
        if(!empty($key)) $payment_url = get_site_url() . "?order_id={$order->get_id()}&key={$key}&woogram_payment=";
        return $payment_url;
    }
    public static function getKeyFromUrl($url)  {
        $res = parse_url($url);
        parse_str($res['query'], $get_array);
        return !empty($get_array['key'])?$get_array['key']:'';
    }
    public static function fixPersianChar($text)  {
        // Telegram/user fields may be null when an optional value is absent.
        // Cast before trim for PHP 8.1+ compatibility.
        $text = trim((string) $text);
        $src = array('ك','ي','۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩');
        $des = array('ک','ی','0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9');
        return str_ireplace($src,$des,$text);
    }
}
