<?php
namespace TeleNexa\Bot;
use TeleNexa\Http\TelegramApiAdapter;

/** Core behavior for WooBot. */
trait CoreTrait {
    public static function setChatId($chat_id)  {
        self::$chat_id = self::fixPersianChar($chat_id);
    }
    public static function getException($e)  {
        $start = "' with message '";
        $end = "' in";
        $error = '';
        $r = explode($start, $e);
        if (isset($r[1])) {
            $r = explode($end, $r[1]);
            $error = isset($r[0])?$r[0]:'';
            $e = $error;
        }
        if(strpos($e, 'API KEY not defined') !== false) {
            echo '<div class="error">';
            echo '<p>'.self::botTranslate('API KEY is empty.', 'telenexa-commerce-for-telegram').'</p>';
            echo '</div>';
        }  else if(strpos($e, 'Webhook was not set') !== false)  {
            echo '<div class="error">';
            echo '<p>'.self::botTranslate('Webhook was not set!', 'telenexa-commerce-for-telegram').'</p>';
            echo '</div>';
        }  else if(strpos($e, 'Telegram returned an invalid response!') !== false) {
            echo '<div class="error">';
            echo '<p>'.self::botTranslate('Telegram returned an invalid response!', 'telenexa-commerce-for-telegram').'</p>';
            echo '</div>';
        }  else if(!empty($e)) {
            echo '<div class="error">';
            echo '<p>'.$e.'</p>';
            echo '</div>';
        }  else  {
            echo '<div class="error">';
            echo '<p>'.self::botTranslate('Unknown error!', 'telenexa-commerce-for-telegram').'</p>';
            echo '</div>';
        }
    }
    public static function getBotInfo()  {
        $options = get_option( 'woogram_settings');
        $bot_token = isset($options['token']) ? self::fixPersianChar($options['token']) : '';
        $bot_name = isset($options['username']) ? self::fixPersianChar($options['username']) : '';
        $plugin_key = self::fixPersianChar(get_option( 'woogram_webhook_key'));
        return ['token'=>$bot_token, 'name'=>$bot_name, 'plugin_key' => $plugin_key];
    }
    public static function setHook($direct=0)  {
        $bot_info = self::getBotInfo();
        try  {
            if(empty($bot_info['token']) && empty($bot_info['name'])) return false;
            if($direct) {
                $hook_url = get_site_url() . "/?" . $bot_info['plugin_key'] . "=1";
            }  else  {
                $hook = ['key'=> $bot_info['plugin_key'], 'url'=>get_site_url()];
                $seriall = serialize($hook);
                $base = base64_encode($seriall);
                $hook_url = 'https://milmit.net/receive.php?key=' . $base;
            }
            $secret = (string) woogram_option('webhook_secret_token');
            if ($secret !== '' && !preg_match('/\A[A-Za-z0-9_-]{1,256}\z/', $secret)) {
                self::log('Webhook registration failed: invalid secret token format.');
                return false;
            }
            $params = ['url' => $hook_url];
            if ($secret !== '') {
                $params['secret_token'] = $secret;
            }
            $result = TelegramApiAdapter::request('setWebhook', $params);
            if (method_exists( $result , 'isOk' ) AND $result->isOk() )  {
                return $result->getDescription() ?: self::botTranslate('Webhook was set', 'telenexa-commerce-for-telegram');
            }  else  {
            }
        }  catch (\Exception $e)  {
            self::getException($e->getMessage());
        }
        return false;
    }
    public static function init($mode='out')  {
        self::$token = woogram_option('token');
        self::$username = woogram_option('username');
        self::$currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
        if(!empty(self::$token) && !empty(self::$username)) {
            try {
                self::$token = self::fixPersianChar(self::$token);
                self::$username = self::fixPersianChar(self::$username);
                // Telegram requests are handled by TelegramApiAdapter. Keep a
                // truthy marker for legacy menu initialization checks.
                self::$telegram = true;
            }  catch (\Exception $e)  {
            }
        }  else  {
            return false;
        }
        self::$product_per_page = intval(woogram_option('display_productsperpage'))>0?intval(woogram_option('display_productsperpage')):5;
        self::$display_alert_in_bot = woogram_option('display_alert_in_bot')?1:0;
        $display_categories = array(1=>'category_per_key_1col',2=>'category_per_key_2col',3=>'category_per_key_3col',4=>'category_per_line');
        self::$category_list_type = isset($display_categories[woogram_option('display_categories')])?$display_categories[woogram_option('display_categories')]:'category_per_key_1col';
        $display_productslist = array(1=>'product_per_post',2=>'product_per_line',3=>'product_per_key');
        self::$product_list_type = isset($display_productslist[woogram_option('display_productslist')])?$display_productslist[woogram_option('display_productslist')]:'product_per_post';
        $display_product = array(1=>'product_by_photo',2=>'product_by_text',3=>'product_by_url');
        self::$product_single_type = isset($display_product[woogram_option('display_product')])?$display_product[woogram_option('display_product')]:'product_by_photo';
        self::$display_product_excerpt = woogram_option('display_product_excerpt')?(int)woogram_option('display_product_excerpt'):0;
        self::$display_product_excerpt_length = woogram_option('display_product_excerpt_length')?(int)woogram_option('display_product_excerpt_length'):0;
        $display_photosize = array(1=>'thumbnail',2=>'medium',3=>'full',);
        self::$display_photosize = isset($display_photosize[woogram_option('display_photosize')])?$display_photosize[woogram_option('display_photosize')]:'medium';
        self::$display_search = woogram_option('display_search')?(int)woogram_option('display_search'):0;
        self::$enabledingroups = woogram_option('enabledingroups')?1:0;
        self::$cats_show_count = woogram_option('display_categorycount')?1:0;
        self::$cats_hide_empty = woogram_option('display_hideemptycategory')?1:0;
        self::$hide_products_edge_subcategory = woogram_option('hide_products_edge_subcategory')?1:0;
        self::$only_display_instock_products = woogram_option('only_display_instock_products')?1:0;
        self::$display_showqty = in_array(woogram_option('display_showqty'), [0,1,2]) ? woogram_option('display_showqty') : 0;
        self::$display_product_link_type = woogram_option('display_product_link_type')?woogram_option('display_product_link_type'):1;
        self::$display_price_discounted = in_array($mode, woogram_option('display_price_discounted'))?1:0;
        self::$display_price = in_array($mode, woogram_option('display_price'))?1:0;
        self::$show_link_buy_on_site = in_array($mode, woogram_option('display_linkbuyonsite'))?1:0;
        self::$show_link_quick_buy = in_array($mode, woogram_option('display_linkquickbuy'))?1:0;
        self::$show_button_order = in_array($mode, woogram_option('display_buttonorder'))?1:0;
        self::$show_button_view_on_site = in_array($mode, woogram_option('display_buttonviewonsite'))?1:0;
        $display_howgetqty = self::fixPersianChar(trim((string) woogram_option('display_howgetqty')));
        $numbers = [];
        $R = 0;
        if($display_howgetqty) {
            $rows = explode("\n",$display_howgetqty);
            if(!empty($rows) && is_array($rows)) {
                foreach($rows as $row) {
                    $row = trim($row);
                    $row = explode(" ",$row);
                    if(!empty($row) && is_array($row)) {
                        $numbers[$R] = [];
                        foreach($row as $number) {
                            if(!empty($number) && is_numeric($number)) {
                                $numbers[$R][] = $number;
                            }
                        }
                        $R++;
                    }
                }
            }
        }
        self::$display_howgetqty = $numbers;
        self::$order_cart_status = woogram_option('order_cart_status')?1:0;
        self::$show_coupon_in_cart = woogram_option('show_coupon_in_cart')?1:0;
        self::$register_req_qty = woogram_option('register_req_qty')?1:0;
        self::$register_req_email = woogram_option('register_req_email')?1:0;
        self::$register_req_state = woogram_option('register_req_state')?1:0;
        self::$register_req_city = woogram_option('register_req_city')?1:0;
        self::$register_req_name = woogram_option('register_req_name')?1:0;
        self::$register_req_phone = woogram_option('register_req_phone')?(int)woogram_option('register_req_phone'):0;
        self::$register_req_address = woogram_option('register_req_address')?1:0;
        self::$register_req_zip = woogram_option('register_req_zip')?1:0;
        self::$register_req_ordernote = woogram_option('register_req_ordernote')?1:0;
        self::$register_req_shipping = woogram_option('register_req_shipping')?1:0;
        self::$register_req_payment = woogram_option('register_req_payment')?1:0;
        self::$sign = "\n\n" . self::translateBotString('setting.botsign', woogram_option('botsign'));
        $pages = array();
        $pages['new'] = ['key' => self::botTranslate('⭐ New Products', 'telenexa-commerce-for-telegram'), 'alias' => '/newproducts'];
        $pages['cat'] = ['key' => self::botTranslate('📦 All Categories', 'telenexa-commerce-for-telegram'), 'alias' => '/category'];
        $pages['track'] = ['key' => self::botTranslate('❓ Track Orders', 'telenexa-commerce-for-telegram'), 'alias' => '/tracking'];
        $pages['cart'] = ['key' => self::botTranslate('🛒 View Cart', 'telenexa-commerce-for-telegram'), 'alias' => '/cart'];
        $pages['help'] = ['key' => self::botTranslate('ℹ️ Help', 'telenexa-commerce-for-telegram'), 'alias' => '/help'];
        $pages['wishlist'] = ['key' => self::botTranslate('❤️ Wishlist', 'telenexa-commerce-for-telegram'), 'alias' => '/wishlist'];
        $pages['settings'] = ['key' => self::botTranslate('⚙️ Settings', 'telenexa-commerce-for-telegram'), 'alias' => '/settings'];

        $contact_keyboard = [];
        $contactuspageurl = woogram_option('contactuspageurl');
        if(!empty($contactuspageurl) && filter_var($contactuspageurl, FILTER_VALIDATE_URL)) {
            $contact_keyboard[] = [ ['text' => self::botTranslate('🌐 Store Website', 'telenexa-commerce-for-telegram'), 'url' => $contactuspageurl] ];
        }
        $contact_keyboard[] = [ ['text' => self::botTranslate('🔙 Back', 'telenexa-commerce-for-telegram'), 'callback_data' => '/home'] ];
        $pages['contact'] = [
            'key' => self::botTranslate('📞 Contact Us', 'telenexa-commerce-for-telegram'),
            'alias' => '/contactus',
            'text' => self::translateBotString('setting.contactuspage', woogram_option('contactuspage')),
            'keyboard' => $contact_keyboard
        ];

        $pages['start_user'] = [
            'key' => '/start',
            'text' => self::translateBotString('setting.wmuser', woogram_option('wmuser')),
            'keyboard' => self::buildMainMenuKeyboard(self::getLanguage()),
        ];
        $pages['stop_user'] = ['key' => '/stop', 'text' => self::translateBotString('setting.bmuser', woogram_option('bmuser')), 'keyboard' => array()];
        $pages['home'] = $pages['start_user'];
        $pages['home']['key'] = 'home';
        $pages['home']['alias'] = '/home';
        $pages['home']['text'] = self::translateBotString('setting.messagehome', woogram_option('messagehome'));
        self::$pages = $pages;
        // Menu commands are bot-wide configuration. Never call Telegram's
        // setMyCommands/setChatMenuButton for every webhook update.
        if ($mode !== 'in' && woogram_option('feature_menu_commands') !== '0') {
            self::configureTelegramMenu();
        }
    }
    public static function configureTelegramMenu() {
        if (empty(self::$token)) return false;
        $commands = [
            ['command' => 'start', 'description' => self::botTranslate('Start the store', 'telenexa-commerce-for-telegram')],
            ['command' => 'shop', 'description' => self::botTranslate('Open the store', 'telenexa-commerce-for-telegram')],
            ['command' => 'search', 'description' => self::botTranslate('Search products', 'telenexa-commerce-for-telegram')],
            ['command' => 'cart', 'description' => self::botTranslate('View cart', 'telenexa-commerce-for-telegram')],
            ['command' => 'orders', 'description' => self::botTranslate('My orders', 'telenexa-commerce-for-telegram')],
            ['command' => 'wishlist', 'description' => self::botTranslate('Saved products', 'telenexa-commerce-for-telegram')],
            ['command' => 'help', 'description' => self::botTranslate('Help', 'telenexa-commerce-for-telegram')],
        ];

        $menu_text = self::botTranslate('Store menu', 'telenexa-commerce-for-telegram');
        $signature = md5(wp_json_encode([$commands, $menu_text]));
        $state = get_option('woogram_telegram_menu_sync', []);
        $state = is_array($state) ? $state : [];

        // Avoid duplicate API calls during normal WordPress page loads. A
        // changed command list gets a new signature and is synchronized once.
        if (($state['signature'] ?? '') === $signature && !empty($state['synced_at'])) {
            return true;
        }

        // Respect Telegram's retry_after response after a rate-limit error.
        $blocked_until = (int) get_transient('woogram_telegram_menu_backoff');
        if ($blocked_until > time()) return false;

        $result = TelegramApiAdapter::request('setMyCommands', ['commands' => $commands]);
        if (!is_object($result) || !$result->isOk()) {
            $description = is_object($result) && method_exists($result, 'getDescription') ? $result->getDescription() : '';
            if (preg_match('/retry after (\d+)/i', $description, $matches)) {
                set_transient('woogram_telegram_menu_backoff', time() + min(1800, max(60, absint($matches[1]))), 1800);
            }
            return false;
        }

        $menu_result = TelegramApiAdapter::request('setChatMenuButton', ['menu_button' => ['type' => 'commands', 'text' => $menu_text]]);
        if (!is_object($menu_result) || !$menu_result->isOk()) {
            $description = is_object($menu_result) && method_exists($menu_result, 'getDescription') ? $menu_result->getDescription() : '';
            if (preg_match('/retry after (\d+)/i', $description, $matches)) {
                set_transient('woogram_telegram_menu_backoff', time() + min(1800, max(60, absint($matches[1]))), 1800);
            }
            return false;
        }

        update_option('woogram_telegram_menu_sync', [
            'signature' => $signature,
            'synced_at' => time(),
        ], false);
        return $result;
    }
    public static function log2file($var)  {
        file_put_contents(realpath(dirname(__FILE__))."/log.txt", json_encode($var) );
    }
}
