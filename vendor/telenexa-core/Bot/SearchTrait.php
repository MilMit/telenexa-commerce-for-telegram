<?php
namespace TeleNexa\Bot;

/**
 * Professional Search Engine and Filtering for WooBot.
 * Supports search by title, SKU, category, price range, stock status, and multi-criteria sorting.
 */
trait SearchTrait {

    /**
     * Show interactive search hub with active filters and sorting options.
     */
    public static function showSearchHub() {
        self::setLastRequest('search');
        $lang = self::getLanguage();

        $saved_sort = self::getSession('search_sort') ?: (woogram_option('search_default_sort') ?: 'date');
        $only_stock = self::getSession('search_only_stock');
        if ($only_stock === null) {
            $only_stock = woogram_option('only_display_instock_products') ? 1 : 0;
        }

        $sort_labels = [
            'date'       => self::botText('search.sort.newest', '⭐ جدیدترین', '⭐ Newest', $lang),
            'popularity' => self::botText('search.sort.popular', '🔥 پرفروش‌ترین', '🔥 Popular', $lang),
            'price_asc'  => self::botText('search.sort.price_low', '💸 ارزان‌ترین', '💸 Price: Low to High', $lang),
            'price_desc' => self::botText('search.sort.price_high', '💎 گران‌ترین', '💎 Price: High to Low', $lang),
            'on_sale'    => self::botText('search.sort.sale', '🏷️ دارای تخفیف', '🏷️ On Sale', $lang),
        ];
        if (!isset($sort_labels[$saved_sort])) {
            $saved_sort = 'date';
        }

        $stock_status_label = $only_stock
            ? self::botText('search.stock.active', '🟢 فقط کالاهای موجود (فعال)', '🟢 In Stock Only (Active)', $lang)
            : self::botText('search.stock.all', '⚪ همه کالاها (موجود و ناموجود)', '⚪ All Products (Stock & Out)', $lang);

        $text = "<b>" . self::botText('search.title', '🔍 جستجوی پیشرفته محصولات', '🔍 Advanced Product Search', $lang) . "</b>\n\n"
            . self::botText(
                'search.instructions',
                "نام کالا، کد محصول (SKU) یا کلمه کلیدی مورد نظرتان را ارسال کنید.\n\n📌 مرتب‌سازی فعلی: <b>%1\$s</b>\n📦 فیلتر موجودی: <b>%2\$s</b>",
                "Send any product name, SKU or keyword to start searching.\n\n📌 Current Sort: <b>%1\$s</b>\n📦 Stock Filter: <b>%2\$s</b>",
                $lang
            );
        $text = sprintf($text, esc_html($sort_labels[$saved_sort]), esc_html($stock_status_label));

        $keyboard = [
            [
                ['text' => self::botText('search.toggle_stock', '🔄 فیلتر فقط کالاهای موجود', '🔄 Toggle Stock Filter', $lang), 'callback_data' => '/TOGGLE_STOCK'],
            ],
            [
                ['text' => self::botText('search.button.newest', '⭐ جدیدترین', '⭐ Newest', $lang), 'callback_data' => '/SETSORT_date'],
                ['text' => self::botText('search.button.popular', '🔥 پرفروش‌ترین', '🔥 Popular', $lang), 'callback_data' => '/SETSORT_popularity'],
            ],
            [
                ['text' => self::botText('search.button.cheapest', '💸 ارزان‌ترین', '💸 Cheapest', $lang), 'callback_data' => '/SETSORT_price_asc'],
                ['text' => self::botText('search.button.expensive', '💎 گران‌ترین', '💎 Expensive', $lang), 'callback_data' => '/SETSORT_price_desc'],
                ['text' => self::botText('search.button.sale', '🏷️ تخفیف‌دار', '🏷️ On Sale', $lang), 'callback_data' => '/SETSORT_on_sale'],
            ],
            [self::btnHome()]
        ];

        if (self::isCallback()) {
            self::editMessage(['text' => $text, 'keyboard' => $keyboard]);
        } else {
            self::sendMessage(['text' => $text, 'keyboard' => $keyboard]);
        }
    }

    /**
     * Perform the advanced search and display formatted results.
     */
    public static function doAdvancedSearch($search_term, $page = 1, $cat_id = 0) {
        $search_term = sanitize_text_field($search_term);
        self::setLastSearch($search_term);
        $page = max(1, absint($page));
        $lang = self::getLanguage();

        $saved_sort = self::getSession('search_sort') ?: (woogram_option('search_default_sort') ?: 'date');
        $only_stock = self::getSession('search_only_stock');
        if ($only_stock === null) {
            $only_stock = woogram_option('only_display_instock_products') ? 1 : 0;
        }

        $min_price = self::getSession('search_min_price');
        $max_price = self::getSession('search_max_price');

        $query_params = [
            's'            => $search_term,
            'cat_id'       => $cat_id,
            'only_instock' => (bool)$only_stock,
            'orderby'      => $saved_sort,
            'min_price'    => $min_price,
            'max_price'    => $max_price,
            'page'         => $page,
            'per_page'     => self::$product_per_page ?: 5,
        ];

        $results = self::queryProducts($query_params);
        $products = $results['products'];
        $total = $results['total'];
        $max_pages = $results['max_pages'];

        if (empty($products)) {
            $no_result_text = "🔍 " . sprintf(
                self::botText('search.no_results', 'هیچ محصولی برای جستجوی «%s» یافت نشد.', 'No products found for "%s".', $lang),
                esc_html($search_term)
            ) . "\n\n" . self::botText('search.try_again', 'لطفاً با کلمات دیگر مجدداً جستجو کنید.', 'Try searching with different keywords.', $lang);

            $keyboard = [
                [['text' => self::botText('search.new_search', '🔍 جستجوی جدید', '🔍 New Search', $lang), 'callback_data' => '/search']],
                [self::btnHome()]
            ];

            if (self::isCallback()) {
                self::editMessage(['text' => $no_result_text, 'keyboard' => $keyboard]);
            } else {
                self::sendMessage(['text' => $no_result_text, 'keyboard' => $keyboard]);
            }
            return;
        }

        // Header message
        $header = "🔍 " . sprintf(
            self::botText('search.results', 'نتایج جستجو برای: <b>%1$s</b> (%2$d محصول)', 'Search results for: <b>%1$s</b> (%2$d products)', $lang),
            esc_html($search_term),
            $total
        ) . ($max_pages > 1 ? sprintf(self::botText('pagination.page_of', ' - صفحه %1$d از %2$d', ' - Page %1$d of %2$d', $lang), $page, $max_pages) : '');

        self::sendMessage(['text' => $header]);

        // Send individual product cards
        foreach ($products as $prod) {
            if (!$prod instanceof \WC_Product) {
                $prod = wc_get_product($prod);
            }
            if (!$prod || !$prod->is_visible()) continue;
            self::renderProductCard($prod);
        }

        // Pagination controls
        if ($max_pages > 1) {
            $paging_keyboard = [];
            $row = [];
            if ($page > 1) {
                $prev = $page - 1;
                $row[] = ['text' => '◀ ' . self::botText('pagination.previous', 'قبلی', 'Previous', $lang), 'callback_data' => "/SEARCHPAGE{$prev}"];
            }
            $row[] = ['text' => "📄 {$page} / {$max_pages}", 'callback_data' => '/noop'];
            if ($page < $max_pages) {
                $next = $page + 1;
                $row[] = ['text' => self::botText('pagination.next', 'بعدی', 'Next', $lang) . ' ▶', 'callback_data' => "/SEARCHPAGE{$next}"];
            }
            $paging_keyboard[] = $row;
            $paging_keyboard[] = [
                ['text' => self::botText('search.new_search', '🔍 جستجوی جدید', '🔍 New Search', $lang), 'callback_data' => '/search'],
                self::btnHome()
            ];

            self::sendMessage([
                'text' => sprintf(self::botText('pagination.page_of_plain', 'صفحه %1$d از %2$d', 'Page %1$d of %2$d', $lang), $page, $max_pages),
                'keyboard' => $paging_keyboard
            ]);
        }
    }

    /**
     * Render a modern, detailed product card with photo, SKU, stock status, discount badge, and action buttons.
     */
    public static function renderProductCard(\WC_Product $product) {
        $lang = self::getLanguage();
        $id = $product->get_id();
        $title = $product->get_name();
        $sku = $product->get_sku();
        $is_in_stock = $product->is_in_stock();
        $stock_qty = $product->get_stock_quantity();
        $is_on_sale = $product->is_on_sale();
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();

        // Calculate discount percentage
        $discount_badge = '';
        if ($is_on_sale && is_numeric($regular_price) && is_numeric($sale_price) && $regular_price > 0) {
            $discount_pct = round((($regular_price - $sale_price) / $regular_price) * 100);
            if ($discount_pct > 0) {
                $discount_badge = "\n🔥 <b>" . sprintf(self::botText('product.discount', '%d٪ تخفیف ویژه', '%d%% OFF', $lang), $discount_pct) . "</b>";
            }
        }

        // Stock status label
        if ($is_in_stock) {
            $stock_label = $stock_qty !== null && $stock_qty > 0
                ? "✅ " . sprintf(self::botText('product.stock_count', 'موجود در انبار (%d عدد)', 'In Stock (%d available)', $lang), $stock_qty)
                : "✅ " . self::botText('product.in_stock', 'موجود در انبار', 'In Stock', $lang);
        } else {
            $stock_label = "❌ " . self::botText('product.out_of_stock', 'ناموجود', 'Out of Stock', $lang);
        }

        // SKU line
        $sku_text = !empty($sku) ? "\n🏷️ SKU: <code>" . esc_html($sku) . "</code>" : "";

        // Price formatting
        $price_html = strip_tags($product->get_price_html());

        $caption = "<b>" . esc_html($title) . "</b>\n"
            . $sku_text . "\n"
            . "💰 " . self::botText('product.price', 'قیمت: ', 'Price: ', $lang) . "<b>" . esc_html($price_html) . "</b>"
            . $discount_badge . "\n"
            . "📦 " . $stock_label;

        // Rating
        if ($product->get_rating_count() > 0) {
            $rating = round($product->get_average_rating(), 1);
            $caption .= "\n⭐ " . sprintf(self::botText('product.rating', 'امتیاز: %1$s از ۵ (%2$d دیدگاه)', 'Rating: %1$s/5 (%2$d reviews)', $lang), $rating, $product->get_rating_count());
        }

        // Buttons
        $keyboard = [];
        $actions = [];

        // Order or View
        if ($is_in_stock) {
            $actions[] = ['text' => '🛒 ' . self::botText('product.order', 'سفارش', 'Order', $lang), 'callback_data' => "/ADD2CART{$id}"];
        } else {
            $actions[] = ['text' => '🔔 ' . self::botText('product.notify_stock', 'خبرم کن', 'Notify Me', $lang), 'callback_data' => "/NOTIFY_STOCK{$id}"];
        }

        // Wishlist
        $actions[] = self::btnWishlist($id);
        $keyboard[] = $actions;

        // Details / Gallery / Site link
        $extra_row = [];
        $extra_row[] = ['text' => '🔎 ' . self::botText('product.details', 'جزئیات کالا', 'Details', $lang), 'callback_data' => "/P{$id}"];

        if (!empty($product->get_gallery_image_ids())) {
            $extra_row[] = ['text' => '🖼️ ' . self::botText('product.gallery', 'تصاویر بیشتر', 'Gallery', $lang), 'callback_data' => "/GALLERY{$id}"];
        }

        $extra_row[] = ['text' => '🔗 ' . self::botText('product.website', 'مشاهده در سایت', 'Website', $lang), 'url' => self::productShortLink($id)];
        $keyboard[] = $extra_row;

        // Image
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';

        if (!empty($image_url)) {
            self::sendPhoto([
                'photo'    => $image_url,
                'caption'  => $caption,
                'keyboard' => $keyboard,
            ]);
        } else {
            self::sendMessage([
                'text'     => $caption,
                'keyboard' => $keyboard,
            ]);
        }
    }
}
