<?php
namespace TeleNexa\Bot;


/**
 * Commands and Dispatcher for WooBot.
 * Routes interactive buttons, search filters, cart operations, orders, reviews, gallery, and custom buttons.
 */
trait CommandsTrait {

    /**
     * Reply keyboards send their visible label as a normal message instead
     * of sending callback_data. Resolve configured menu labels back to the
     * stable internal command so both reply and inline keyboards behave alike.
     */
    public static function resolveMenuButtonCommand($command) {
        $normalize = static function ($value) {
            $value = (string) $value;
            // Remove common Telegram emoji/icon code points and normalize the
            // Persian half-space/whitespace differences used in menu labels.
            $value = preg_replace('/[\\x{1F000}-\\x{1FAFF}\\x{2300}-\\x{23FF}\\x{2600}-\\x{27BF}\\x{2B00}-\\x{2BFF}\\x{FE0F}\\x{200D}]/u', '', $value);
            $value = str_replace(["\u{200C}", "\u{00A0}"], ' ', $value);
            $value = preg_replace('/\\s+/u', ' ', trim($value));
            return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        };

        $needle = $normalize($command);
        if ($needle === '') return $command;

        $menu_items = method_exists(__CLASS__, 'getActiveMenuItems') ? self::getActiveMenuItems() : [];
        foreach ((array) $menu_items as $item) {
            if (!is_array($item) || !empty($item['type']) && $item['type'] === 'webapp') continue;
            $labels = [$item['title_fa'] ?? '', $item['title_en'] ?? ''];
            foreach ($labels as $label) {
                if ($normalize($label) === $needle) {
                    return !empty($item['alias']) ? $item['alias'] : ($item['callback'] ?? $command);
                }
            }
        }

        // Also support labels from the legacy CoreTrait keyboard and common
        // installations that still have the old Persian menu text saved.
        foreach ((array) self::$pages as $page) {
            if (!is_array($page) || empty($page['alias'])) continue;
            if ($normalize($page['key'] ?? '') === $needle) return $page['alias'];
        }

        if (method_exists(__CLASS__, 'getCustomButtons')) {
            foreach ((array) self::getCustomButtons() as $index => $button) {
                if (!is_array($button) || ($button['type'] ?? '') !== 'text') continue;
                foreach ([$button['title_fa'] ?? '', $button['title_en'] ?? ''] as $label) {
                    if ($normalize($label) === $needle) return '/CUSTOM_BTN_' . (int) $index;
                }
            }
        }

        $legacy_labels = [
            'دسته بندی ها' => '/category', 'دسته ها' => '/category',
            'جدیدترین محصولات' => '/newproducts', 'محصولات جدید' => '/newproducts',
            'سبد خرید' => '/cart', 'سفارش های من' => '/tracking', 'سفارشات من' => '/tracking',
            'علاقه مندی ها' => '/wishlist', 'تنظیمات زبان' => '/settings',
            'تماس با ما' => '/contactus', 'راهنما' => '/help',
        ];
        return $legacy_labels[$needle] ?? $command;
    }

    public static function commands() {
        self::testMod();
        if (empty(self::$command)) return;

        self::$command = trim((string) self::$command);
        $raw_command = self::$command;
        self::$command = self::resolveMenuButtonCommand(self::$command);
        if ($raw_command !== self::$command) {
            self::logStructured('button_route_resolved', [
                'input'    => $raw_command,
                'route'    => self::$command,
                'source'   => self::isCallback() ? 'callback_query' : 'reply_keyboard',
            ]);
        } elseif (self::isCallback()) {
            self::logStructured('button_callback_received', [
                'route' => self::$command,
            ]);
        }
        $navigation_aliases = [
            '/HOME' => '/home', '/SHOP' => '/shop', '/SEARCH' => '/search',
            '/CART' => '/cart', '/ORDERS' => '/orders', '/TRACKING' => '/tracking',
            '/WISHLIST' => '/wishlist', '/SETTINGS' => '/settings',
            '/LANGUAGE' => '/language', '/CONTACT' => '/contactus',
            '/CONTACTUS' => '/contactus', '/HELP' => '/help',
        ];
        $command_key = strtoupper(self::$command);
        if (isset($navigation_aliases[$command_key])) {
            self::$command = $navigation_aliases[$command_key];
        }

        // Rate limit check
        if (!self::checkRateLimit(self::$chat_id)) {
            self::sendMessage([
                'text' => self::botTranslate('⚠️ You are sending requests too quickly. Please wait a moment.', 'telenexa-commerce-for-telegram')
            ]);
            return;
        }

        $part = '';
        $part_id = 0;
        $page_number = 1;
        $matches = [];

        // Command regex normalizations
        if (preg_match("/^\/start\ (?:ref_)?([a-z0-9_-]+)$/si", self::$command, $matches) === 1) {
            $param = $matches[1];
            if (is_numeric($param)) {
                self::handleReferral($param);
                self::$command = '/start';
            } else {
                self::$command = "/{$param}";
            }
        }
        if (preg_match("/^\/ref_(\d+)$/si", self::$command, $matches) === 1) {
            self::handleReferral($matches[1]);
            self::$command = '/start';
        }
        if (preg_match("/^\/RATE_(\d+)_([1-5])$/si", self::$command, $matches) === 1) {
            $part = 'RATE_ORDER';
            $part_id = absint($matches[1]);
            $page_number = absint($matches[2]);
        }
        if (preg_match("/^\/stop\ ([a-z0-9_-]+)$/si", self::$command, $matches) === 1) {
            self::$command = "/{$matches[1]}";
        }
        if (preg_match("/^\/CS$/si", self::$command, $matches) === 1) {
            $part = 'CS';
            $part_id = 0;
            $page_number = 1;
        }
        if (preg_match("/^\/C(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'C';
            $part_id = $matches[1];
            $page_number = 1;
        }
        if (preg_match("/^\/C(\d+)P(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'C';
            $part_id = $matches[1];
            $page_number = $matches[2];
        }
        if (preg_match("/^\/PS(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'PS';
            $part_id = $matches[1];
            $page_number = 1;
        }
        if (preg_match("/^\/PS(\d+)P(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'PS';
            $part_id = $matches[1];
            $page_number = $matches[2];
        }
        if (preg_match("/^\/P(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'P';
            $part_id = $matches[1];
            $page_number = 1;
        }
        if (preg_match("/^\/WISHLIST(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'WISHLIST';
            $part_id = $matches[1];
        }
        if (preg_match("/^\/LANG([a-z]{2})$/si", self::$command, $matches) === 1) {
            $part = 'LANGUAGE';
            $part_id = strtolower($matches[1]);
        }
        if (preg_match("/^\/ADD2CART(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'ADD2CART';
            $part_id = $matches[1];
            $page_number = 0;
        }
        if (preg_match("/^\/ADD2CART(\d+)V(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'ADD2CART';
            $part_id = "{$matches[1]}V{$matches[2]}";
            $page_number = 0;
        }

        $selected_attribute = [];
        if (preg_match("/^\/ADD2CART(\d+)V(\d+)A([a-zA-Z0-9_-]+)O([a-zA-Z0-9_-]+)$/si", self::$command, $matches) === 1) {
            $part = 'ADD2CART';
            $part_id = "{$matches[1]}V{$matches[2]}";
            $selected_attribute = [$matches[3], $matches[4]];
            $page_number = 0;
        }
        if (preg_match("/^\/ADD2CART(\d+)C(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'ADD2CART';
            $part_id = $matches[1];
            $page_number = $matches[2];
        }
        if (preg_match("/^\/ADD2CART(\d+)V(\d+)C(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'ADD2CART';
            $part_id = "{$matches[1]}V{$matches[2]}";
            $page_number = $matches[3];
        }
        if (preg_match("/^\/EMPTYCART$/si", self::$command, $matches) === 1) {
            $part = 'EMPTYCART';
            $part_id = 0;
            $page_number = 0;
        }
        if (preg_match("/^\/DELCART(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'DELCART';
            $part_id = $matches[1];
            $page_number = 0;
        }
        if (preg_match("/^\/DELCART(\d+)V(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'DELCART';
            $part_id = "{$matches[1]}V{$matches[2]}";
            $page_number = 0;
        }
        if (preg_match("/^\/CART_DEC_([a-zA-Z0-9V]+)$/si", self::$command, $matches) === 1) {
            $part = 'CART_DEC';
            $part_id = $matches[1];
        }
        if (preg_match("/^\/CART_INC_([a-zA-Z0-9V]+)$/si", self::$command, $matches) === 1) {
            $part = 'CART_INC';
            $part_id = $matches[1];
        }
        if (preg_match("/^\/REORDER_(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'REORDER';
            $part_id = $matches[1];
        }
        if (preg_match("/^\/DOWNLOADS_(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'DOWNLOADS';
            $part_id = $matches[1];
        }
        if (preg_match("/^\/TRACKFILTER_([a-zA-Z0-9_-]+)$/si", self::$command, $matches) === 1) {
            $part = 'TRACKFILTER';
            $part_id = $matches[1];
        }
        if (preg_match("/^\/GALLERY(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'GALLERY';
            $part_id = $matches[1];
        }
        if (preg_match("/^\/REVIEWS(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'REVIEWS';
            $part_id = $matches[1];
        }
        if (preg_match("/^\/NOTIFY_STOCK(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'NOTIFY_STOCK';
            $part_id = $matches[1];
        }
        if (preg_match("/^\/SETSORT_([a-zA-Z_]+)$/si", self::$command, $matches) === 1) {
            $part = 'SETSORT';
            $part_id = $matches[1];
        }
        if (preg_match("/^\/SEARCHPAGE(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'SEARCHPAGE';
            $page_number = $matches[1];
        }
        if (preg_match("/^\/CUSTOM_BTN_(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'CUSTOM_BTN';
            $part_id = (int)$matches[1];
        }
        if (preg_match("/^\/STATE(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'STATE';
            $part_id = $matches[1];
        }
        if (preg_match("/^\/STATE0([a-zA-Z]+)$/si", self::$command, $matches) === 1) {
            $part = 'STATE';
            $part_id = $matches[1];
        }
        if (preg_match("/^\/CITY(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'CITY';
            $part_id = $matches[1];
        }
        if (preg_match("/^\/SHIPPING([a-zA-Z0-9+\/\=]+)$/si", self::$command, $matches) === 1) {
            $part = 'SHIPPING';
            $part_id = $matches[1];
        }
        if (preg_match("/^\/TRACK(\d+)$/si", self::$command, $matches) === 1) {
            $part = 'TRACK';
            $page_number = $matches[1];
        }

        if (!empty($page_number)) {
            self::$page_number = $page_number;
        }

        $last_request = self::getLastRequest();

        // No-op callback (clicks on labels/counters)
        if (self::$command === '/noop' || self::$command === '*') {
            self::logStructured('button_dispatch', [
                'route' => self::$command,
                'part'  => 'noop',
            ]);
            self::answerCallback();
            return;
        }

        if (self::isCallback() || $raw_command !== self::$command || strpos(self::$command, '/') === 0) {
            self::logStructured('button_dispatch', [
                'route'      => self::$command,
                'part'       => $part,
                'part_id'    => is_scalar($part_id) ? (string) $part_id : '',
                'page'       => (int) $page_number,
                'update_type'=> self::$update_type,
            ]);
        }

        // =========================================================================
        // COMMAND ROUTING
        // =========================================================================

        // Rate order callback
        if ($part === 'RATE_ORDER') {
            self::handleOrderRating($part_id, $page_number);
            return;
        }

        if (self::$command == '/start' || (isset(self::$pages['start_user']['key']) && self::$command == self::$pages['start_user']['key'])) {
            self::setLastRequest('home');
            $welcome_msg = self::$pages['start_user']['text'];

            // Time-limited campaign banner
            if (woogram_option('enable_campaign_banner') === 'yes') {
                $banner_text = woogram_option('campaign_banner_text');
                if (!empty($banner_text)) {
                    $welcome_msg = "🔥 <b>" . self::botTranslate('Special Announcement', 'telenexa-commerce-for-telegram') . ":</b>\n" . esc_html($banner_text) . "\n\n" . $welcome_msg;
                }
            }

            $keyboard = self::buildMainMenuKeyboard();
            self::sendMessage(['text' => $welcome_msg, 'keyboard' => $keyboard]);
            self::answerCallback(self::botTranslate('Welcome!', 'telenexa-commerce-for-telegram'));

        } elseif (self::$command == '/referral' || self::$command == 'دعوت از دوستان' || self::$command == 'کسب درآمد') {
            self::showReferralPanel();
            return;

        } elseif (self::$command == '/suggest' || self::$command == '/foryou' || self::$command == 'پیشنهادات') {
            self::showPersonalizedSuggestions();
            return;

        } elseif (self::$command == '/stop' || (isset(self::$pages['stop_user']['key']) && self::$command == self::$pages['stop_user']['key'])) {
            self::setLastRequest('stop');
            self::emptyCart();
            self::sendMessage(['text' => self::$pages['stop_user']['text'], 'keyboard' => []]);
            self::answerCallback(self::botTranslate('Goodbye!', 'telenexa-commerce-for-telegram'));

        } elseif (self::$command == '/home' || self::$command == '/shop' || self::$command == 'home' || self::$command == 'صفحه اصلی' || self::$command == 'فروشگاه') {
            self::setLastRequest('home');
            $home_msg = self::$pages['home']['text'];

            // Time-limited campaign banner
            if (woogram_option('enable_campaign_banner') === 'yes') {
                $banner_text = woogram_option('campaign_banner_text');
                if (!empty($banner_text)) {
                    $home_msg = "🔥 <b>" . self::botTranslate('Special Announcement', 'telenexa-commerce-for-telegram') . ":</b>\n" . esc_html($banner_text) . "\n\n" . $home_msg;
                }
            }

            $keyboard = self::buildMainMenuKeyboard();
            if (self::isCallback()) {
                self::editMessage(['text' => $home_msg, 'keyboard' => $keyboard]);
            } else {
                self::sendMessage(['text' => $home_msg, 'keyboard' => $keyboard]);
            }
            self::answerCallback(self::botTranslate('Home', 'telenexa-commerce-for-telegram'));

        } elseif (self::$command == '/search' || self::$command == '/SEARCH') {
            self::showSearchHub();

        } elseif (self::$command == '/TOGGLE_STOCK') {
            $cur = self::getSession('search_only_stock');
            $new = $cur ? 0 : 1;
            self::setSession('search_only_stock', $new);
            self::answerCallback($new ? self::botTranslate('In-stock filter active', 'telenexa-commerce-for-telegram') : self::botTranslate('Showing all products', 'telenexa-commerce-for-telegram'));
            self::showSearchHub();

        } elseif ($part === 'SETSORT') {
            self::setSession('search_sort', $part_id);
            self::answerCallback(self::botTranslate('Sorting updated', 'telenexa-commerce-for-telegram'));
            self::showSearchHub();

        } elseif ($part === 'SEARCHPAGE') {
            self::doAdvancedSearch(self::getLastSearch(), $page_number);

        } elseif ($part === 'CART_DEC') {
            self::changeCartQty($part_id, -1);

        } elseif ($part === 'CART_INC') {
            self::changeCartQty($part_id, 1);

        } elseif ($part === 'REORDER') {
            self::reorderItems($part_id);

        } elseif ($part === 'DOWNLOADS') {
            self::showOrderDownloads($part_id);

        } elseif ($part === 'TRACKFILTER') {
            self::trackOrder(1, $part_id);

        } elseif ($part === 'GALLERY') {
            self::showProductGallery($part_id);

        } elseif ($part === 'REVIEWS') {
            self::showProductReviews($part_id);

        } elseif ($part === 'NOTIFY_STOCK') {
            self::subscribeToStockAlert($part_id);

        } elseif ($part === 'CUSTOM_BTN') {
            $custom_btns = self::getCustomButtons();
            if (isset($custom_btns[$part_id])) {
                $lang = self::getLanguage();
                $button = $custom_btns[$part_id];
                $language_code = strtolower(substr((string) $lang, 0, 2));
                $source_language = $language_code === 'fa' ? 'fa' : 'en';
                $source_value = $source_language === 'en'
                    ? (!empty($button['value_en']) ? $button['value_en'] : ($button['value'] ?? ''))
                    : (!empty($button['value_fa']) ? $button['value_fa'] : ($button['value'] ?? ''));
                $btn_text = self::translateBotString('menu.custom.' . (int) $part_id . '.value.' . $source_language, $source_value, $language_code);
                self::answerCallback();
                self::sendMessage(['text' => $btn_text, 'keyboard' => [[self::btnHome()]]]);
            }

        } elseif (self::$command == '/bestsellers' || self::$command == 'پرفروش‌ترین‌ها') {
            self::showBestSellers(1);

        } elseif (self::$command == '/onsale' || self::$command == 'تخفیف‌دارها') {
            self::showOnSaleProducts(1);

        } elseif (self::$command == '/tracking' || self::$command == '/orders' || self::$command == 'پیگیری سفارشات' || self::$command == 'سفارش‌های من' || $part == 'TRACK') {
            self::setLastRequest('track');
            self::trackOrder($page_number);
            self::answerCallback(self::botTranslate('Track Orders', 'telenexa-commerce-for-telegram'));

        } elseif (self::$command == '/newproducts' || self::$command == '/new' || self::$command == 'جدیدترین محصولات' || $part == 'PS') {
            self::setLastRequest('new');
            self::getProducts('PS', 1, $page_number);

        } elseif (self::$command == '/category' || self::$command == '/categories' || self::$command == '/cats' || self::$command == 'دسته‌ها' || self::$command == 'دسته ها' || $part == 'CS') {
            self::setLastRequest('cats');
            self::getCats();
            self::answerCallback(self::botTranslate('Categories', 'telenexa-commerce-for-telegram'));

        } elseif (self::$command == '/wishlist' || self::$command == '/WISHLIST' || self::$command == 'علاقه‌مندی‌ها' || self::$command == 'علاقه مندی ها') {
            self::showWishlist();

        } elseif ($part == 'LANGUAGE') {
            if (self::setLanguage($part_id)) {
                self::answerCallback(self::botText('language.changed', 'زبان با موفقیت تغییر کرد.', 'Language changed successfully.', $part_id));
            }
            self::showLanguageSettings();

        } elseif (self::$command == '/settings' || self::$command == '/language' || self::$command == '/LANGUAGE' || self::$command == 'تنظیمات') {
            self::showLanguageSettings();

        } elseif ($part == 'C') {
            self::setLastRequest('cat');
            self::getProducts('C', $part_id, $page_number);

        } elseif ($part == 'WISHLIST') {
            $added = self::toggleWishlist($part_id);
            self::answerCallback($added ? self::botTranslate('Added to wishlist', 'telenexa-commerce-for-telegram') : self::botTranslate('Removed from wishlist', 'telenexa-commerce-for-telegram'));
            self::getProduct('P', $part_id);

        } elseif ($part == 'P') {
            self::setLastRequest('product');
            self::getProduct('P', $part_id);

        } elseif (self::$command == '/contactus' || self::$command == '/contact' || self::$command == 'تماس با ما' || self::$command == 'ارتباط با ما') {
            $contact_text = woogram_option('contactuspage') ?: self::botTranslate('Contact us via milmit.net', 'telenexa-commerce-for-telegram');
            $contact_kb = [];
            $contactuspageurl = woogram_option('contactuspageurl');
            if (!empty($contactuspageurl) && filter_var($contactuspageurl, FILTER_VALIDATE_URL)) {
                $contact_kb[] = [['text' => self::botTranslate('🌐 Store Website', 'telenexa-commerce-for-telegram'), 'url' => $contactuspageurl]];
            }
            $contact_kb[] = [self::btnHome()];

            if (self::isCallback()) {
                self::editMessage(['text' => $contact_text, 'keyboard' => $contact_kb]);
            } else {
                self::sendMessage(['text' => $contact_text, 'keyboard' => $contact_kb]);
            }
            self::answerCallback(self::botTranslate('Contact Us', 'telenexa-commerce-for-telegram'));

        } elseif (self::$command == '/help' || self::$command == '/HELP' || self::$command == 'راهنما') {
            self::setLastRequest('help');
            $help_text = "<b>" . self::botTranslate('TeleNexa Telegram Bot Guide', 'telenexa-commerce-for-telegram') . "</b>\n\n"
                . "▫ /start - " . self::botTranslate('Start and main menu', 'telenexa-commerce-for-telegram') . "\n"
                . "▫ /shop - " . self::botTranslate('Browse products', 'telenexa-commerce-for-telegram') . "\n"
                . "▫ /search - " . self::botTranslate('Advanced search with filters', 'telenexa-commerce-for-telegram') . "\n"
                . "▫ /newproducts - " . self::botTranslate('View newest products', 'telenexa-commerce-for-telegram') . "\n"
                . "▫ /category - " . self::botTranslate('Product categories', 'telenexa-commerce-for-telegram') . "\n"
                . "▫ /cart - " . self::botTranslate('Shopping cart', 'telenexa-commerce-for-telegram') . "\n"
                . "▫ /orders - " . self::botTranslate('Track orders', 'telenexa-commerce-for-telegram') . "\n"
                . "▫ /wishlist - " . self::botTranslate('Wishlist', 'telenexa-commerce-for-telegram') . "\n"
                . "▫ /settings - " . self::botTranslate('Change language', 'telenexa-commerce-for-telegram') . "\n"
                . "▫ /contactus - " . self::botTranslate('Support and contact', 'telenexa-commerce-for-telegram') . "\n\n"
                . "🔍 " . self::botTranslate('You can also send any product name or SKU directly to search!', 'telenexa-commerce-for-telegram');

            $help_keyboard = [[self::btnHome()]];
            if (self::isCallback()) {
                self::editMessage(['text' => $help_text, 'keyboard' => $help_keyboard]);
            } else {
                self::sendMessage(['text' => $help_text, 'keyboard' => $help_keyboard]);
            }
            self::answerCallback(self::botTranslate('Guide', 'telenexa-commerce-for-telegram'));

        } elseif ($part == 'ADD2CART') {
            self::setLastRequest('add2cart');
            self::add2Cart($part_id, $page_number, $selected_attribute);

        } elseif ($part == 'DELCART') {
            self::delCart($part_id);

        } elseif ($part == 'EMPTYCART') {
            self::emptyCart(true);

        } elseif (self::$command == '/cart' || self::$command == '/CART' || self::$command == 'سبد خرید') {
            self::showCart();

        } elseif (self::$command == '/ADDCOUPON') {
            self::requestCoupon();

        } elseif (self::$command == '/CHECKOUT') {
            self::checkLastAddress();

        } elseif (self::$command == '/PREADDRESSYES') {
            $order_data = self::getOrderData();
            if (!empty($order_data)) {
                self::stepsOrderEnd();
            } else {
                self::checkLastAddress();
            }

        } elseif (self::$command == '/PREADDRESSNO') {
            self::stepsOrderStart();

        } elseif ($part == 'STATE') {
            self::step1setState($part_id);

        } elseif ($part == 'CITY') {
            self::step2setCity($part_id);

        } elseif ($part == 'SHIPPING') {
            self::step7SetShipping($part_id);

        } elseif ($last_request === 'addcoupon') {
            self::setCoupon(self::$command);

        } elseif ($last_request === 'get_qty') {
            $qty = (self::$command + 0 > 0) ? (self::$command + 0) : 1;
            $product_id = self::getSession('last_get_qty_product_id');
            self::add2Cart($product_id, $qty);

        } elseif ($last_request === 'email') {
            self::step0setEmail(self::$command);

        } elseif ($last_request === 'city') {
            self::step2setCity(self::$command);

        } elseif ($last_request === 'name') {
            self::step3setName(self::$command);

        } elseif ($last_request === 'phone') {
            self::step4setPhone(self::$command);

        } elseif ($last_request === 'address') {
            self::step5setAddress(self::$command);

        } elseif ($last_request === 'zip') {
            self::step6SetZip(self::$command);

        } elseif ($last_request === 'ordernote') {
            if (self::$command == '/SKIPNOTE') {
                self::step7RequestShipping();
            } else {
                self::step6_1_SetOrderNote(self::$command);
            }

        } else {
            if (self::isCallback() || $raw_command !== self::$command) {
                self::logStructured('button_unresolved', [
                    'input' => $raw_command,
                    'route' => self::$command,
                    'part'  => $part,
                ]);
            }
            if (self::isCallback()) {
                self::answerCallback(self::botTranslate('This button is no longer available.', 'telenexa-commerce-for-telegram'), true);
                self::sendMessage(['text' => self::botTranslate('This action is no longer available. Please open the main menu again.', 'telenexa-commerce-for-telegram'), 'keyboard' => [[self::btnHome()]]]);
            } elseif (self::$display_search) {
                // Natural search fallback applies only to typed messages, not
                // unknown callback data from stale Telegram keyboards.
                self::doAdvancedSearch(self::$command, 1);
            }
        }
    }

    /**
     * Handle incoming referral attribution when a user visits via /start ref_XXXX.
     */
    public static function handleReferral($referrer_chat_id) {
        $referrer_chat_id = self::fixPersianChar(trim((string)$referrer_chat_id));
        $current_chat_id = self::fixPersianChar(trim((string)self::$chat_id));
        if (empty($referrer_chat_id) || empty($current_chat_id) || $referrer_chat_id === $current_chat_id) {
            return;
        }

        $user = self::getUser();
        if (!$user || !is_object($user)) return;

        $already_referred = get_post_meta($user->ID, '_referred_by', true);
        if (!empty($already_referred)) {
            return;
        }

        update_post_meta($user->ID, '_referred_by', $referrer_chat_id);
        if (method_exists(__CLASS__, 'logStructured')) {
            self::logStructured('referral_registered', [
                'invitee'  => $current_chat_id,
                'referrer' => $referrer_chat_id,
            ]);
        }

        global $wpdb;
        $referrer_post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'woogram_user' ORDER BY ID DESC LIMIT 1",
            $referrer_chat_id
        ));

        if ($referrer_post_id) {
            $count = (int) get_post_meta($referrer_post_id, '_referral_count', true);
            update_post_meta($referrer_post_id, '_referral_count', $count + 1);

            $referrer_msg = "🎉 <b>" . self::botTranslate('New Referral Joined!', 'telenexa-commerce-for-telegram') . "</b>\n\n"
                . self::botTranslate('A friend just joined using your referral link!', 'telenexa-commerce-for-telegram') . "\n"
                . sprintf(self::botTranslate('Total Successful Referrals: <b>%d</b>', 'telenexa-commerce-for-telegram'), $count + 1);

            self::sendMessage([
                'chat_id' => $referrer_chat_id,
                'text'    => $referrer_msg,
                'keyboard' => [
                    [['text' => '🎁 ' . self::botTranslate('View My Referrals', 'telenexa-commerce-for-telegram'), 'callback_data' => '/referral']],
                    [self::btnHome()]
                ]
            ]);
        }
    }

    /**
     * Show user referral stats and shareable invite link.
     */
    public static function showReferralPanel() {
        $bot_info = self::getBotInfo();
        $bot_username = !empty($bot_info['name']) ? trim($bot_info['name'], '@') : '';
        $chat_id = self::fixPersianChar((string)self::$chat_id);
        $user = self::getUser();
        $ref_count = 0;
        if (!empty($user) && is_object($user)) {
            $ref_count = (int) get_post_meta($user->ID, '_referral_count', true);
        }

        $invite_url = "https://t.me/{$bot_username}?start=ref_{$chat_id}";
        $share_text = urlencode(self::botTranslate('Discover amazing products and special offers on our Telegram store!', 'telenexa-commerce-for-telegram'));
        $share_url = "https://t.me/share/url?url=" . urlencode($invite_url) . "&text=" . $share_text;

        $msg = "🎁 <b>" . self::botTranslate('Referral & Invitation Program', 'telenexa-commerce-for-telegram') . "</b>\n\n"
            . self::botTranslate('Invite your friends to our Telegram bot and earn exclusive shopping rewards!', 'telenexa-commerce-for-telegram') . "\n\n"
            . self::botTranslate('Your Personal Invite Link:', 'telenexa-commerce-for-telegram') . "\n"
            . "<code>{$invite_url}</code>\n\n"
            . sprintf(self::botTranslate('Friends Invited: <b>%d</b>', 'telenexa-commerce-for-telegram'), $ref_count);

        $keyboard = [
            [['text' => '📤 ' . self::botTranslate('Share Invite Link', 'telenexa-commerce-for-telegram'), 'url' => $share_url]],
            [self::btnHome()]
        ];

        if (self::isCallback()) {
            self::editMessage(['text' => $msg, 'keyboard' => $keyboard]);
        } else {
            self::sendMessage(['text' => $msg, 'keyboard' => $keyboard]);
        }
        self::answerCallback(self::botTranslate('Referral Program', 'telenexa-commerce-for-telegram'));
    }

    /**
     * Record customer rating for an order.
     */
    public static function handleOrderRating($order_id, $rating) {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            self::answerCallback(self::botTranslate('Order not found.', 'telenexa-commerce-for-telegram'));
            return;
        }

        $order_chat_id = self::fixPersianChar((string)$order->get_meta('_chat_id', true));
        if ($order_chat_id !== self::fixPersianChar((string)self::$chat_id)) {
            self::answerCallback(self::botTranslate('Permission denied.', 'telenexa-commerce-for-telegram'));
            return;
        }

        $order->update_meta_data('_woogram_rating', $rating);
        $order->save();

        if (method_exists(__CLASS__, 'logStructured')) {
            self::logStructured('order_rated', [
                'order_id' => $order_id,
                'rating'   => $rating,
                'chat_id'  => self::$chat_id,
            ]);
        }

        $stars_str = str_repeat('⭐', $rating);
        $msg = "⭐️ <b>" . self::botTranslate('Thank you for your rating!', 'telenexa-commerce-for-telegram') . "</b>\n\n"
            . sprintf(self::botTranslate('You rated Order #%s: %s (%d / 5)', 'telenexa-commerce-for-telegram'), $order->get_order_number(), $stars_str, $rating) . "\n\n"
            . self::botTranslate('Your rating helps us continuously improve our products and service.', 'telenexa-commerce-for-telegram');

        $keyboard = [[self::btnHome()]];

        if (self::isCallback()) {
            self::editMessage(['text' => $msg, 'keyboard' => $keyboard]);
        } else {
            self::sendMessage(['text' => $msg, 'keyboard' => $keyboard]);
        }
        self::answerCallback(self::botTranslate('Rating recorded! Thank you.', 'telenexa-commerce-for-telegram'));
    }

    /**
     * Render personalized recommendations based on previous order history or wishlist.
     */
    public static function showPersonalizedSuggestions() {
        $user_chat_id = self::fixPersianChar((string)self::$chat_id);
        $lang = self::getLanguage();

        // 1. Check user recent orders to find categories of interest
        $cat_ids = [];
        if (method_exists(__CLASS__, 'queryCustomerOrders')) {
            $orders_data = self::queryCustomerOrders($user_chat_id, 1, 3);
            if (!empty($orders_data['orders'])) {
                foreach ($orders_data['orders'] as $order) {
                    if ($order instanceof \WC_Order) {
                        foreach ($order->get_items() as $item) {
                            $p_id = $item->get_product_id();
                            $terms = wc_get_product_term_ids($p_id, 'product_cat');
                            if (!empty($terms)) {
                                $cat_ids = array_merge($cat_ids, $terms);
                            }
                        }
                    }
                }
            }
        }

        // 2. Check wishlist
        if (method_exists(__CLASS__, 'getWishlist')) {
            $wishlist = self::getWishlist();
            if (!empty($wishlist)) {
                foreach ($wishlist as $p_id) {
                    $terms = wc_get_product_term_ids($p_id, 'product_cat');
                    if (!empty($terms)) {
                        $cat_ids = array_merge($cat_ids, $terms);
                    }
                }
            }
        }

        $cat_ids = array_unique(array_filter($cat_ids));

        // 3. Query recommended products
        $recommended_products = [];
        if (!empty($cat_ids) && method_exists(__CLASS__, 'queryProducts')) {
            $rec_query = self::queryProducts([
                'cat_id'       => reset($cat_ids),
                'only_instock' => true,
                'orderby'      => 'popularity',
                'per_page'     => 4,
            ]);
            $recommended_products = $rec_query['products'];
        }

        if (empty($recommended_products) && method_exists(__CLASS__, 'queryProducts')) {
            $rec_query = self::queryProducts([
                'only_instock' => true,
                'orderby'      => 'popularity',
                'per_page'     => 4,
            ]);
            $recommended_products = $rec_query['products'];
        }

        $header = "✨ <b>" . self::botText('recommendations.title', 'پیشنهادات ویژه و اختصاصی برای شما', 'Personalized Recommendations for You', $lang) . "</b>\n\n"
            . self::botText('recommendations.subtitle', 'بر اساس محصولات محبوب و علاقه‌مندی‌های شما:', 'Based on popular trends and items you love:', $lang);

        $keyboard = [];
        foreach ($recommended_products as $prod) {
            if ($prod instanceof \WC_Product) {
                $title = $prod->get_name();
                $price = strip_tags($prod->get_price_html());
                $keyboard[] = [
                    ['text' => "🛍️ {$title} - {$price}", 'callback_data' => "/P{$prod->get_id()}"]
                ];
            }
        }
        $keyboard[] = [self::btnHome()];

        if (self::isCallback()) {
            self::editMessage(['text' => $header, 'keyboard' => $keyboard]);
        } else {
            self::sendMessage(['text' => $header, 'keyboard' => $keyboard]);
        }
        self::answerCallback(self::botText('recommendations.callback', 'پیشنهادات شما', 'For You', $lang));
    }
}
