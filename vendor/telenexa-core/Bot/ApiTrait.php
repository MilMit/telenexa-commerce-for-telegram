<?php
namespace TeleNexa\Bot;
use TeleNexa\Http\TelegramApiAdapter;

/** Api behavior for WooBot. */
trait ApiTrait {

    /**
     * Call Telegram Bot API methods without relying on third-party
     * request/entity definitions.
     */
    public static function telegramApiCall($method, array $params = []) {
        return TelegramApiAdapter::call($method, $params);
    }

    public static function sendTelegramStarsInvoice(array $params) {
        $params['currency'] = 'XTR';
        return TelegramApiAdapter::sendInvoice($params);
    }

    public static function refundTelegramStarsPayment($user_id, $telegram_payment_charge_id) {
        return TelegramApiAdapter::refundStarPayment([
            'user_id'                   => absint($user_id),
            'telegram_payment_charge_id' => sanitize_text_field($telegram_payment_charge_id),
        ]);
    }
    public static function getBotWooProduct($product_id) {
        if (!function_exists('wc_get_product')) return false;
        $product_id = (string) $product_id;
        if (strpos($product_id, 'V') !== false) {
            $ids = explode('V', $product_id);
            $product_id = absint(end($ids));
        }
        return $product_id ? wc_get_product($product_id) : false;
    }

    public static function isBotProductPurchasable($product_id) {
        $product = self::getBotWooProduct($product_id);
        if (!$product) return false;
        if (method_exists($product, 'is_purchasable') && !$product->is_purchasable()) return false;
        if (method_exists($product, 'is_in_stock') && !$product->is_in_stock()) return false;
        return true;
    }

    public static function existPhysicalProduct()  {
        $cart = self::getSession('cart');
        if(!empty($cart)&&is_array($cart)) {
            foreach($cart as $product_id=>$qty) {
                $base_id = strpos($product_id, 'V') !== false ? explode('V', $product_id)[0] : $product_id;
                $lookup_id = strpos($product_id, 'V') !== false ? explode('V', $product_id)[1] : $base_id;
                $product = function_exists('wc_get_product') ? wc_get_product($lookup_id) : false;
                if (!$product && function_exists('wc_get_product')) {
                    $product = wc_get_product($base_id);
                }
                if ($product && method_exists($product, 'needs_shipping')) {
                    if ($product->needs_shipping()) return true;
                    continue;
                }
                $api_product = self::apiGetProduct($product_id);
                if (!empty($api_product['virtual']) && empty($api_product['downloadable'])) continue;
                if (empty($api_product['virtual'])) return true;
            }
        }
        return false;
    }
    public static function price($price)  {
        $price = self::fixPersianChar($price);
        $price += 0 ;
        $currency = (string) self::$currency;
        for ($currency_decode_attempt = 0; $currency_decode_attempt < 3; $currency_decode_attempt++) {
            $decoded_currency = html_entity_decode($currency, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded_currency === $currency) {
                break;
            }
            $currency = $decoded_currency;
        }
        return number_format($price) . wp_strip_all_tags($currency);
    }
    public static function getPrices($product)  {
        $price = [];
        if (!is_array($product)) return $price;
        $price['regular_price_label'] = '';
        $price['price_label'] = '';
        if(!empty($product['variations']) && is_array($product['variations']) && count($product['variations']) ) {
            $variations_price = [];
            $variations_regular_price = [];
            if(!empty($product['variations']) && is_array($product['variations'])) {
                foreach($product['variations'] as $variation) {
                    if (isset($variation['price']) && is_numeric($variation['price'])) $variations_price[] = (float) $variation['price'];
                    if (isset($variation['regular_price']) && is_numeric($variation['regular_price'])) $variations_regular_price[] = (float) $variation['regular_price'];
                }
            }
            if(empty($variations_price)) return $price;
            $min_price = min( $variations_price );
            $max_price = max( $variations_price );
            $min_reg_price = !empty($variations_regular_price) ? min( $variations_regular_price ) : 0;
            $max_reg_price = !empty($variations_regular_price) ? max( $variations_regular_price ) : 0;
            $price['price_label'] = ($min_price !== $max_price) ? self::price($min_price).'-'.self::price($max_price) : self::price($min_price);
            $price['regular_price_label'] = ($min_reg_price !== $max_reg_price) ? self::price($min_reg_price).'-'.self::price($max_reg_price) : self::price($min_reg_price);
        }  else  {
            $price['regular_price_label'] = !empty($product['on_sale']) ? self::price($product['regular_price'] ?? 0) : '';
            $price['price_label'] = self::price($product['price'] ?? 0);
        }
        return $price;
    }
    public static function vAPI()  {
        return (function_exists('WC') && version_compare( WC()->version, '3.0.0', '<' )) ? 1 : 2;
    }
    public static function rest_get_server_rewrite()  {
        global $wp_rest_server;
        if ( empty( $wp_rest_server ) )  {
            $wp_rest_server_class = apply_filters( 'wp_rest_server_class', 'TeleNexa_REST_Server_Rewrite' );
            $wp_rest_server = new $wp_rest_server_class;
            do_action( 'rest_api_init', $wp_rest_server );
        }
        return $wp_rest_server;
    }
    public static function getAdminUserId() {
        global $wpdb;
        $wp_user_search = $wpdb->get_results("SELECT ID, display_name FROM $wpdb->users ORDER BY ID");
        $adminArray = array();
        foreach ( $wp_user_search as $userid )  {
            if(is_super_admin($userid->ID)) return $userid->ID;
        }
        return 0;
    }
    public static function api($route = '')  {
        $last_request_method = $_SERVER['REQUEST_METHOD'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $admin_id = self::getAdminUserId();
        if(empty($admin_id)) {
            self::log("ERROR: Admin id is empty.");
            die("Admin id is empty.");
            return false;
        }
        $current_user = wp_get_current_user();
        $current_id = isset($current_user->ID) ? $current_user->ID : 0;
        wp_set_current_user( $admin_id );
        $route = trim($route,'/');
        $server = self::rest_get_server_rewrite();
        $api_version = self::vAPI();
        $route = "/wc/v{$api_version}/{$route}";
        $result = $server->serve_request_rewrite( $route );
        $_SERVER['REQUEST_METHOD'] = $last_request_method;
        wp_set_current_user( $current_id );
        return $result;
    }
    public static function apiGetProduct($product_id)  {
        $variation_id = 0;
        if(strpos($product_id,'V')!==false) {
            $product_id = explode('V', $product_id);
            $variation_id = $product_id[1];
            $product_id = $product_id[0];
        }
        // Use WooCommerce CRUD directly. REST server responses can be false
        // on restricted hosts and were the source of several dead buttons.
        $wc_product = function_exists('wc_get_product') ? wc_get_product((int) $product_id) : false;
        if (!$wc_product) return [];
        $product_api = [
            'id' => $wc_product->get_id(),
            'name' => $wc_product->get_name(),
            'type' => $wc_product->get_type(),
            'price' => (string) $wc_product->get_price(),
            'regular_price' => (string) $wc_product->get_regular_price(),
            'sale_price' => (string) $wc_product->get_sale_price(),
            'on_sale' => $wc_product->is_on_sale(),
            'permalink' => get_permalink($wc_product->get_id()),
            'sku' => $wc_product->get_sku(),
            'stock_quantity' => $wc_product->get_stock_quantity(),
            'stock_status' => $wc_product->get_stock_status(),
            'virtual' => $wc_product->is_virtual(),
            'downloadable' => $wc_product->is_downloadable(),
            'short_description' => $wc_product->get_short_description(),
            'description' => $wc_product->get_description(),
            'parent_id' => $wc_product->get_parent_id(),
            'attributes' => [],
            'variations' => [],
        ];
        if ($wc_product->is_type('variable')) {
            foreach ((array) $wc_product->get_attributes() as $attribute_name => $attribute) {
                $options = method_exists($attribute, 'get_options') ? $attribute->get_options() : [];
                $product_api['attributes'][] = [
                    'name' => wc_attribute_label($attribute_name),
                    'options' => array_values($options),
                    'variation' => method_exists($attribute, 'get_variation') ? $attribute->get_variation() : true,
                ];
            }
            foreach ((array) $wc_product->get_children() as $child_id) {
                $variation = wc_get_product($child_id);
                if (!$variation || !$variation->exists()) continue;
                $variation_attributes = $variation->get_variation_attributes();
                $variation_name = function_exists('wc_get_formatted_variation')
                    ? wc_get_formatted_variation($variation, true)
                    : implode(' / ', array_filter(array_values($variation_attributes)));
                $product_api['variations'][] = [
                    'id' => $variation->get_id(),
                    'price' => (string) $variation->get_price(),
                    'regular_price' => (string) $variation->get_regular_price(),
                    'attributes' => $variation_attributes,
                    'variation_name' => wp_strip_all_tags($variation_name),
                ];
            }
        }
        if($variation_id) {
            if(!empty($product_api['variations']) && is_array($product_api['variations'])) {
                foreach($product_api['variations'] as $variation) {
                    if($variation_id == $variation['id']) {
                        $variation['name'] = $product_api['name'].' - '.$variation['variation_name'];
                        $product_api = $variation;
                        break;
                    }
                }
            }
        }
        $product_api = array_merge($product_api, self::getPrices($product_api));
        return $product_api;
    }
    public static function apiGetProductVariations($product_id)  {
        $parent = function_exists('wc_get_product') ? wc_get_product(absint($product_id)) : false;
        if ($parent && method_exists($parent, 'get_children')) {
            $variations = [];
            foreach ((array) $parent->get_children() as $child_id) {
                $variation = wc_get_product($child_id);
                if ($variation) $variations[] = ['id' => $variation->get_id(), 'price' => (string) $variation->get_price(), 'regular_price' => (string) $variation->get_regular_price(), 'attributes' => $variation->get_variation_attributes(), 'variation_name' => wp_strip_all_tags(wc_get_formatted_variation($variation, true))];
            }
            return $variations;
        }
        return [];
        /* Legacy REST fallback intentionally retained below for reference only. */
        /*
        if(self::vAPI()==1) {
            $product = self::apiGetProduct($product_id);
            $variations = $product['variations'];
        }  else if(self::vAPI()==2) {
            $variations = self::api("products/{$product_id}/variations");
            if(!empty($variations) && is_array($variations)) {
                foreach($variations as $key => $variation) {
                    $options = array_map( function ($v)  {
                        return urldecode($v['option']);
                    }
                    , $variation['attributes'] );
                    $variation_name = implode(' , ', $options);
                    $variations[$key]['variation_name'] = $variation_name;
                }
            }
        }
        return $variations;
        */
    }
    public function fixBugInPluginShortcodePagination( $query )  {
        $query->query['page'] = self::$page_number;
        $query->query_vars['paged'] = self::$page_number;
    }
    public static function btnHome()  {
        return ['text' => self::botText('button.home', '🏠 خانه', '🏠 Home'), 'callback_data' => '/home'];
    }
    public static function btnCancel()  {
        return ['text' => self::botText('button.cancel', '❌ انصراف', '❌ Cancel'), 'callback_data' => '/home'];
    }
    public static function btnContinueOrder()  {
        return ['text' => self::botTranslate('➕ Continue Shopping', 'telenexa-commerce-for-telegram'), 'callback_data' => '/home'];
    }
    public static function btnBack($target = '/home')  {
        return ['text' => self::botTranslate('🔙 Back', 'telenexa-commerce-for-telegram'), 'callback_data' => $target];
    }
    public static function btnCart()  {
        return ['text' => self::botTranslate('🛒 View Cart', 'telenexa-commerce-for-telegram'), 'callback_data' => '/cart'];
    }
}
