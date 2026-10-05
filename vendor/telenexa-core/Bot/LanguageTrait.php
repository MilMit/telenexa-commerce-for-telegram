<?php
namespace TeleNexa\Bot;

/** Language selection and WPML integration for TeleNexa. */
trait LanguageTrait {
    public static function supportedLanguages() {
        $languages = [
            'fa' => 'فارسی',
            'en' => 'English',
            'de' => 'Deutsch',
            'es' => 'Español',
            'fr' => 'Français',
            'it' => 'Italiano',
            'pt' => 'Português',
            'tr' => 'Türkçe',
            'ar' => 'العربية',
            'ru' => 'Русский',
            'zh' => '中文',
            'ja' => '日本語',
        ];

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('woogram_supported_languages', $languages);
            if (is_array($filtered)) {
                foreach ($filtered as $code => $label) {
                    $code = strtolower(substr((string) $code, 0, 2));
                    if (preg_match('/\A[a-z]{2}\z/', $code) && is_scalar($label)) {
                        $languages[$code] = (string) $label;
                    }
                }
            }
        }

        return $languages;
    }

    public static function getWpmlLanguage() {
        if (!function_exists('has_filter') || !has_filter('wpml_current_language')) {
            return null;
        }

        $language = apply_filters('wpml_current_language', null);
        $language = strtolower(substr((string) $language, 0, 2));
        // WPML may provide any locale configured by the site owner. Keep the
        // two-letter code so registered bot strings can be translated in WPML
        // String Translation even when the language is not bundled by default.
        return preg_match('/\A[a-z]{2}\z/', $language) ? $language : null;
    }

    public static function getLanguage() {
        $supported = self::supportedLanguages();
        $saved = self::getSession('language');
        if (is_string($saved) && isset($supported[$saved])) {
            return $saved;
        }

        if (woogram_option('wpml_language_sync') !== '0') {
            $wpml_language = self::getWpmlLanguage();
            if ($wpml_language) {
                return $wpml_language;
            }
        }

        $default = strtolower(substr((string) woogram_option('default_language'), 0, 2));
        return preg_match('/\A[a-z]{2}\z/', $default)
            ? $default
            : 'fa';
    }

    public static function setLanguage($language) {
        $language = strtolower(substr((string) $language, 0, 2));
        if (!isset(self::supportedLanguages()[$language])) {
            return false;
        }

        self::setSession('language', $language);
        self::updateSession();
        return true;
    }

    /**
     * Register a dynamic bot string in WPML String Translation.
     *
     * Settings entered by an administrator and menu labels are not extracted
     * from PHP source by gettext tools, so they need a stable WPML context and
     * name of their own.
     */
    public static function registerBotString($name, $value, $context = 'TeleNexa Bot') {
        if (!function_exists('do_action') || !is_scalar($value) || (string) $value === '') {
            return;
        }
        if (function_exists('has_action') && has_action('wpml_register_single_string')) {
            do_action('wpml_register_single_string', (string) $context, (string) $name, (string) $value);
        }
    }

    /**
     * Translate a dynamic bot string through WPML when available.
     * Falls back to the original value for sites without WPML.
     */
    public static function translateBotString($name, $value, $language = null, $context = 'TeleNexa Bot') {
        $value = (string) $value;
        self::registerBotString($name, $value, $context);

        if ($language === null) {
            $language = self::getLanguage();
        }
        $language = strtolower(substr((string) $language, 0, 2));

        if (function_exists('has_filter') && has_filter('wpml_translate_single_string')) {
            $translated = apply_filters(
                'wpml_translate_single_string',
                $value,
                (string) $context,
                (string) $name,
                $language
            );
            if (is_string($translated) && $translated !== '') {
                return $translated;
            }
        }

        return $value;
    }

    /**
     * Translate a built-in bot label while keeping a stable WPML string name.
     */
    public static function botText($name, $fa, $en = null, $language = null) {
        if ($en === null) {
            $en = $fa;
        }
        if ($language === null) {
            $language = self::getLanguage();
        }
        $language_code = strtolower(substr((string) $language, 0, 2));
        $bundled = self::bundledBotTranslation($name, $language_code);
        if ($bundled !== null) {
            return self::translateBotString('runtime.' . $name, $bundled, $language);
        }
        $source = $language_code === 'fa' ? $fa : $en;
        return self::translateBotString('runtime.' . $name, $source, $language);
    }

    /** Core checkout/navigation translations bundled for the supported locales. */
    public static function bundledBotTranslation($name, $language) {
        $common = [
            'de' => ['button.home'=>'🏠 Startseite','button.cancel'=>'❌ Abbrechen','button.track_order'=>'Bestellung verfolgen','button.view_order'=>'Bestelldetails anzeigen','order.registered'=>'Bestellung erfolgreich aufgegeben!','order.status_update'=>'Bestellstatus aktualisiert','payment.success'=>'Zahlung erfolgreich!'],
            'es' => ['button.home'=>'🏠 Inicio','button.cancel'=>'❌ Cancelar','button.track_order'=>'Rastrear pedido','button.view_order'=>'Ver detalles del pedido','order.registered'=>'¡Pedido registrado correctamente!','order.status_update'=>'Actualización del estado del pedido','payment.success'=>'¡Pago realizado correctamente!'],
            'fr' => ['button.home'=>'🏠 Accueil','button.cancel'=>'❌ Annuler','button.track_order'=>'Suivre la commande','button.view_order'=>'Voir les détails de la commande','order.registered'=>'Commande enregistrée avec succès !','order.status_update'=>'Mise à jour du statut de la commande','payment.success'=>'Paiement réussi !'],
            'it' => ['button.home'=>'🏠 Home','button.cancel'=>'❌ Annulla','button.track_order'=>'Traccia ordine','button.view_order'=>'Vedi dettagli ordine','order.registered'=>'Ordine registrato con successo!','order.status_update'=>'Aggiornamento stato ordine','payment.success'=>'Pagamento riuscito!'],
            'pt' => ['button.home'=>'🏠 Início','button.cancel'=>'❌ Cancelar','button.track_order'=>'Acompanhar pedido','button.view_order'=>'Ver detalhes do pedido','order.registered'=>'Pedido registrado com sucesso!','order.status_update'=>'Atualização do status do pedido','payment.success'=>'Pagamento realizado com sucesso!'],
            'tr' => ['button.home'=>'🏠 Ana sayfa','button.cancel'=>'❌ İptal','button.track_order'=>'Siparişi takip et','button.view_order'=>'Sipariş ayrıntılarını gör','order.registered'=>'Sipariş başarıyla oluşturuldu!','order.status_update'=>'Sipariş durumu güncellendi','payment.success'=>'Ödeme başarılı!'],
            'ar' => ['button.home'=>'🏠 الرئيسية','button.cancel'=>'❌ إلغاء','button.track_order'=>'تتبع الطلب','button.view_order'=>'عرض تفاصيل الطلب','order.registered'=>'تم تسجيل الطلب بنجاح!','order.status_update'=>'تحديث حالة الطلب','payment.success'=>'تم الدفع بنجاح!'],
            'ru' => ['button.home'=>'🏠 Главная','button.cancel'=>'❌ Отмена','button.track_order'=>'Отследить заказ','button.view_order'=>'Детали заказа','order.registered'=>'Заказ успешно оформлен!','order.status_update'=>'Обновление статуса заказа','payment.success'=>'Оплата прошла успешно!'],
            'zh' => ['button.home'=>'🏠 首页','button.cancel'=>'❌ 取消','button.track_order'=>'查询订单','button.view_order'=>'查看订单详情','order.registered'=>'订单已成功创建！','order.status_update'=>'订单状态更新','payment.success'=>'付款成功！'],
            'ja' => ['button.home'=>'🏠 ホーム','button.cancel'=>'❌ キャンセル','button.track_order'=>'注文を追跡','button.view_order'=>'注文詳細を見る','order.registered'=>'注文が正常に登録されました！','order.status_update'=>'注文ステータスの更新','payment.success'=>'支払いが完了しました！'],
        ];
        $language_changed = [
            'de' => 'Sprache erfolgreich geändert.', 'es' => 'Idioma cambiado correctamente.',
            'fr' => 'Langue modifiée avec succès.', 'it' => 'Lingua modificata con successo.',
            'pt' => 'Idioma alterado com sucesso.', 'tr' => 'Dil başarıyla değiştirildi.',
            'ar' => 'تم تغيير اللغة بنجاح.', 'ru' => 'Язык успешно изменён.',
            'zh' => '语言已成功更改。', 'ja' => '言語を変更しました.',
        ];
        foreach ($language_changed as $code => $text) $common[$code]['language.changed'] = $text;
        $menu_translations = [
            'de' => ['cat'=>'📦 Kategorien','new'=>'⭐ Neue Produkte','search'=>'🔍 Suche','cart'=>'🛒 Warenkorb','orders'=>'📦 Meine Bestellungen','wishlist'=>'❤️ Wunschliste','settings'=>'🌐 Einstellungen / Sprache','contact'=>'📞 Kontakt','help'=>'ℹ️ Hilfe','webapp'=>'📱 Store-Mini-App'],
            'es' => ['cat'=>'📦 Categorías','new'=>'⭐ Productos nuevos','search'=>'🔍 Buscar','cart'=>'🛒 Carrito','orders'=>'📦 Mis pedidos','wishlist'=>'❤️ Favoritos','settings'=>'🌐 Ajustes / Idioma','contact'=>'📞 Contacto','help'=>'ℹ️ Ayuda','webapp'=>'📱 Mini App de tienda'],
            'fr' => ['cat'=>'📦 Catégories','new'=>'⭐ Nouveaux produits','search'=>'🔍 Rechercher','cart'=>'🛒 Panier','orders'=>'📦 Mes commandes','wishlist'=>'❤️ Favoris','settings'=>'🌐 Réglages / Langue','contact'=>'📞 Contact','help'=>'ℹ️ Aide','webapp'=>'📱 Mini-app boutique'],
            'it' => ['cat'=>'📦 Categorie','new'=>'⭐ Nuovi prodotti','search'=>'🔍 Cerca','cart'=>'🛒 Carrello','orders'=>'📦 I miei ordini','wishlist'=>'❤️ Preferiti','settings'=>'🌐 Impostazioni / Lingua','contact'=>'📞 Contatti','help'=>'ℹ️ Guida','webapp'=>'📱 Mini App negozio'],
            'pt' => ['cat'=>'📦 Categorias','new'=>'⭐ Produtos novos','search'=>'🔍 Pesquisar','cart'=>'🛒 Carrinho','orders'=>'📦 Meus pedidos','wishlist'=>'❤️ Favoritos','settings'=>'🌐 Configurações / Idioma','contact'=>'📞 Contacto','help'=>'ℹ️ Ajuda','webapp'=>'📱 Mini App da loja'],
            'tr' => ['cat'=>'📦 Kategoriler','new'=>'⭐ Yeni ürünler','search'=>'🔍 Ürün ara','cart'=>'🛒 Sepet','orders'=>'📦 Siparişlerim','wishlist'=>'❤️ Favoriler','settings'=>'🌐 Ayarlar / Dil','contact'=>'📞 İletişim','help'=>'ℹ️ Yardım','webapp'=>'📱 Mağaza Mini Uygulaması'],
            'ar' => ['cat'=>'📦 الفئات','new'=>'⭐ أحدث المنتجات','search'=>'🔍 بحث','cart'=>'🛒 السلة','orders'=>'📦 طلباتي','wishlist'=>'❤️ المفضلة','settings'=>'🌐 الإعدادات / اللغة','contact'=>'📞 اتصل بنا','help'=>'ℹ️ المساعدة','webapp'=>'📱 تطبيق المتجر المصغر'],
            'ru' => ['cat'=>'📦 Категории','new'=>'⭐ Новинки','search'=>'🔍 Поиск','cart'=>'🛒 Корзина','orders'=>'📦 Мои заказы','wishlist'=>'❤️ Избранное','settings'=>'🌐 Настройки / Язык','contact'=>'📞 Контакты','help'=>'ℹ️ Помощь','webapp'=>'📱 Мини-приложение магазина'],
            'zh' => ['cat'=>'📦 商品分类','new'=>'⭐ 最新商品','search'=>'🔍 搜索商品','cart'=>'🛒 购物车','orders'=>'📦 我的订单','wishlist'=>'❤️ 收藏夹','settings'=>'🌐 设置 / 语言','contact'=>'📞 联系我们','help'=>'ℹ️ 帮助','webapp'=>'📱 商店小程序'],
            'ja' => ['cat'=>'📦 カテゴリー','new'=>'⭐ 新着商品','search'=>'🔍 商品検索','cart'=>'🛒 カート','orders'=>'📦 注文履歴','wishlist'=>'❤️ お気に入り','settings'=>'🌐 設定 / 言語','contact'=>'📞 お問い合わせ','help'=>'ℹ️ ヘルプ','webapp'=>'📱 ショップミニアプリ'],
        ];
        foreach ($menu_translations as $code => $labels) {
            foreach ($labels as $key => $text) $common[$code]['menu.' . $key] = $text;
        }
        return isset($common[$language][$name]) ? $common[$language][$name] : null;
    }

    /** Return a stable, user-facing WooCommerce order status label. */
    public static function botOrderStatusText($status, $language = null) {
        $status = strtolower((string) $status);
        $labels = [
            'pending'    => ['در انتظار پرداخت', 'Pending payment'],
            'processing' => ['در حال پردازش', 'Processing'],
            'on-hold'    => ['در انتظار بررسی', 'On hold'],
            'completed'  => ['تکمیل شده', 'Completed'],
            'cancelled'  => ['لغو شده', 'Cancelled'],
            'refunded'   => ['مسترد شده', 'Refunded'],
            'failed'     => ['ناموفق', 'Failed'],
            'draft'      => ['پیش‌نویس', 'Draft'],
        ];
        if (isset($labels[$status])) {
            return self::botText('order.status.' . $status, $labels[$status][0], $labels[$status][1], $language);
        }
        return function_exists('wc_get_order_status_name')
            ? (string) wc_get_order_status_name($status)
            : ucfirst($status);
    }

    /**
     * Translate legacy runtime labels according to the Telegram user's language.
     *
     * Older bot code used WordPress __() directly, which follows the site's
     * locale rather than the language selected inside Telegram. Keep those
     * messages compatible while routing them through the bot language context.
     */
    public static function botTranslate($text, $domain = 'telenexa-commerce-for-telegram', $language = null) {
        $text = (string) $text;
        $language = $language === null ? self::getLanguage() : strtolower(substr((string) $language, 0, 2));

        if ($language === 'en') {
            return $text;
        }

        $translated = self::translateBotString('gettext.' . md5($domain . '|' . $text), $text, $language, 'TeleNexa Bot');
        if (is_string($translated) && $translated !== '' && $translated !== $text) {
            return $translated;
        }

        // Telegram users choose a language independently from the WordPress
        // administrator. Temporarily load the matching WordPress locale so
        // bundled .mo catalogs also translate legacy bot messages.
        $locales = [
            'fa' => 'fa_IR', 'en' => 'en_US', 'de' => 'de_DE', 'es' => 'es_ES',
            'fr' => 'fr_FR', 'it' => 'it_IT', 'pt' => 'pt_PT', 'tr' => 'tr_TR',
            'ar' => 'ar', 'ru' => 'ru_RU', 'zh' => 'zh_CN', 'ja' => 'ja',
        ];
        if (isset($locales[$language]) && function_exists('switch_to_locale') && function_exists('restore_current_locale')) {
            switch_to_locale($locales[$language]);
            $localized = __($text, $domain);
            restore_current_locale();
            if (is_string($localized) && $localized !== '' && $localized !== $text) return $localized;
        }

        return is_string($translated) && $translated !== '' ? $translated : $text;
    }

    public static function showLanguageSettings() {
        $current = self::getLanguage();
        $languages = self::supportedLanguages();
        if (!isset($languages[$current])) {
            $languages[$current] = strtoupper($current);
        }
        $language_buttons = [];
        foreach ($languages as $code => $label) {
            $language_buttons[] = [
                'text' => ($current === $code ? '✅ ' : '') . $label,
                'callback_data' => '/LANG' . $code,
            ];
        }
        $keyboard = array_chunk($language_buttons, 2);
        $keyboard[] = [self::btnHome()];

        $text = self::botText(
            'language.choose',
            '🌐 زبان ربات را انتخاب کنید:',
            '🌐 Choose your language:',
            $current
        );

        if (self::isCallback()) {
            self::editMessage(['text' => $text, 'keyboard' => $keyboard]);
        } else {
            self::sendMessage(['text' => $text, 'keyboard' => $keyboard]);
        }
    }
}
