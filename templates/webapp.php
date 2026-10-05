<?php
if (!defined('ABSPATH')) exit;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public Mini App language selector is read-only.
$woogram_requested_lang = isset($_GET['lang']) ? sanitize_key(wp_unslash($_GET['lang'])) : '';
$woogram_lang = preg_match('/\A[a-z]{2}\z/', $woogram_requested_lang) ? $woogram_requested_lang : 'fa';
$woogram_is_rtl = ($woogram_lang === 'fa');
$woogram_is_persian = ($woogram_lang === 'fa');
$woogram_currency = function_exists('get_woocommerce_currency_symbol') ? (string) get_woocommerce_currency_symbol() : '$';
// Some currency plugins return an HTML-encoded symbol (for example
// &#x062A;&#x0648;&#x0645;&#x0627;&#x0646;). Decode it before passing it to JavaScript.
for ($woogram_currency_decode_attempt = 0; $woogram_currency_decode_attempt < 3; $woogram_currency_decode_attempt++) {
    $woogram_decoded_currency = html_entity_decode($woogram_currency, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($woogram_decoded_currency === $woogram_currency) {
        break;
    }
    $woogram_currency = $woogram_decoded_currency;
}
$woogram_currency = wp_strip_all_tags($woogram_currency);
$woogram_site_name = get_bloginfo('name');
$woogram_webapp_nonce = wp_create_nonce('woogram_webapp_nonce');
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($woogram_lang); ?>" dir="<?php echo $woogram_is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo esc_html($woogram_site_name); ?> - TeleNexa Mini App</title>
    <?php
    wp_enqueue_script('telegram-web-app', 'https://telegram.org/js/telegram-web-app.js', [], null, false);
    wp_enqueue_style('telenexa-webapp', plugins_url('assets/css/webapp.css', dirname(__FILE__)), [], '1.0.0');
    wp_enqueue_script('telenexa-webapp', plugins_url('assets/js/webapp.js', dirname(__FILE__)), ['telegram-web-app'], '1.0.0', true);

    $webapp_config = [
        'ajaxUrl'      => admin_url('admin-ajax.php'),
        'nonce'        => $woogram_webapp_nonce,
        'lang'         => $woogram_lang,
        'currency'     => $woogram_currency,
        'isPersian'    => (bool) $woogram_is_persian,
        'numberLocale' => $woogram_is_persian ? 'fa-IR' : 'en-US',
    ];
    wp_localize_script('telenexa-webapp', 'teleNexaWebAppConfig', $webapp_config);

    wp_head();
    ?>
</head>
<body class="<?php echo $woogram_is_rtl ? 'rtl' : 'ltr'; ?>">
    <!-- Top Header -->
    <header class="app-header">
        <div class="app-brand">
            <div class="app-logo">W</div>
            <div>
                <h1 class="app-title"><?php echo esc_html($woogram_site_name); ?></h1>
                <div class="app-user" id="tg_user_display">TeleNexa</div>
            </div>
        </div>
    </header>

    <!-- TAB 1: SHOP (CATALOG) -->
    <section class="tab-view is-active" id="view-shop">
        <div class="search-container">
            <div class="search-box">
                <input type="text" id="search_input" class="search-input" placeholder="<?php echo $woogram_is_persian ? 'جستجوی نام کالا یا شناسه SKU...' : 'Search products by name or SKU...'; ?>">
                <span class="search-icon">🔍</span>
            </div>
        </div>
        <div class="products-grid" id="products_grid"></div>
    </section>

    <!-- TAB 2: CART & CHECKOUT -->
    <section class="tab-view" id="view-cart">
        <div class="cart-container">
            <h2 style="font-size: 18px; margin-bottom: 14px;"><?php echo $woogram_is_persian ? 'سبد خرید شما' : 'Shopping Cart'; ?></h2>
            <div id="cart_items_list"></div>

            <div class="cart-summary" id="cart_summary_box" style="display: none;">
                <!-- Summary breakdown -->
                <div class="summary-row">
                    <span><?php echo $woogram_is_persian ? 'مجموع خرید:' : 'Subtotal'; ?></span>
                    <strong id="cart_subtotal">0</strong>
                </div>
                <div class="summary-row" id="row_discount" style="display: none; color: #15803d;">
                    <span><?php echo $woogram_is_persian ? 'تخفیف:' : 'Discount'; ?></span>
                    <strong id="cart_discount">0</strong>
                </div>
                <div class="summary-row" id="row_shipping">
                    <span><?php echo $woogram_is_persian ? 'هزینه ارسال:' : 'Shipping'; ?></span>
                    <strong id="cart_shipping"><?php echo $woogram_is_persian ? 'محاسبه در مرحله بعد' : 'Calculated at checkout'; ?></strong>
                </div>
                <div class="summary-row" id="row_tax">
                    <span><?php echo $woogram_is_persian ? 'مالیات:' : 'Tax'; ?></span>
                    <strong id="cart_tax">0</strong>
                </div>
                <div class="summary-row total">
                    <span><?php echo $woogram_is_persian ? 'مبلغ نهایی:' : 'Final Total'; ?></span>
                    <strong id="cart_total" style="color: var(--button-color);">0</strong>
                </div>

                <!-- Coupon Box -->
                <div class="form-group" style="margin-top: 14px;">
                    <label class="form-label"><?php echo $woogram_is_persian ? 'کد تخفیف:' : 'Discount Coupon'; ?></label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="chk_coupon" class="form-input" placeholder="DISCOUNT10">
                        <button type="button" id="btn_apply_coupon" class="btn-coupon"><?php echo $woogram_is_persian ? 'اعمال' : 'Apply'; ?></button>
                    </div>
                    <div id="coupon_feedback" style="font-size: 12px; margin-top: 4px;"></div>
                </div>

                <!-- Checkout Details Form -->
                <div class="checkout-form">
                    <h3 style="font-size: 15px; margin-bottom: 12px; border-top: 1px solid var(--card-border); padding-top: 12px;">
                        <?php echo $woogram_is_persian ? 'مشخصات تحویل‌گیرنده' : 'Recipient Details'; ?>
                    </h3>
                    <div class="form-group">
                        <label class="form-label"><?php echo $woogram_is_persian ? 'نام و نام‌خانوادگی:' : 'Full Name'; ?> *</label>
                        <input type="text" id="chk_name" class="form-input" placeholder="<?php echo $woogram_is_persian ? 'علی محمدی' : 'John Doe'; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $woogram_is_persian ? 'شماره تماس همراه:' : 'Phone Number'; ?> *</label>
                        <input type="tel" id="chk_phone" class="form-input" placeholder="0912...">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $woogram_is_persian ? 'آدرس ایمیل:' : 'Email Address'; ?></label>
                        <input type="email" id="chk_email" class="form-input" placeholder="you@domain.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $woogram_is_persian ? 'استان:' : 'Province / State'; ?></label>
                        <input type="text" id="chk_state" class="form-input" placeholder="<?php echo $woogram_is_persian ? 'تهران' : 'Tehran'; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $woogram_is_persian ? 'شهر:' : 'City'; ?></label>
                        <input type="text" id="chk_city" class="form-input" placeholder="<?php echo $woogram_is_persian ? 'تهران' : 'Tehran'; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $woogram_is_persian ? 'نشانی دقیق پستی:' : 'Detailed Address'; ?> *</label>
                        <input type="text" id="chk_address" class="form-input" placeholder="<?php echo $woogram_is_persian ? 'خیابان، کوچه، پلاک...' : 'Street, No...'; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $woogram_is_persian ? 'کد پستی ۱۰ رقمی:' : 'Postal Code'; ?></label>
                        <input type="text" id="chk_zip" class="form-input" placeholder="1234567890">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo $woogram_is_persian ? 'یادداشت یا توضیحات سفارش:' : 'Order Notes'; ?></label>
                        <input type="text" id="chk_note" class="form-input" placeholder="<?php echo $woogram_is_persian ? 'اختیاری...' : 'Optional delivery notes...'; ?>">
                    </div>

                    <!-- Shipping Methods Container -->
                    <div id="shipping_section" style="margin-top: 14px; display: none;">
                        <label class="form-label"><?php echo $woogram_is_persian ? 'روش ارسال کالا:' : 'Select Shipping Method:'; ?></label>
                        <div class="option-card-row" id="shipping_methods_list"></div>
                    </div>

                    <!-- Payment Gateways Container -->
                    <div id="payment_section" style="margin-top: 14px; display: none;">
                        <label class="form-label"><?php echo $woogram_is_persian ? 'انتخاب درگاه پرداخت:' : 'Select Payment Gateway:'; ?></label>
                        <div class="option-card-row" id="payment_gateways_list"></div>
                    </div>

                    <button class="btn-checkout" id="btn_proceed_checkout">
                        💳 <?php echo $woogram_is_persian ? 'تکمیل سفارش و پرداخت' : 'Proceed to Payment'; ?>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- TAB 3: WISHLIST -->
    <section class="tab-view" id="view-wishlist">
        <div class="cart-container">
            <h2 style="font-size: 18px; margin-bottom: 14px;"><?php echo $woogram_is_persian ? 'علاقه‌مندی‌های من' : 'Saved Wishlist'; ?></h2>
            <div id="wishlist_items_list"></div>
        </div>
    </section>

    <!-- TAB 4: ORDERS -->
    <section class="tab-view" id="view-orders">
        <div class="cart-container">
            <h2 style="font-size: 18px; margin-bottom: 14px;"><?php echo $woogram_is_persian ? 'سفارش‌های من' : 'My Orders'; ?></h2>
            <div id="orders_list"></div>
        </div>
    </section>

    <!-- Variation Selection Modal -->
    <div class="modal-overlay" id="var_modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="var_modal_title">انتخاب مشخصات محصول</div>
                <button class="modal-close" onclick="closeVariationModal()">&times;</button>
            </div>
            <div style="display: flex; gap: 14px; align-items: center; margin-bottom: 16px;">
                <img id="var_modal_img" src="" style="width: 70px; height: 70px; object-fit: cover; border-radius: 8px;">
                <div>
                    <div id="var_modal_price" style="font-size: 16px; font-weight: 700; color: var(--button-color);"></div>
                    <div id="var_modal_stock" style="font-size: 12px; color: var(--hint-color); margin-top: 4px;"></div>
                </div>
            </div>
            <div id="var_options_container"></div>
            <button class="btn-checkout" id="btn_add_variation" style="margin-top: 16px;">
                🛒 <?php echo $woogram_is_persian ? 'افزودن این متغیر به سبد' : 'Add Variation to Cart'; ?>
            </button>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <div class="nav-item is-active" data-view="view-shop">
            <span class="nav-icon">🛍️</span>
            <span><?php echo $woogram_is_persian ? 'فروشگاه' : 'Shop'; ?></span>
        </div>
        <div class="nav-item" data-view="view-cart">
            <span class="nav-icon">🛒</span>
            <span><?php echo $woogram_is_persian ? 'سبد خرید' : 'Cart'; ?></span>
            <span class="nav-badge" id="nav_cart_badge" style="display: none;">0</span>
        </div>
        <div class="nav-item" data-view="view-wishlist">
            <span class="nav-icon">❤️</span>
            <span><?php echo $woogram_is_persian ? 'علاقه‌ها' : 'Wishlist'; ?></span>
        </div>
        <div class="nav-item" data-view="view-orders">
            <span class="nav-icon">📦</span>
            <span><?php echo $woogram_is_persian ? 'سفارش‌ها' : 'Orders'; ?></span>
        </div>
    </nav>


    <?php wp_footer(); ?>
</body>
</html>
