<?php
namespace TeleNexa\Bot;

/**
 * Dynamic Main Menu Manager for WooBot.
 * Supports enabling/disabling buttons, reordering, custom labels in FA/EN, custom buttons, and Mini App.
 */
trait MenuTrait {

    /**
     * Get default core buttons definition.
     */
    public static function getDefaultMenuItems() {
        return [
            'cat' => [
                'enabled'   => 1,
                'order'     => 1,
                'title_fa'  => '📦 دسته‌بندی‌ها',
                'title_en'  => '📦 Categories',
                'callback'  => '/category',
                'alias'     => '/category',
                'icon'      => 'dashicons-category',
            ],
            'new' => [
                'enabled'   => 1,
                'order'     => 2,
                'title_fa'  => '⭐ جدیدترین محصولات',
                'title_en'  => '⭐ New Products',
                'callback'  => '/newproducts',
                'alias'     => '/newproducts',
                'icon'      => 'dashicons-star-filled',
            ],
            'search' => [
                'enabled'   => 1,
                'order'     => 3,
                'title_fa'  => '🔍 جستجوی محصولات',
                'title_en'  => '🔍 Search',
                'callback'  => '/search',
                'alias'     => '/search',
                'icon'      => 'dashicons-search',
            ],
            'cart' => [
                'enabled'   => 1,
                'order'     => 4,
                'title_fa'  => '🛒 سبد خرید',
                'title_en'  => '🛒 View Cart',
                'callback'  => '/cart',
                'alias'     => '/cart',
                'icon'      => 'dashicons-cart',
            ],
            'orders' => [
                'enabled'   => 1,
                'order'     => 5,
                'title_fa'  => '📦 سفارش‌های من',
                'title_en'  => '📦 My Orders',
                'callback'  => '/tracking',
                'alias'     => '/tracking',
                'icon'      => 'dashicons-clipboard',
            ],
            'wishlist' => [
                'enabled'   => 1,
                'order'     => 6,
                'title_fa'  => '❤️ علاقه‌مندی‌ها',
                'title_en'  => '❤️ Wishlist',
                'callback'  => '/wishlist',
                'alias'     => '/wishlist',
                'icon'      => 'dashicons-heart',
            ],
            'settings' => [
                'enabled'   => 1,
                'order'     => 7,
                'title_fa'  => '🌐 تنظیمات / زبان',
                'title_en'  => '🌐 Settings / Language',
                'callback'  => '/settings',
                'alias'     => '/settings',
                'icon'      => 'dashicons-admin-generic',
            ],
            'contact' => [
                'enabled'   => 1,
                'order'     => 8,
                'title_fa'  => '📞 تماس با ما',
                'title_en'  => '📞 Contact Us',
                'callback'  => '/contactus',
                'alias'     => '/contactus',
                'icon'      => 'dashicons-phone',
            ],
            'help' => [
                'enabled'   => 1,
                'order'     => 9,
                'title_fa'  => 'ℹ️ راهنما',
                'title_en'  => 'ℹ️ Guide',
                'callback'  => '/help',
                'alias'     => '/help',
                'icon'      => 'dashicons-editor-help',
            ],
            'webapp' => [
                'enabled'   => 1,
                'order'     => 10,
                'title_fa'  => '📱 مینی‌اپ فروشگاه',
                'title_en'  => '📱 Store Mini App',
                'type'      => 'webapp',
                'icon'      => 'dashicons-smartphone',
            ],
        ];
    }

    /**
     * Get active configured menu items merged with defaults.
     */
    public static function getActiveMenuItems() {
        $defaults = self::getDefaultMenuItems();
        $saved = woogram_option('main_menu_items');

        if (!is_array($saved) || empty($saved)) {
            return $defaults;
        }

        $merged = [];
        foreach ($defaults as $key => $default_item) {
            if (isset($saved[$key]) && is_array($saved[$key])) {
                $merged[$key] = array_merge($default_item, $saved[$key]);
                $merged[$key]['enabled'] = !empty($saved[$key]['enabled']) ? 1 : 0;
                $merged[$key]['order']   = isset($saved[$key]['order']) ? (int)$saved[$key]['order'] : $default_item['order'];
                $merged[$key]['title_fa'] = !empty($saved[$key]['title_fa']) ? $saved[$key]['title_fa'] : $default_item['title_fa'];
                $merged[$key]['title_en'] = !empty($saved[$key]['title_en']) ? $saved[$key]['title_en'] : $default_item['title_en'];
            } else {
                $merged[$key] = $default_item;
            }
        }
        return $merged;
    }

    /**
     * Get custom buttons list defined in settings.
     */
    public static function getCustomButtons() {
        $buttons = woogram_option('custom_menu_buttons');
        if (is_string($buttons)) {
            $buttons = json_decode($buttons, true);
        }
        return is_array($buttons) ? $buttons : [];
    }

    /**
     * Build the Telegram Inline or Reply Keyboard for the main menu.
     */
    public static function buildMainMenuKeyboard($lang = null) {
        if ($lang === null) {
            $lang = self::getLanguage();
        }

        $items = self::getActiveMenuItems();

        // Filter only enabled items
        $active_items = [];
        foreach ($items as $k => $item) {
            if (!empty($item['enabled'])) {
                // If webapp is disabled globally, skip
                if ($k === 'webapp' && woogram_option('feature_webapp_enable') === '0') {
                    continue;
                }
                $active_items[$k] = $item;
            }
        }

        // Sort by order weight
        uasort($active_items, function($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        $buttons_list = [];
        $webapp_url = self::getWebAppUrl();

        foreach ($active_items as $key => $item) {
            $language_code = strtolower(substr((string) $lang, 0, 2));
            $defaults = self::getDefaultMenuItems();
            $is_custom_label = isset($defaults[$key]) && (
                (string) $item['title_fa'] !== (string) $defaults[$key]['title_fa'] ||
                (string) $item['title_en'] !== (string) $defaults[$key]['title_en']
            );
            if ($is_custom_label && !in_array($language_code, ['fa', 'en'], true)) {
                $title = self::translateBotString('menu.' . $key . '.en', $item['title_en'], $language_code);
            } else {
                $title = self::botText('menu.' . $key, $item['title_fa'], $item['title_en'], $language_code);
            }

            if ($key === 'webapp') {
                $buttons_list[] = [
                    'text' => $title,
                    'web_app' => ['url' => $webapp_url]
                ];
            } else {
                $buttons_list[] = [
                    'text' => $title,
                    // Core menu routes are not user-editable data. Always use
                    // the stable alias so an old/corrupt saved callback cannot
                    // leave buttons such as New Products or My Orders inert.
                    'callback_data' => !empty($item['alias']) ? $item['alias'] : $item['callback']
                ];
            }
        }

        // Add custom buttons
        $custom_buttons = self::getCustomButtons();
        foreach ($custom_buttons as $cb_idx => $cb) {
            if (empty($cb['enabled'])) continue;
            $cb_language = strtolower(substr((string) $lang, 0, 2));
            $cb_source_language = $cb_language === 'fa' ? 'fa' : 'en';
            $cb_title = ($cb_source_language === 'en' && !empty($cb['title_en'])) ? $cb['title_en'] : (!empty($cb['title_fa']) ? $cb['title_fa'] : 'Custom');
            $cb_title = self::translateBotString('menu.custom.' . (int) $cb_idx . '.' . $cb_source_language, $cb_title, $cb_language);
            $cb_type = isset($cb['type']) ? $cb['type'] : 'url';
            $cb_val  = isset($cb['value']) ? $cb['value'] : '';

            if ($cb_type === 'url' && !empty($cb_val)) {
                $buttons_list[] = [
                    'text' => $cb_title,
                    'url'  => esc_url_raw($cb_val)
                ];
            } elseif ($cb_type === 'text') {
                $buttons_list[] = [
                    'text' => $cb_title,
                    'callback_data' => '/CUSTOM_BTN_' . $cb_idx
                ];
            }
        }

        // Arrange into 2 buttons per row
        $keyboard = [];
        $row = [];
        foreach ($buttons_list as $btn) {
            // WebApp button gets a prominent full-width row
            if (isset($btn['web_app'])) {
                if (!empty($row)) {
                    $keyboard[] = $row;
                    $row = [];
                }
                $keyboard[] = [$btn];
                continue;
            }

            $row[] = $btn;
            if (count($row) >= 2) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $keyboard[] = $row;
        }

        return $keyboard;
    }

    /**
     * Get the Mini App URL.
     */
    public static function getWebAppUrl() {
        return add_query_arg([
            'woogram_webapp' => '1',
            'lang' => self::getLanguage()
        ], home_url('/'));
    }
}
