<?php
namespace TeleNexa\Bot;

/** Catalog behavior for WooBot. */
trait CatalogTrait {
    public static function getProducts($part,$part_id=0,$page_number=1,$search_key='')  {
        $search_key = sanitize_text_field($search_key);
        self::setLastSearch($search_key);
        $sub_cat_load = false;
        if($part=='C') {
            $sub_cat_load = self::getCats($part_id);
        }
        $args = array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => self::$product_per_page, 'paged' => $page_number, 'meta_query' => [], );
        if(self::$only_display_instock_products) {
            $args['meta_query'][] = [ 'key' => '_stock_status', 'value' => 'instock' ];
        }
        if($part=='SEARCH') {
            $args['s'] = $search_key;
            $args['suppress_filters'] = false;
            if ( function_exists('WC') && isset(WC()->version) && version_compare( WC()->version, '2.7.0', '<' ) )  {
                $args['meta_query'] = array( array( 'key' => '_visibility', 'value' => array( 'search', 'visible' ), 'compare' => 'IN' ), );
            } else {
                $product_visibility_term_ids = wc_get_product_visibility_term_ids();
                $args['tax_query'][] = array( 'taxonomy' => 'product_visibility', 'field' => 'term_taxonomy_id', 'terms' => $product_visibility_term_ids['exclude-from-search'], 'operator' => 'NOT IN', );
            }
        }
        if ($part === 'PS') {
            $newest_order = woogram_option('newest_orderby');
            if ($newest_order === 'modified') {
                $args['orderby'] = 'modified';
                $args['order'] = 'DESC';
            } elseif ($newest_order === 'price') {
                $args['meta_key'] = '_price';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'ASC';
            } elseif ($newest_order === 'popularity') {
                $args['meta_key'] = 'total_sales';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
            }
        }
        $message = '';
        $alert_paging = $page_number>1? sprintf(self::botTranslate(' - Page %d', 'telenexa-commerce-for-telegram'), $page_number):'';
        switch($part) {
            case 'SEARCH': global $woocommerce;
            $ordering_args = $woocommerce->query->get_catalog_ordering_args( 'title', 'asc' );
            $args['orderby'] = $ordering_args['orderby'];
            $args['order'] = $ordering_args['order'];
            $message = "🔍 " . sprintf(self::botTranslate('Search: %s', 'telenexa-commerce-for-telegram'), $search_key);
            $keyboard = array();
            $keyboard[0][0] = self::btnHome();
            self::sendMessage( [ 'text' => $message,'keyboard'=>$keyboard]);
            self::answerCallback($message.$alert_paging);
            break;
            case 'PS':
            if (empty($args['orderby'])) {
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
            }
            $message = self::botTranslate('Newest Products', 'telenexa-commerce-for-telegram');
            self::answerCallback($message.$alert_paging);
            break;
            case 'C': $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            if($part_id) {
                $category = self::getProductCategoryById($part_id);
                if (!is_array($category) || empty($category['slug'])) {
                    self::answerCallback(self::botTranslate('This category is no longer available.', 'telenexa-commerce-for-telegram'));
                    self::sendMessage(['text' => self::botTranslate('This category is no longer available.', 'telenexa-commerce-for-telegram'), 'keyboard' => [[self::btnHome()]]]);
                    return false;
                }
                $args['tax_query'] = array();
                $args['tax_query'] = array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $category['slug'], 'include_children' => false, ),);
                $message = sprintf(self::botTranslate('Category: %s', 'telenexa-commerce-for-telegram'), $category['name']);
            }  else  {
                $message = self::botTranslate('All Products', 'telenexa-commerce-for-telegram');
            }
            self::answerCallback($message.$alert_paging);
            if(self::$hide_products_edge_subcategory && $sub_cat_load) return true;
            break;
        }
        $sort = 'date';
        if ($part === 'SEARCH') {
            $sort = 'title';
        } elseif ($part === 'PS') {
            $newest_order = woogram_option('newest_orderby');
            $sort = in_array($newest_order, ['modified', 'price', 'popularity'], true) ? $newest_order : 'date';
        }
        $catalog_result = self::queryProducts([
            's'            => $part === 'SEARCH' ? $search_key : '',
            'cat_id'       => $part === 'C' ? absint($part_id) : 0,
            'only_instock' => (bool) self::$only_display_instock_products,
            'orderby'      => $sort === 'price' ? 'price_asc' : $sort,
            'page'         => max(1, (int) $page_number),
            'per_page'     => max(1, (int) self::$product_per_page),
        ]);
        $loop = new \TeleNexa\Repository\ProductQueryAdapter(
            $catalog_result['products'],
            $catalog_result['total'],
            $catalog_result['max_pages']
        );
        if ( $loop->have_posts() )  {
            if(self::$product_list_type == 'product_per_key') {
                self::productPerKey($message, $part, $part_id, $loop, $page_number, $search_key);
            }  elseif(self::$product_list_type == 'product_per_line') {
                self::productPerLine($message, $part, $part_id, $loop, $page_number, $search_key);
            }  elseif(self::$product_list_type == 'product_per_post') {
                self::productPerPost($message, $part, $part_id, $loop, $page_number, $search_key);
            }
        }  else  {
            if(!$sub_cat_load) self::sendMessage(['text'=>self::botTranslate('No products found.', 'telenexa-commerce-for-telegram')]);
        }
        wp_reset_postdata();
    }
    public static function productPerLine($message, $part, $part_id, &$loop, $page_number, $search_key='')  {
        $keyboard = array();
        $list = array();
        $p=0;
        while ( $loop->have_posts() ) : $loop->the_post();
        global $product;
        $id = get_the_ID();
        $title = get_the_title();
        $text = get_the_excerpt();
        $price = self::price($product->get_price());
        $list[] = "▫ $title - " . sprintf(self::botTranslate('Price: %s', 'telenexa-commerce-for-telegram'), $price) . " > /P{$id}";
        endwhile;
        $nextpage = intval($page_number) + 1;
        $previouspage = intval($page_number) - 1;
        if($loop->max_num_pages>1) {
            if( $nextpage <= $loop->max_num_pages ) {
                $keyboard[$p][0] = ['text' => '◀', 'callback_data' => "/{$part}{$part_id}P{$nextpage}"];
            }  else  {
                $keyboard[$p][0] = ['text' => sprintf(self::botTranslate('Page %1$d of %2$d', 'telenexa-commerce-for-telegram'), $loop->max_num_pages, $loop->max_num_pages), 'callback_data' => '*'];
            }
            if($previouspage >= 1 ) {
                $keyboard[$p][1] = ['text' => '▶', 'callback_data' => "/{$part}{$part_id}P{$previouspage}"];
            }  else  {
                $keyboard[$p][1] = ['text' => sprintf(self::botTranslate('Page 1 of %d', 'telenexa-commerce-for-telegram'), $loop->max_num_pages), 'callback_data' => '*'];
            }
        }
        if($part==='PS') {
            $keyboard[++$p][0] = self::btnHome();
        }  elseif($part==='C')  {
            $keyboard[++$p][0] = self::btnBack(self::$pages['cat']['alias']);
        }
        $paging_title = $loop->max_num_pages>1 ? sprintf(self::botTranslate(' - Page %1$d of %2$d', 'telenexa-commerce-for-telegram'), $page_number, $loop->max_num_pages) . "\n\n" . implode("\n",$list) : "";
        if(self::isCallback()) {
            self::editMessage( [ 'text' => $message . $paging_title,'keyboard'=>$keyboard]);
        }  else  {
            self::sendMessage( [ 'text' => $message . $paging_title,'keyboard'=>$keyboard]);
        }
    }
    public static function productPerKey($message, $part, $part_id, &$loop, $page_number, $search_key='')  {
        $keyboard = array();
        $p = 0;
        while ( $loop->have_posts() ) : $loop->the_post();
        global $product;
        $id = get_the_ID();
        $title = get_the_title();
        $text = get_the_excerpt();
        $price = self::price($product->get_price());
        $keyboard[$p] = array();
        $keyboard[$p][0] = ['text' => "▫ $title - " . sprintf(self::botTranslate('Price: %s', 'telenexa-commerce-for-telegram'), $price), 'callback_data' => "/P{$id}"];
        $p++;
        endwhile;
        $nextpage = intval($page_number) + 1;
        $previouspage = intval($page_number) - 1;
        if($loop->max_num_pages>1) {
            if( $nextpage <= $loop->max_num_pages ) {
                $keyboard[$p][0] = ['text' => '◀', 'callback_data' => "/{$part}{$part_id}P{$nextpage}"];
            }  else  {
                $keyboard[$p][0] = ['text' => sprintf(self::botTranslate('Page %1$d of %2$d', 'telenexa-commerce-for-telegram'), $loop->max_num_pages, $loop->max_num_pages), 'callback_data' => '*'];
            }
            if($previouspage >= 1 ) {
                $keyboard[$p][1] = ['text' => '▶', 'callback_data' => "/{$part}{$part_id}P{$previouspage}"];
            }  else  {
                $keyboard[$p][1] = ['text' => sprintf(self::botTranslate('Page 1 of %d', 'telenexa-commerce-for-telegram'), $loop->max_num_pages), 'callback_data' => '*'];
            }
        }
        if($part==='PS') {
            $keyboard[++$p][0] = self::btnHome();
        }  elseif($part==='C')  {
            $keyboard[++$p][0] = self::btnBack(self::$pages['cat']['alias']);
        }
        $paging_title = $loop->max_num_pages>1 ? sprintf(self::botTranslate(' - Page %1$d of %2$d', 'telenexa-commerce-for-telegram'), $page_number, $loop->max_num_pages) : "";
        if(self::isCallback()) {
            self::editMessage( [ 'text' => $message . $paging_title,'keyboard'=>$keyboard]);
        }  else  {
            self::sendMessage( [ 'text' => $message . $paging_title,'keyboard'=>$keyboard]);
        }
    }
    public static function productPerPost($message, $part, $part_id, &$loop, $page_number, $search_key='')  {
        while ( $loop->have_posts() ) : $loop->the_post();
        global $product;
        $id = get_the_ID();
        self::getProduct('product', $id);
        endwhile;
        $keyboard = array();
        $p = 0;
        $nextpage = intval($page_number) + 1;
        $previouspage = intval($page_number) - 1;
        $back = ($part==='PS' || $part==='SEARCH') ? self::btnHome() : self::btnBack(self::$pages['cat']['alias']);
        $is_back = false;
        if($loop->max_num_pages>1) {
            if( $nextpage <= $loop->max_num_pages ) {
                $keyboard[$p][0] = ['text' => '◀', 'callback_data' => "/{$part}{$part_id}P{$nextpage}"];
            }  else  {
                $keyboard[$p][0] = $back;
                $is_back = true;
            }
            if($previouspage >= 1 ) {
                $keyboard[$p][1] = ['text' => '▶', 'callback_data' => "/{$part}{$part_id}P{$previouspage}"];
            }  else  {
                $keyboard[$p][1] = $back;
                $is_back = true;
            }
        }  else  {
            $keyboard[$p][0] = $back;
            $is_back = true;
        }
        if(!$is_back) {
            $keyboard[++$p][0] = $back;
        }
        $paging_info = $loop->max_num_pages > 1 ? sprintf(self::botTranslate('Page %1$d of %2$d', 'telenexa-commerce-for-telegram'), $page_number, $loop->max_num_pages) : sprintf(self::botTranslate('Total products: %d', 'telenexa-commerce-for-telegram'), $loop->post_count);
        self::sendMessage( [ 'text' => $paging_info, 'keyboard' => $keyboard ] );
    }
    public static function getCats($cat_id = 0)  {
        $language = self::getLanguage();
        self::switchLanguageContext($language);
        $cat_id = self::getTranslatedTermId($cat_id, 'product_cat', $language);
        $args = array( 'taxonomy' => 'product_cat', 'orderby' => 'name', 'show_count' => self::$cats_show_count, 'hide_empty' => self::$cats_hide_empty, 'parent' => $cat_id, 'hierarchical' => 1, );
        $all_categories = get_categories( $args );
        if (is_wp_error($all_categories) || !is_array($all_categories) || empty($all_categories)) {
            $message = self::botTranslate('No product categories found.', 'telenexa-commerce-for-telegram');
            self::answerCallback($message);
            self::sendMessage(['text' => $message, 'keyboard' => [[self::btnHome()]]]);
            return false;
        }
        foreach($all_categories as $kcat=>$cat) {
            $term_id = isset($cat->term_id) ? (int) $cat->term_id : 0;
            $metafieldArray = get_option('taxonomy_'. $term_id, []);
            $woogram_hide_cat = is_array($metafieldArray) ? !empty($metafieldArray['woogram_hide_cat']) : false;
            if($woogram_hide_cat == 1) unset($all_categories[$kcat]);
        }
        if(self::$category_list_type == 'category_per_key_1col') {
            self::categoryPerKey($all_categories, 1, $cat_id);
        }  else if(self::$category_list_type == 'category_per_key_2col')  {
            self::categoryPerKey($all_categories,2, $cat_id);
        }  else if(self::$category_list_type == 'category_per_key_3col')  {
            self::categoryPerKey($all_categories,3, $cat_id);
        }  else if(self::$category_list_type == 'category_per_line')  {
            self::categoryPerLine($all_categories, $cat_id);
        }
        return true;
    }
    public static function categoryPerKey(&$cats,$cols=1, $cat_id=0) {
        $keyboard = array();
        $p = 0;
        $p2 = 0;
        foreach ($cats as $cat)  {
            $parent_id = isset($cat->parent) ? (int) $cat->parent : (int) ($cat->category_parent ?? 0);
            $count = isset($cat->count) ? (int) $cat->count : (int) ($cat->category_count ?? 0);
            if($parent_id == $cat_id)  {
                if(empty($keyboard[$p])) {
                    $keyboard[$p] = array();
                }
                $keyboard[$p][$p2%$cols] = ['text' => "📦  {$cat->name}".(self::$cats_show_count?" ({$count})":""), 'callback_data' => "/C{$cat->term_id}"];
                if( ($p2%$cols)== $cols-1 ) {
                    $p++;
                }
                $p2++;
            }
        }
        if(empty($cat_id)) {
            $callback_data = self::$pages['home']['alias'];
        }  else  {
            $category = self::getProductCategoryById($cat_id);
            if(!empty($category['parent']))$callback_data = "/C{$category['parent']}";
            else $callback_data = "/CS";
        }
        $keyboard[++$p][0] = self::btnBack($callback_data);
        $header_text = "<b>" . self::botTranslate('Product Categories', 'telenexa-commerce-for-telegram') . "</b>";
        if(self::isCallback()) {
            self::editMessage( ['text'=>$header_text,'keyboard'=>$keyboard]);
        }  else  {
            self::sendMessage( ['text'=>$header_text,'keyboard'=>$keyboard]);
        }
    }
    public static function categoryPerLine(&$cats, $cat_id) {
        $list = array();
        foreach ($cats as $cat)  {
            $parent_id = isset($cat->parent) ? (int) $cat->parent : (int) ($cat->category_parent ?? 0);
            $count = isset($cat->count) ? (int) $cat->count : (int) ($cat->category_count ?? 0);
            if($parent_id == $cat_id)  {
                $list[] = "📦  {$cat->name}".(self::$cats_show_count?" ({$count})":"")." /C{$cat->term_id}";
            }
        }
        $keyboard = array();
        $keyboard[0] = array();
        if(empty($cat_id)) {
            $callback_data = self::$pages['home']['alias'];
        }  else  {
            $category = self::getProductCategoryById($cat_id);
            if(!empty($category['parent']))$callback_data = "/C{$category['parent']}";
            else $callback_data = "/CS";
        }
        $keyboard[0][0] = self::btnBack($callback_data);
        $header_text = "<b>" . self::botTranslate('Product Categories:', 'telenexa-commerce-for-telegram') . "</b>\n\n" . implode("\n", $list);
        if(self::isCallback()) {
            self::editMessage( ['text'=>$header_text,'keyboard'=>$keyboard]);
        }  else  {
            self::sendMessage( ['text'=>$header_text,'keyboard'=>$keyboard]);
        }
    }
    public static function getProduct($part,$part_id=0, $qty=0, $attribute_key = 0,$variation_id_for_add2cart = 0)  {
        $product = self::apiGetProduct($part_id);
        if(!empty($product)) {
            $view_on_site = ['text' => self::botTranslate('🔗 View on Website', 'telenexa-commerce-for-telegram'), 'url' => self::productShortLink($product['id'])];
            $view_on_site_full_url_on_cart = ['text' => self::botTranslate('🔗 View on Website', 'telenexa-commerce-for-telegram'), 'url' => self::productShortLink($product['id'], true)];
            $delete_from_card = ['text' => self::botTranslate('❌ Remove', 'telenexa-commerce-for-telegram'), 'callback_data' => "/DELCART{$part_id}"];
            $keyboard = [];
            $R = 0;
            $C = 0;
            if(isset($product['type']) && $product['type'] == 'variable' ) {
                if($qty) {
                    $keyboard[$R] = array();
                    if(self::$show_button_view_on_site) $keyboard[$R][$C++] = $view_on_site_full_url_on_cart;
                    $keyboard[$R][$C++] = $delete_from_card;
                }  else  {
                    if(self::$show_button_order) {
                        $attributes_variation_true = array_filter(is_array($product['attributes'] ?? null) ? $product['attributes'] : [], function($value) {
                            return is_array($value) && !empty($value['variation']);
                        }
                        );
                        $attributes_variation_true = array_values($attributes_variation_true);
                        if(isset($product['variations']) && is_array($product['variations'])) {
                            foreach($product['variations'] as $variation) {
                                if(!empty($variation_id_for_add2cart) && $variation['id'] != $variation_id_for_add2cart ) {
                                    continue;
                                }
                                if (!self::isBotProductPurchasable($variation['id'])) {
                                    continue;
                                }
                                $keyboard[$R] = [];
                                $variation_title = $variation['variation_name'] ?? self::botTranslate('Variation', 'telenexa-commerce-for-telegram');
                                $variation_attributes = is_array($variation['attributes'] ?? null) ? $variation['attributes'] : [];
                                if(count($attributes_variation_true) !== count($variation_attributes)) {
                                    if(count($variation_attributes)==0 OR $attribute_key) {
                                        $variation_keyboard = [];
                                        if(!empty($attributes_variation_true ) && is_array($attributes_variation_true )) {
                                            foreach($attributes_variation_true as $atribute_k=>$atribute_v) {
                                                if($attribute_key && $attribute_key != $atribute_k) continue;
                                                foreach((array) ($attributes_variation_true[$atribute_k]['options'] ?? []) as $vkey=>$vvalue) {
                                                    $variation_keyboard[$vkey] = ['text'=>$vvalue,'callback_data'=>"/ADD2CART{$product['id']}V{$variation['id']}A{$attribute_key}O{$vkey}"];
                                                }
                                                break;
                                            }
                                        }
                                        if($attribute_key) {
                                            $text = sprintf(self::botTranslate('Please select %s:', 'telenexa-commerce-for-telegram'), $attributes_variation_true[$attribute_key]['name'] ?? self::botTranslate('an option', 'telenexa-commerce-for-telegram'));
                                            $R = 0;
                                            if(!empty($variation_keyboard) && is_array($variation_keyboard)) {
                                                foreach($variation_keyboard as $var_keyboard) {
                                                    $keyboard[$R][0] = $var_keyboard;
                                                    $R++;
                                                }
                                            }
                                            $keyboard[$R++][0] = self::btnHome();
                                            self::answerCallback($text);
                                            self::sendMessage(['text'=>$text,'keyboard'=>$keyboard ]);
                                            return true;
                                        }
                                        if(!empty($variation_keyboard) && is_array($variation_keyboard)) {
                                            foreach($variation_keyboard as $var_keyboard) {
                                                $keyboard[$R][0] = $var_keyboard;
                                                $R++;
                                            }
                                        }
                                    }  else  {
                                        if(!empty($variation['price'])) {
                                            $variation_price = self::price($variation['price']);
                                            $keyboard[$R][0] = ['text'=>sprintf(self::botTranslate('🛒 Order: %1$s (%2$s)', 'telenexa-commerce-for-telegram'), $variation_title, $variation_price),'callback_data'=>"/ADD2CART{$product['id']}V{$variation['id']}"];
                                            $R++;
                                        }
                                    }
                                }  else  {
                                    if(!empty($variation['price'])) {
                                        $variation_price = self::price($variation['price']);
                                        $keyboard[$R][0] = ['text' => sprintf(self::botTranslate('🛒 Order: %1$s (%2$s)', 'telenexa-commerce-for-telegram'), $variation_title, $variation_price), 'callback_data' => "/ADD2CART{$product['id']}V{$variation['id']}"];
                                        $R++;
                                    }
                                }
                            }
                        }
                    }
                    if(self::$show_button_view_on_site) $keyboard[$R][0] = $view_on_site;
                }
            }  else  {
                $keyboard[$R] = [];
                if($qty) {
                    if(self::$show_button_view_on_site) $keyboard[$R][$C++] = $view_on_site_full_url_on_cart;
                    $keyboard[$R][$C++] = $delete_from_card;
                }  else  {
                    if(self::$show_button_view_on_site) $keyboard[$R][$C++] = $view_on_site;
                    if(self::$show_button_order) $keyboard[$R][$C++] = ['text'=>self::botTranslate('🛒 Order', 'telenexa-commerce-for-telegram'),'callback_data'=>"/ADD2CART{$product['id']}"];
                }
            }
            if (!$qty && woogram_option('feature_wishlist') !== '0') {
                $keyboard[++$R][0] = self::btnWishlist($product['id']);
            }
            if(self::$product_single_type == 'product_by_photo')  {
                self::productByPhoto($product, $keyboard, $qty, $attribute_key);
            }  elseif(self::$product_single_type == 'product_by_url')  {
                self::productByUrl($product, $keyboard, $qty, $attribute_key);
            }  elseif(self::$product_single_type == 'product_by_text')  {
                self::productByText($product, $keyboard, $qty, $attribute_key);
            }
            return $product;
        }  else  {
            if($qty) {
                self::delCart($part_id);
            }
            $keyboard = [ [ self::btnHome(), ], ];
            self::sendMessage(['text'=>self::botTranslate('Product not found.', 'telenexa-commerce-for-telegram'),'keyboard'=>$keyboard]);
            return array();
        }
    }
    public static function productGetText($product, $qty=0)  {
        $id = $product['id'];
        $title = $product['name'];
        $short_description = isset($product['short_description'])?trim($product['short_description']):'';
        $description = isset($product['description'])?trim($product['description']):'';
        $link = $product['permalink'];
        $qty_text = $qty ? "\n📌 " . sprintf(self::botTranslate('%d item(s)', 'telenexa-commerce-for-telegram'), $qty) : "";
        $regular_price_text = (self::$display_price_discounted && $product['on_sale']) ? sprintf(self::botTranslate('❌ Regular Price: %s ❌', 'telenexa-commerce-for-telegram'), $product['regular_price_label']) . "\n\n" : "";
        $price_text = $regular_price_text . (self::$display_price ? "✅ {$product['price_label']}" : '') . $qty_text;

        $text_array = array();
        if(self::$show_cart_product_id) {
            $cart_variation = self::getSession('cart_variation');
            if(isset(self::$show_cart_product_id)) {
                $title_list = [];
                if(isset($cart_variation[self::$show_cart_product_id]) && is_array($cart_variation[self::$show_cart_product_id])) {
                    $title_list = array_filter($cart_variation[self::$show_cart_product_id],function($v) {
                        return !empty($v)?true:false;
                    }
                    );
                }
                if(!empty($title_list) && is_array($title_list)) $title .= " ".implode(', ',$title_list);
            }
        }
        if(!empty($title))$text_array[] = "<b>{$title}</b>";
        if (woogram_option('display_product_sku') == '1' && !empty($product['sku'])) {
            $text_array[] = "🏷 " . sprintf(self::botTranslate('SKU: %s', 'telenexa-commerce-for-telegram'), $product['sku']);
        }
        if (woogram_option('display_sale_badge') == '1' && !empty($product['on_sale']) && !empty($product['regular_price']) && (float) $product['regular_price'] > 0) {
            $discount = round((1 - ((float) $product['price'] / (float) $product['regular_price'])) * 100);
            if ($discount > 0) $text_array[] = "🔥 " . sprintf(self::botTranslate('%d%% off', 'telenexa-commerce-for-telegram'), $discount);
        }
        if(!empty($price_text))$text_array[] = $price_text;
        if(self::$display_product_excerpt === 1 && !empty($short_description) && !$qty) {
            $text_array[] = wp_trim_words($short_description, self::$display_product_excerpt_length);
        }  else if(self::$display_product_excerpt === 2 && !empty($description) && !$qty) {
            $text_array[] = wp_trim_words($description, self::$display_product_excerpt_length);
        }  else if(self::$display_product_excerpt === 3 && !empty($description) && !$qty)  {
            $text_array[] = $description;
        }
        if(self::$show_link_buy_on_site && !$qty) $text_array[] = self::botTranslate("Online Purchase 👇", 'telenexa-commerce-for-telegram') . "\n" . self::productShortLink($product['id']);
        if(self::$show_link_quick_buy && !$qty) $text_array[] = self::botTranslate("Quick Buy 👇", 'telenexa-commerce-for-telegram') . "\nhttps://t.me/".self::$username."?start=P{$product['id']}";
        $p_object = wc_get_product( $product['id'] );
        if(self::$display_showqty==2) {
            if($p_object && $p_object->is_in_stock()) $text_array[] = "🏷 " . self::botTranslate("In Stock", 'telenexa-commerce-for-telegram');
            else $text_array[] = "❌ " . self::botTranslate("Out of Stock", 'telenexa-commerce-for-telegram');
        }  else if(self::$display_showqty==1)  {
            $text_array[] = "🏷 " . sprintf(self::botTranslate("Stock quantity: %s", 'telenexa-commerce-for-telegram'), (int) self::fixPersianChar($product['stock_quantity'] ?? 0));
        }
        $text = implode("\n\n", $text_array);
        return $text;
    }
    public static function productByPhoto($product, $keyboard, $qty=0, $attribute_key=0)  {
        $id = $product['id'];
        $text = self::productGetText($product, $qty);
        $attachment_ids = get_post_thumbnail_id($id);
        if (!$attachment_ids && function_exists('wc_get_product')) {
            $woo_product = wc_get_product($id);
            if ($woo_product && method_exists($woo_product, 'get_image_id')) {
                $attachment_ids = $woo_product->get_image_id();
            }
            if (!$attachment_ids && $woo_product && method_exists($woo_product, 'get_parent_id')) {
                $attachment_ids = get_post_thumbnail_id($woo_product->get_parent_id());
            }
        }
        if (!$attachment_ids && !empty($product['parent_id'])) {
            $attachment_ids = get_post_thumbnail_id($product['parent_id']);
        }
        if ($attachment_ids) {
            // Prefer a public URL. It works on hosts where Telegram cannot
            // access the local filesystem path, and also supports variations.
            $attachment_url = wp_get_attachment_image_url($attachment_ids, self::$display_photosize);
            if ($attachment_url) {
                self::sendPhoto(['text'=>$text, 'keyboard'=>$keyboard], $attachment_url);
                return true;
            }
            $attachment = self::getPhotoRealPath($attachment_ids, self::$display_photosize);
            if ($attachment) {
                self::sendPhoto(['text'=>$text, 'keyboard'=>$keyboard], $attachment);
                return true;
            }
        }
        self::sendMessage(['text'=>$text, 'keyboard'=>$keyboard]);
        return true;
    }
    public static function productByUrl($product, $keyboard, $qty=0, $attribute_key=0)  {
        $id = $product['id'];
        $text = self::productGetText($product, $qty);
        $attachment_ids = get_post_thumbnail_id( $id );
        $attachment = wp_get_attachment_image_src($attachment_ids, self::$display_photosize );
        $text .= isset($attachment[0])?"\n\n{$attachment[0]}":'';
        self::sendMessage(['text'=>$text, 'keyboard'=>$keyboard]);
        return true;
    }
    public static function productByText($product, $keyboard, $qty=0, $attribute_key=0)  {
        $text = self::productGetText($product, $qty);
        self::sendMessage(['text'=>$text, 'keyboard'=>$keyboard]);
        return true;
    }
    public static function getPhotoRealPath($attachment_id, $size = 'thumbnail')  {
        $file = get_attached_file($attachment_id, true);
        if (empty($size) || $size === 'full')  {
            return realpath($file);
        }
        if (! wp_attachment_is_image($attachment_id) )  {
            return false;
        }
        $info = image_get_intermediate_size($attachment_id, $size);
        if (!is_array($info) || ! isset($info['file']))  {
            return false;
        }
        return realpath(str_replace(wp_basename($file), $info['file'], $file));
    }
    public static function productShortLink($product_id,$force_full_url=false) {
        if(self::$display_product_link_type == 2 OR $force_full_url) {
            return get_permalink($product_id);
        }
        return get_site_url() . "/?p={$product_id}";
    }
    public static function getProductCategoryById( $category_id )  {
        $term = get_term_by( 'id', $category_id, 'product_cat', 'ARRAY_A' );
        return $term;
    }
}
