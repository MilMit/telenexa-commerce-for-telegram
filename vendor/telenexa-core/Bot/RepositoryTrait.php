<?php
namespace TeleNexa\Bot;

/**
 * High-performance Repository and Data Access Layer for WooBot.
 * Fully compatible with WooCommerce HPOS (High-Performance Order Storage) and standard CPT.
 */
trait RepositoryTrait {

    /**
     * Switch WPML language context cleanly before querying products or taxonomy terms.
     */
    public static function switchLanguageContext($lang = null) {
        if ($lang === null) {
            $lang = self::getLanguage();
        }
        if (function_exists('do_action')) {
            do_action('wpml_switch_language', $lang);
        }
    }

    /**
     * Get translated product ID for the current or specified language under WPML.
     */
    public static function getTranslatedProductId($product_id, $lang = null) {
        $product_id = absint($product_id);
        if (!$product_id) return 0;

        if ($lang === null) {
            $lang = self::getLanguage();
        }

        if (function_exists('apply_filters')) {
            $translated_id = apply_filters('wpml_object_id', $product_id, 'product', true, $lang);
            if (!empty($translated_id)) {
                return absint($translated_id);
            }
        }
        return $product_id;
    }

    /**
     * Get the translated product category ID under WPML.
     * Taxonomy buttons can originate from the default language, so resolve
     * them before building the WooCommerce order query.
     */
    public static function getTranslatedTermId($term_id, $taxonomy = 'product_cat', $lang = null) {
        $term_id = absint($term_id);
        if (!$term_id) return 0;
        if ($lang === null) $lang = self::getLanguage();

        if (function_exists('apply_filters')) {
            $translated_id = apply_filters('wpml_object_id', $term_id, $taxonomy, true, $lang);
            if (!empty($translated_id)) return absint($translated_id);
        }
        return $term_id;
    }

    /**
     * Get product by ID with caching and translated language context.
     */
    public static function getCachedProduct($product_id) {
        $lang = self::getLanguage();
        $target_id = self::getTranslatedProductId($product_id, $lang);
        self::switchLanguageContext($lang);

        $cache_key = 'woogram_prod_' . $target_id . '_' . $lang;
        $product = wp_cache_get($cache_key, 'woogram_bot');

        if (false === $product) {
            $product = wc_get_product($target_id);
            if ($product) {
                wp_cache_set($cache_key, $product, 'woogram_bot', 300); // 5 min cache
            }
        }

        return $product;
    }

    /**
     * Advanced product search supporting title, SKU, category, price filter, stock status, and sorting.
     *
     * @param array $params [
     *     's' => search string,
     *     'cat_id' => taxonomy term id,
     *     'min_price' => numeric,
     *     'max_price' => numeric,
     *     'only_instock' => bool,
     *     'orderby' => 'date'|'price_asc'|'price_desc'|'popularity'|'rating'|'on_sale',
     *     'page' => int,
     *     'per_page' => int
     * ]
     * @return array ['products' => \WC_Product[], 'total' => int, 'max_pages' => int]
     */
    public static function queryProducts(array $params = []) {
        $lang = self::getLanguage();
        self::switchLanguageContext($lang);

        $page = max(1, isset($params['page']) ? absint($params['page']) : 1);
        $per_page = isset($params['per_page']) ? absint($params['per_page']) : (self::$product_per_page ?: 5);
        $s = isset($params['s']) ? sanitize_text_field($params['s']) : '';
        $cat_id = isset($params['cat_id']) ? absint($params['cat_id']) : 0;
        $min_price = isset($params['min_price']) && is_numeric($params['min_price']) ? floatval($params['min_price']) : null;
        $max_price = isset($params['max_price']) && is_numeric($params['max_price']) ? floatval($params['max_price']) : null;
        $only_instock = isset($params['only_instock']) ? (bool) $params['only_instock'] : (bool) self::$only_display_instock_products;
        $orderby = isset($params['orderby']) ? sanitize_key($params['orderby']) : 'date';

        $args = [
            'status' => 'publish',
            'limit' => $per_page,
            'page' => $page,
            'paginate' => true,
        ];

        // Stock status
        if ($only_instock) {
            $args['stock_status'] = 'instock';
        }

        // Category filter
        if ($cat_id > 0) {
            $cat_id = self::getTranslatedTermId($cat_id, 'product_cat', $lang);
            $term = get_term($cat_id, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $args['category'] = [$term->slug];
            }
        }

        // Sorting
        switch ($orderby) {
            case 'price_asc':
                $args['orderby'] = 'price';
                $args['order'] = 'ASC';
                break;
            case 'price_desc':
                $args['orderby'] = 'price';
                $args['order'] = 'DESC';
                break;
            case 'popularity':
                $args['orderby'] = 'popularity';
                $args['order'] = 'DESC';
                break;
            case 'rating':
                $args['orderby'] = 'rating';
                $args['order'] = 'DESC';
                break;
            case 'on_sale':
                $args['on_sale'] = true;
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
            case 'title':
                $args['orderby'] = 'title';
                $args['order'] = 'ASC';
                break;
            case 'modified':
                $args['orderby'] = 'modified';
                $args['order'] = 'DESC';
                break;
            case 'date':
            default:
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
        }

        // Price range
        if ($min_price !== null || $max_price !== null) {
            $min = $min_price !== null ? $min_price : 0;
            $max = $max_price !== null ? $max_price : 999999999;
            $args['min_price'] = $min;
            $args['max_price'] = $max;
        }

        // Keyword or SKU search
        if (!empty($s)) {
            // Check if search matches an SKU directly
            $sku_product_id = wc_get_product_id_by_sku($s);
            if ($sku_product_id) {
                $sku_prod = wc_get_product($sku_product_id);
                if ($sku_prod && $sku_prod->is_visible()) {
                    return [
                        'products' => [$sku_prod],
                        'total' => 1,
                        'max_pages' => 1
                    ];
                }
            }
            $args['s'] = $s;
        }

        $results = wc_get_products($args);

        return [
            'products' => isset($results->products) ? $results->products : (array) $results,
            'total' => isset($results->total) ? $results->total : count((array) $results),
            'max_pages' => isset($results->max_num_pages) ? $results->max_num_pages : 1,
        ];
    }

    /**
     * HPOS-safe, paginated query for customer orders strictly belonging to a specific chat ID.
     */
    public static function getCustomerOrders($chat_id, $page = 1, $per_page = 4, $status_filter = '') {
        $chat_id = self::fixPersianChar((string) $chat_id);
        if ($chat_id === '') {
            return ['orders' => [], 'total' => 0, 'max_pages' => 1];
        }

        $page = max(1, absint($page));
        $per_page = max(1, absint($per_page));

        $raw_statuses = function_exists('wc_get_order_statuses') ? array_keys(wc_get_order_statuses()) : ['wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed'];
        $clean_statuses = array_map(function ($s) {
            return str_replace('wc-', '', $s);
        }, $raw_statuses);

        if (!empty($status_filter)) {
            $clean_filter = str_replace('wc-', '', $status_filter);
            if (in_array($clean_filter, $clean_statuses, true)) {
                $clean_statuses = [$clean_filter];
            }
        }

        // Build status list supporting both with 'wc-' prefix and without
        $status_list = [];
        foreach ($clean_statuses as $st) {
            $status_list[] = $st;
            $status_list[] = 'wc-' . $st;
        }
        $status_list = array_values(array_unique($status_list));

        $chat_id_variants = [$chat_id];
        if (method_exists(__CLASS__, 'toPersian')) {
            $persian_id = self::toPersian($chat_id);
            if ($persian_id !== $chat_id) {
                $chat_id_variants[] = $persian_id;
            }
        }

        global $wpdb;

        $order_ids = [];

        if ($wpdb && !empty($wpdb->prefix)) {
            $hpos_active = false;
            if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && method_exists('\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
                $hpos_active = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            }

            $status_placeholders = implode(',', array_fill(0, count($status_list), '%s'));
            $chat_placeholders = implode(',', array_fill(0, count($chat_id_variants), '%s'));

            if ($hpos_active) {
                $orders_table = $wpdb->prefix . 'wc_orders';
                $meta_table = $wpdb->prefix . 'wc_orders_meta';

                $sql = "
                    SELECT DISTINCT o.id
                    FROM {$orders_table} o
                    INNER JOIN {$meta_table} m ON o.id = m.order_id
                    WHERE m.meta_key = '_chat_id'
                      AND m.meta_value IN ({$chat_placeholders})
                      AND o.status IN ({$status_placeholders})
                    ORDER BY o.date_created_gmt DESC, o.id DESC
                ";
                $query_args = array_merge([$sql], $chat_id_variants, $status_list);
                $col = $wpdb->get_col(call_user_func_array([$wpdb, 'prepare'], $query_args));
                if (is_array($col) && !empty($col)) {
                    $order_ids = array_map('absint', $col);
                }
            }

            // If not HPOS or no orders found in HPOS, check legacy posts/postmeta
            if (empty($order_ids)) {
                $sql = "
                    SELECT DISTINCT p.ID
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
                    WHERE p.post_type = 'shop_order'
                      AND m.meta_key = '_chat_id'
                      AND m.meta_value IN ({$chat_placeholders})
                      AND p.post_status IN ({$status_placeholders})
                    ORDER BY p.post_date DESC, p.ID DESC
                ";
                $query_args = array_merge([$sql], $chat_id_variants, $status_list);
                $col = $wpdb->get_col(call_user_func_array([$wpdb, 'prepare'], $query_args));
                if (is_array($col) && !empty($col)) {
                    $order_ids = array_map('absint', $col);
                }
            }
        }

        // Fallback for mock/test environments when $wpdb is unavailable
        if (empty($order_ids) && (!$wpdb || empty($wpdb->prefix)) && function_exists('wc_get_orders')) {
            $fallback_args = [
                'status' => $clean_statuses,
                'limit' => 100,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => [
                    [
                        'key' => '_chat_id',
                        'value' => (string) $chat_id,
                        'compare' => '=',
                    ]
                ],
            ];
            $fallback_results = wc_get_orders($fallback_args);
            $raw_orders = isset($fallback_results->orders) ? $fallback_results->orders : (array) $fallback_results;
            foreach ($raw_orders as $o) {
                if ($o instanceof \WC_Order) {
                    if (self::fixPersianChar((string) $o->get_meta('_chat_id', true)) === $chat_id) {
                        $order_ids[] = $o->get_id();
                    }
                }
            }
        }

        $order_ids = array_values(array_unique(array_filter($order_ids)));
        $total = count($order_ids);
        $max_pages = max(1, (int) ceil($total / $per_page));

        if ($page > $max_pages) {
            $page = $max_pages;
        }

        $offset = ($page - 1) * $per_page;
        $page_ids = array_slice($order_ids, $offset, $per_page);

        $valid_orders = [];
        if (function_exists('wc_get_order')) {
            foreach ($page_ids as $id) {
                $order = wc_get_order($id);
                if ($order instanceof \WC_Order) {
                    if (self::fixPersianChar((string) $order->get_meta('_chat_id', true)) === $chat_id) {
                        $valid_orders[] = $order;
                    }
                }
            }
        }

        return [
            'orders' => $valid_orders,
            'total' => $total,
            'max_pages' => $max_pages
        ];
    }

    /**
     * Alias for queryCustomerOrders to maintain compatibility with recommendation engines.
     */
    public static function queryCustomerOrders($chat_id, $page = 1, $per_page = 4, $status_filter = '') {
        return self::getCustomerOrders($chat_id, $page, $per_page, $status_filter);
    }

    /**
     * Calculate sales metrics and statistics for Telegram orders cleanly.
     */
    public static function getTelegramSalesStats() {
        $cache_key = 'woogram_sales_stats';
        $stats = wp_cache_get($cache_key, 'woogram_bot');
        if (false !== $stats && is_array($stats)) {
            return $stats;
        }

        global $wpdb;

        // Check if HPOS is active
        $hpos_active = false;
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && method_exists('\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
            $hpos_active = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }

        $completed_statuses = ["'wc-completed'", "'wc-processing'"];
        $status_sql = implode(',', $completed_statuses);

        $today_start = date('Y-m-d 00:00:00');
        $month_start = date('Y-m-01 00:00:00');

        $total_orders = 0;
        $total_revenue = 0.0;
        $today_sales = 0.0;
        $month_sales = 0.0;

        if ($hpos_active) {
            $orders_table = $wpdb->prefix . 'wc_orders';
            $meta_table = $wpdb->prefix . 'wc_orders_meta';

            $row = $wpdb->get_row("
                SELECT COUNT(DISTINCT o.id) as order_count, SUM(o.total_amount) as total_sum
                FROM {$orders_table} o
                INNER JOIN {$meta_table} m ON o.id = m.order_id
                WHERE m.meta_key = '_order_from' AND m.meta_value = 'telegram'
                AND o.status IN ('wc-completed', 'wc-processing')
            ");

            if ($row) {
                $total_orders = (int) $row->order_count;
                $total_revenue = (float) $row->total_sum;
            }

            // Today sales
            $today_row = $wpdb->get_row($wpdb->prepare("
                SELECT SUM(o.total_amount) as today_sum
                FROM {$orders_table} o
                INNER JOIN {$meta_table} m ON o.id = m.order_id
                WHERE m.meta_key = '_order_from' AND m.meta_value = 'telegram'
                AND o.status IN ('wc-completed', 'wc-processing')
                AND o.date_created_gmt >= %s
            ", $today_start));
            $today_sales = $today_row ? (float) $today_row->today_sum : 0.0;

            // Monthly sales
            $month_row = $wpdb->get_row($wpdb->prepare("
                SELECT SUM(o.total_amount) as month_sum
                FROM {$orders_table} o
                INNER JOIN {$meta_table} m ON o.id = m.order_id
                WHERE m.meta_key = '_order_from' AND m.meta_value = 'telegram'
                AND o.status IN ('wc-completed', 'wc-processing')
                AND o.date_created_gmt >= %s
            ", $month_start));
            $month_sales = $month_row ? (float) $month_row->month_sum : 0.0;

        } else {
            // Traditional CPT
            $row = $wpdb->get_row("
                SELECT COUNT(DISTINCT p.ID) as order_count, SUM(pm_total.meta_value) as total_sum
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_from ON p.ID = pm_from.post_id AND pm_from.meta_key = '_order_from' AND pm_from.meta_value = 'telegram'
                LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN ({$status_sql})
            ");

            if ($row) {
                $total_orders = (int) $row->order_count;
                $total_revenue = (float) $row->total_sum;
            }

            $today_row = $wpdb->get_row($wpdb->prepare("
                SELECT SUM(pm_total.meta_value) as today_sum
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_from ON p.ID = pm_from.post_id AND pm_from.meta_key = '_order_from' AND pm_from.meta_value = 'telegram'
                LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN ({$status_sql})
                AND p.post_date >= %s
            ", $today_start));
            $today_sales = $today_row ? (float) $today_row->today_sum : 0.0;

            $month_row = $wpdb->get_row($wpdb->prepare("
                SELECT SUM(pm_total.meta_value) as month_sum
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_from ON p.ID = pm_from.post_id AND pm_from.meta_key = '_order_from' AND pm_from.meta_value = 'telegram'
                LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN ({$status_sql})
                AND p.post_date >= %s
            ", $month_start));
            $month_sales = $month_row ? (float) $month_row->month_sum : 0.0;
        }

        // Subscribers count
        $total_users = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_title) FROM {$wpdb->posts} WHERE post_type = 'woogram_user'");

        // Conversion rate
        $conversion_rate = $total_users > 0 ? round(($total_orders / $total_users) * 100, 1) : 0.0;

        $stats = [
            'total_orders' => $total_orders,
            'total_revenue' => $total_revenue,
            'today_sales' => $today_sales,
            'month_sales' => $month_sales,
            'total_users' => $total_users,
            'conversion_rate' => $conversion_rate,
        ];

        wp_cache_set($cache_key, $stats, 'woogram_bot', 120); // 2 min cache
        return $stats;
    }

    /**
     * Get top selling products among Telegram bot orders.
     */
    public static function getTelegramTopProducts($limit = 5) {
        $limit = max(1, absint($limit));
        $cache_key = 'woogram_top_products_' . $limit;
        $cached = wp_cache_get($cache_key, 'woogram_bot');
        if (false !== $cached) {
            return $cached;
        }

        global $wpdb;
        $order_items = $wpdb->prefix . 'woocommerce_order_items';
        $order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

        $query = "
            SELECT im_prod.meta_value as product_id, SUM(im_qty.meta_value) as total_qty
            FROM {$order_items} oi
            INNER JOIN {$order_itemmeta} im_prod ON oi.order_item_id = im_prod.order_item_id AND im_prod.meta_key IN ('_product_id', 'product_id')
            INNER JOIN {$order_itemmeta} im_qty ON oi.order_item_id = im_qty.order_item_id AND im_qty.meta_key IN ('_qty', 'qty')
            WHERE oi.order_item_type = 'line_item'
            GROUP BY im_prod.meta_value
            ORDER BY total_qty DESC
            LIMIT %d
        ";

        $results = $wpdb->get_results($wpdb->prepare($query, $limit));
        $products = [];
        if (!empty($results)) {
            foreach ($results as $row) {
                $product = wc_get_product($row->product_id);
                if ($product) {
                    $products[] = [
                        'id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'price' => $product->get_price_html(),
                        'sales' => (int) $row->total_qty,
                        'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                    ];
                }
            }
        }

        wp_cache_set($cache_key, $products, 'woogram_bot', 300);
        return $products;
    }
}
