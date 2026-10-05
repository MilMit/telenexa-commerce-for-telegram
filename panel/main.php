<?php
if (!defined('ABSPATH')) exit;
// Included by woogram_render_main_panel(); template locals are renderer-scoped.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if (defined('WOOGRAM_MAIN_PANEL_LOADED')) {
    return;
}
define('WOOGRAM_MAIN_PANEL_LOADED', true);

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'telenexa-commerce-for-telegram'));
}

woogram_footer_text_call();

global $wpdb;

// -------------------------------------------------------------
// Auto-deduplication of legacy duplicate records
// -------------------------------------------------------------
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$dup_user_ids = $wpdb->get_col("
    SELECT p1.ID
    FROM {$wpdb->posts} p1
    INNER JOIN {$wpdb->posts} p2 ON p1.post_title = p2.post_title 
        AND p1.post_type = p2.post_type 
        AND p1.ID < p2.ID
    WHERE p1.post_type = 'woogram_user'
");
if (!empty($dup_user_ids)) {
    foreach ($dup_user_ids as $did) {
        wp_delete_post((int)$did, true);
    }
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$dup_grp_ids = $wpdb->get_col("
    SELECT p1.ID
    FROM {$wpdb->posts} p1
    INNER JOIN {$wpdb->posts} p2 ON p1.post_title = p2.post_title 
        AND p1.post_type = p2.post_type 
        AND p1.ID < p2.ID
    WHERE p1.post_type = 'woogram_group'
");
if (!empty($dup_grp_ids)) {
    foreach ($dup_grp_ids as $did) {
        wp_delete_post((int)$did, true);
    }
}

// -------------------------------------------------------------
// Actions Processing (Delete user, Clear logs, Send broadcast)
// -------------------------------------------------------------
$notice = null;

// 1. Delete single subscriber
if (isset($_GET['action']) && sanitize_key(wp_unslash($_GET['action'])) === 'delete_user' && isset($_GET['user_id'])) {
    $user_id = absint(wp_unslash($_GET['user_id']));
    if (check_admin_referer('delete_user_' . $user_id)) {
        wp_delete_post($user_id, true);
        $notice = [
            'type'    => 'success',
            'message' => __('Subscriber was successfully deleted.', 'telenexa-commerce-for-telegram')
        ];
    }
}

// 2. Delete single group
if (isset($_GET['action']) && sanitize_key(wp_unslash($_GET['action'])) === 'delete_group' && isset($_GET['group_id'])) {
    $group_id = absint(wp_unslash($_GET['group_id']));
    if (check_admin_referer('delete_group_' . $group_id)) {
        wp_delete_post($group_id, true);
        $notice = [
            'type'    => 'success',
            'message' => __('Group was successfully deleted.', 'telenexa-commerce-for-telegram')
        ];
    }
}

// 3. Clear logs
if (isset($_GET['action']) && sanitize_key(wp_unslash($_GET['action'])) === 'clear_logs') {
    if (check_admin_referer('clear_bot_logs')) {
        delete_option('wp_woogram_log');
        delete_option('wp_woogram_structured_logs');
        delete_option('wp_woogram_activity_log');
        $notice = [
            'type'    => 'success',
            'message' => __('All activity logs have been cleared.', 'telenexa-commerce-for-telegram')
        ];
    }
}

// 4. Handle Broadcast Message POST
if (isset($_SERVER['REQUEST_METHOD']) && sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])) === 'POST' && isset($_POST['telegram_new_message'])) {
    if (check_admin_referer('woogram_broadcast_action', 'woogram_broadcast_nonce')) {
        $message = wp_kses_post(wp_unslash($_POST['telegram_new_message']));
        $target  = isset($_POST['telegram_target']) ? absint(wp_unslash($_POST['telegram_target'])) : 0;
        
        if (!empty(trim($message))) {
            $sent_count = woogram_sendmessage_touser_switch($target, $message);
            $notice = [
                'type'    => 'success',
                // translators: %d: Sent recipient count
                'message' => sprintf(esc_html__('Message sent successfully to %d recipient(s).', 'telenexa-commerce-for-telegram'), (int)$sent_count)
            ];
        } else {
            $notice = [
                'type'    => 'error',
                'message' => __('Message content cannot be empty.', 'telenexa-commerce-for-telegram')
            ];
        }
    }
}

// -------------------------------------------------------------
// Data & Metrics Aggregation
// -------------------------------------------------------------
$options        = get_option('woogram_settings', array());
$bot_token      = isset($options['token']) ? trim($options['token']) : '';
$bot_username   = isset($options['username']) ? trim($options['username']) : '';
$channel_user   = isset($options['channelusername']) ? trim($options['channelusername']) : '';
$is_test_mode   = isset($options['testmod']) && $options['testmod'] === 'yes';
$ssl_active     = is_ssl();

// Distinct Subscribers & Groups Counts
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$sub_count = (int)$wpdb->get_var("
    SELECT COUNT(DISTINCT post_title) FROM {$wpdb->posts}
    WHERE post_type = 'woogram_user' AND post_status != 'trash'
");

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$grp_count = (int)$wpdb->get_var("
    SELECT COUNT(DISTINCT post_title) FROM {$wpdb->posts}
    WHERE post_type = 'woogram_group' AND post_status != 'trash'
");

// Telegram WooCommerce Orders & Revenue (HPOS-Compatible via WooBot Repository)
if (class_exists('\\TeleNexa\\WooBot') && method_exists('\\TeleNexa\\WooBot', 'getTelegramSalesStats')) {
    $sales_data       = \TeleNexa\WooBot::getTelegramSalesStats();
    $bot_orders_count = isset($sales_data['total_orders']) ? (int)$sales_data['total_orders'] : 0;
    $bot_revenue      = isset($sales_data['total_revenue']) ? (float)$sales_data['total_revenue'] : 0.0;
    $today_sales      = isset($sales_data['today_sales']) ? (float)$sales_data['today_sales'] : 0.0;
    $month_sales      = isset($sales_data['month_sales']) ? (float)$sales_data['month_sales'] : 0.0;
    $conversion_rate  = isset($sales_data['conversion_rate']) ? (float)$sales_data['conversion_rate'] : 0.0;
    $top_products     = \TeleNexa\WooBot::getTelegramTopProducts(5);
} else {
    $bot_orders_count = 0;
    $bot_revenue      = 0.0;
    $today_sales      = 0.0;
    $month_sales      = 0.0;
    $conversion_rate  = 0.0;
    $top_products     = [];
}

// Logs
$logs_raw = get_option('wp_woogram_log');
$logs     = @json_decode($logs_raw, true);
$logs     = is_array($logs) ? array_reverse($logs) : array();
$structured_logs = get_option('wp_woogram_activity_log', []);
if (is_array($structured_logs)) {
    foreach (array_reverse($structured_logs) as $structured) {
        $context = isset($structured['context']) && is_array($structured['context']) ? wp_json_encode($structured['context'], JSON_UNESCAPED_UNICODE) : '';
        $logs[] = [
            $structured['event'] ?? 'event',
            $structured['time'] ?? '',
            $structured['chat_id'] ?? '',
            $context,
        ];
    }
}
$logs_count = count($logs);
$proxy_health = class_exists('TeleNexa\\Http\\ProxyManager') ? \TeleNexa\Http\ProxyManager::health() : [];
$proxy_profiles = class_exists('TeleNexa\\Http\\ProxyManager') ? \TeleNexa\Http\ProxyManager::profiles() : [];

// Active Tab
$current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'overview';
$tabs = array(
    'overview'    => array('name' => __('Overview & Stats', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-dashboard'),
    'broadcast'   => array('name' => __('Broadcast Message', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-megaphone'),
    'subscribers' => array('name' => __('Subscribers & Groups', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-admin-users'),
    'logs'        => array('name' => __('Activity Logs', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-list-view'),
);

if (!isset($tabs[$current_tab])) {
    $current_tab = 'overview';
}

// Active Audience Sub-tab (Users vs Groups)
$audience_subtab = isset($_GET['view']) && sanitize_key(wp_unslash($_GET['view'])) === 'groups' ? 'groups' : 'users';
?>

<div class="wrap milmit-hub-wrap" dir="rtl">
    <!-- Hub Header -->
    <header class="milmit-hub-header">
        <div class="milmit-hub-header-main">
            <div class="milmit-hub-brand">
                <div class="milmit-hub-logo-icon" style="background:transparent;padding:0;display:flex;align-items:center;justify-content:center;">
                    <img src="<?php echo esc_url(plugins_url('img/woogram-icon.svg', dirname(__DIR__) . '/telenexa.php')); ?>" width="38" height="38" alt="TeleNexa" style="border-radius:9px;box-shadow:0 4px 12px rgba(34,158,217,0.35);display:block;">
                </div>
                <div class="milmit-hub-brand-text">
                    <div class="milmit-hub-title-row">
                        <h1><?php esc_html_e('TeleNexa Studio', 'telenexa-commerce-for-telegram'); ?></h1>
                        <span class="milmit-hub-version-badge">v1.0.0</span>
                    </div>
                    <p class="milmit-hub-subtitle"><?php esc_html_e('Centralized hub for managing Telegram sales, subscribers, broadcasts and system activity.', 'telenexa-commerce-for-telegram'); ?></p>
                </div>
            </div>

            <!-- Bot Quick Meta -->
            <div class="milmit-hub-quick-meta">
                <?php if (!empty($bot_username)): ?>
                    <a href="https://t.me/<?php echo esc_attr($bot_username); ?>" target="_blank" class="milmit-hub-bot-badge" title="<?php esc_attr_e('Open Bot in Telegram', 'telenexa-commerce-for-telegram'); ?>">
                        <span class="dashicons dashicons-external"></span>
                        @<?php echo esc_html($bot_username); ?>
                    </a>
                <?php endif; ?>

                <?php if (!empty($bot_token)): ?>
                    <span class="milmit-hub-status-pill online">
                        <span class="milmit-pulse-dot"></span>
                        <?php esc_html_e('Active (Webhook Ready)', 'telenexa-commerce-for-telegram'); ?>
                    </span>
                <?php else: ?>
                    <span class="milmit-hub-status-pill offline">
                        <?php esc_html_e('No Token Entered', 'telenexa-commerce-for-telegram'); ?>
                    </span>
                <?php endif; ?>

                <a href="<?php echo esc_url(admin_url('admin.php?page=woogram_settings')); ?>" class="milmit-btn-hub-settings">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('Settings', 'telenexa-commerce-for-telegram'); ?>
                </a>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <nav class="milmit-hub-tabs">
            <?php foreach ($tabs as $key => $tab): 
                $is_active = ($current_tab === $key);
                $tab_url = add_query_arg(array('page' => 'woogram_main', 'tab' => $key), admin_url('admin.php'));
            ?>
                <a href="<?php echo esc_url($tab_url); ?>" class="milmit-hub-tab-item <?php echo $is_active ? 'active' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
                    <span class="milmit-hub-tab-text"><?php echo esc_html($tab['name']); ?></span>
                    <?php if ($key === 'subscribers' && ($sub_count + $grp_count) > 0): ?>
                        <span class="milmit-hub-tab-badge"><?php echo esc_html(number_format_i18n($sub_count + $grp_count)); ?></span>
                    <?php elseif ($key === 'logs' && $logs_count > 0): ?>
                        <span class="milmit-hub-tab-badge neutral"><?php echo esc_html(number_format_i18n($logs_count)); ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <!-- Notices -->
    <?php if ($notice): ?>
        <div class="milmit-hub-notice milmit-hub-notice-<?php echo esc_attr($notice['type']); ?>">
            <span class="dashicons <?php echo $notice['type'] === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
            <div class="milmit-hub-notice-content">
                <?php echo esc_html($notice['message']); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab Contents -->
    <main class="milmit-hub-body">

        <?php if ($current_tab === 'overview'): ?>
            <!-- ========================================================= -->
            <!-- TAB 1: OVERVIEW & STATS                                   -->
            <!-- ========================================================= -->
            <div class="milmit-overview-grid">
                
                <!-- KPI Stat Cards -->
                <div class="milmit-kpi-card">
                    <div class="milmit-kpi-icon-wrap blue">
                        <span class="dashicons dashicons-admin-users"></span>
                    </div>
                    <div class="milmit-kpi-info">
                        <span class="milmit-kpi-label"><?php esc_html_e('Bot Subscribers', 'telenexa-commerce-for-telegram'); ?></span>
                        <h3 class="milmit-kpi-value"><?php echo esc_html(number_format_i18n($sub_count)); ?></h3>
                        <a href="<?php echo esc_url(add_query_arg(array('page' => 'woogram_main', 'tab' => 'subscribers', 'view' => 'users'), admin_url('admin.php'))); ?>" class="milmit-kpi-link">
                            <?php esc_html_e('View all subscribers &rarr;', 'telenexa-commerce-for-telegram'); ?>
                        </a>
                    </div>
                </div>

                <div class="milmit-kpi-card">
                    <div class="milmit-kpi-icon-wrap purple">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="milmit-kpi-info">
                        <span class="milmit-kpi-label"><?php esc_html_e('Active Groups', 'telenexa-commerce-for-telegram'); ?></span>
                        <h3 class="milmit-kpi-value"><?php echo esc_html(number_format_i18n($grp_count)); ?></h3>
                        <a href="<?php echo esc_url(add_query_arg(array('page' => 'woogram_main', 'tab' => 'subscribers', 'view' => 'groups'), admin_url('admin.php'))); ?>" class="milmit-kpi-link">
                            <?php esc_html_e('View all groups &rarr;', 'telenexa-commerce-for-telegram'); ?>
                        </a>
                    </div>
                </div>

                <div class="milmit-kpi-card">
                    <div class="milmit-kpi-icon-wrap green">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                    <div class="milmit-kpi-info">
                        <span class="milmit-kpi-label"><?php esc_html_e('Orders via Telegram', 'telenexa-commerce-for-telegram'); ?></span>
                        <h3 class="milmit-kpi-value"><?php echo esc_html(number_format_i18n($bot_orders_count)); ?></h3>
                        <span class="milmit-kpi-subtext">
                            <?php 
                            if (function_exists('wc_price')) {
                                // translators: %s: Total sales amount
                                echo wp_kses_post(sprintf(__('Total Sales: %s', 'telenexa-commerce-for-telegram'), wc_price($bot_revenue)));
                            } else {
                                $sym = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '';
                                echo esc_html(number_format_i18n($bot_revenue) . ($sym ? ' ' . $sym : ''));
                            }
                            ?>
                        </span>
                    </div>
                </div>

                <div class="milmit-kpi-card">
                    <div class="milmit-kpi-icon-wrap orange">
                        <span class="dashicons dashicons-shield"></span>
                    </div>
                    <div class="milmit-kpi-info">
                        <span class="milmit-kpi-label"><?php esc_html_e('Connection Health', 'telenexa-commerce-for-telegram'); ?></span>
                        <h3 class="milmit-kpi-value"><?php echo esc_html($ssl_active ? __('Secure (SSL)', 'telenexa-commerce-for-telegram') : __('Non-SSL', 'telenexa-commerce-for-telegram')); ?></h3>
                        <span class="milmit-kpi-subtext">
                            <?php echo $is_test_mode ? '<span style="color:#ef4444;">' . esc_html__('Test Mode Active', 'telenexa-commerce-for-telegram') . '</span>' : esc_html__('Production Ready', 'telenexa-commerce-for-telegram'); ?>
                        </span>
                    </div>
                </div>

            </div>

            <!-- Overview Two Columns: Health & Quick Operations -->
            <div class="milmit-split-grid">
                
                <!-- System Health & Connection Details -->
                <div class="milmit-card">
                    <div class="milmit-card-header">
                        <div class="milmit-card-header-title">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <h3><?php esc_html_e('Bot Connection & Health', 'telenexa-commerce-for-telegram'); ?></h3>
                        </div>
                    </div>
                    <div class="milmit-card-body">
                        <div class="milmit-health-list">
                            <div class="milmit-health-item">
                                <span class="milmit-health-label"><?php esc_html_e('Telegram Bot Token', 'telenexa-commerce-for-telegram'); ?></span>
                                <span class="milmit-health-val">
                                    <?php if (!empty($bot_token)): ?>
                                        <span class="milmit-tag success"><?php esc_html_e('Configured', 'telenexa-commerce-for-telegram'); ?></span>
                                    <?php else: ?>
                                        <span class="milmit-tag error"><?php esc_html_e('Not set', 'telenexa-commerce-for-telegram'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="milmit-health-item">
                                <span class="milmit-health-label"><?php esc_html_e('Bot Username', 'telenexa-commerce-for-telegram'); ?></span>
                                <span class="milmit-health-val">
                                    <?php if (!empty($bot_username)): ?>
                                        <a href="https://t.me/<?php echo esc_attr($bot_username); ?>" target="_blank" class="milmit-tg-link">@<?php echo esc_html($bot_username); ?></a>
                                    <?php else: ?>
                                        <span class="milmit-text-muted">—</span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="milmit-health-item">
                                <span class="milmit-health-label"><?php esc_html_e('Telegram Proxy', 'telenexa-commerce-for-telegram'); ?></span>
                                <span class="milmit-health-val">
                                    <?php if (empty($proxy_profiles)): ?>
                                        <span class="milmit-tag success"><?php esc_html_e('Direct connection', 'telenexa-commerce-for-telegram'); ?></span>
                                    <?php else: ?>
                                        <?php $proxy_ok = false; foreach ($proxy_health as $proxy_state) { if (!empty($proxy_state['ok'])) { $proxy_ok = true; break; } } ?>
                                        <span class="milmit-tag <?php echo $proxy_ok ? 'success' : 'warning'; ?>"><?php echo $proxy_ok ? esc_html__('Online', 'telenexa-commerce-for-telegram') : esc_html__('Not tested / failed', 'telenexa-commerce-for-telegram'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="milmit-health-item">
                                <span class="milmit-health-label"><?php esc_html_e('Webhook Endpoint', 'telenexa-commerce-for-telegram'); ?></span>
                                <span class="milmit-health-val">
                                    <?php if ((string) woogram_option('mode') === '0' && (string) woogram_option('testmod') !== 'yes'): ?><code style="font-size:11px;"><?php echo esc_html(home_url('/?' . rawurlencode((string) get_option('woogram_webhook_key')) . '=1')); ?></code><?php else: ?><span class="milmit-tag warning"><?php esc_html_e('MilMit relay mode', 'telenexa-commerce-for-telegram'); ?></span><?php endif; ?>
                                </span>
                            </div>

                            <div class="milmit-health-item">
                                <span class="milmit-health-label"><?php esc_html_e('Store Channel', 'telenexa-commerce-for-telegram'); ?></span>
                                <span class="milmit-health-val">
                                    <?php if (!empty($channel_user)): ?>
                                        <strong><?php echo esc_html($channel_user); ?></strong>
                                    <?php else: ?>
                                        <span class="milmit-text-muted"><?php esc_html_e('Optional', 'telenexa-commerce-for-telegram'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Hub: All Options & Modules -->
                <div class="milmit-card">
                    <div class="milmit-card-header">
                        <div class="milmit-card-header-title">
                            <span class="dashicons dashicons-screenoptions"></span>
                            <h3><?php esc_html_e('All Options & Modules', 'telenexa-commerce-for-telegram'); ?></h3>
                        </div>
                    </div>
                    <div class="milmit-card-body">
                        <div class="milmit-shortcuts-grid">
                            <a href="<?php echo esc_url(add_query_arg(array('page' => 'woogram_main', 'tab' => 'subscribers', 'view' => 'users'), admin_url('admin.php'))); ?>" class="milmit-shortcut-card">
                                <span class="dashicons dashicons-admin-users"></span>
                                <div class="milmit-shortcut-text">
                                    <strong><?php esc_html_e('Subscribers', 'telenexa-commerce-for-telegram'); ?> (<?php echo esc_html(number_format_i18n($sub_count)); ?>)</strong>
                                    <small><?php esc_html_e('View and filter unique subscribers and groups', 'telenexa-commerce-for-telegram'); ?></small>
                                </div>
                            </a>

                            <a href="<?php echo esc_url(add_query_arg(array('page' => 'woogram_main', 'tab' => 'subscribers', 'view' => 'groups'), admin_url('admin.php'))); ?>" class="milmit-shortcut-card">
                                <span class="dashicons dashicons-groups"></span>
                                <div class="milmit-shortcut-text">
                                    <strong><?php esc_html_e('Groups', 'telenexa-commerce-for-telegram'); ?> (<?php echo esc_html(number_format_i18n($grp_count)); ?>)</strong>
                                    <small><?php esc_html_e('Connected Telegram groups and channels', 'telenexa-commerce-for-telegram'); ?></small>
                                </div>
                            </a>

                            <a href="<?php echo esc_url(add_query_arg(array('page' => 'woogram_main', 'tab' => 'broadcast'), admin_url('admin.php'))); ?>" class="milmit-shortcut-card">
                                <span class="dashicons dashicons-megaphone"></span>
                                <div class="milmit-shortcut-text">
                                    <strong><?php esc_html_e('Broadcast Message', 'telenexa-commerce-for-telegram'); ?></strong>
                                    <small><?php esc_html_e('Compose and send messages with live Telegram preview', 'telenexa-commerce-for-telegram'); ?></small>
                                </div>
                            </a>

                            <a href="<?php echo esc_url(admin_url('admin.php?page=woogram_settings')); ?>" class="milmit-shortcut-card">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <div class="milmit-shortcut-text">
                                    <strong><?php esc_html_e('Settings', 'telenexa-commerce-for-telegram'); ?></strong>
                                    <small><?php esc_html_e('Appearance, checkout fields, proxy and bot commands', 'telenexa-commerce-for-telegram'); ?></small>
                                </div>
                            </a>

                            <a href="<?php echo esc_url(admin_url('admin.php?page=woogram_settings&tab=webapp')); ?>" class="milmit-shortcut-card">
                                <span class="dashicons dashicons-smartphone"></span>
                                <div class="milmit-shortcut-text">
                                    <strong><?php esc_html_e('Mini App', 'telenexa-commerce-for-telegram'); ?></strong>
                                    <small><?php esc_html_e('Telegram Web App settings', 'telenexa-commerce-for-telegram'); ?></small>
                                </div>
                            </a>

                            <a href="<?php echo esc_url(admin_url('admin.php?page=woogram_settings&tab=menu')); ?>" class="milmit-shortcut-card">
                                <span class="dashicons dashicons-menu"></span>
                                <div class="milmit-shortcut-text">
                                    <strong><?php esc_html_e('Main Menu', 'telenexa-commerce-for-telegram'); ?></strong>
                                    <small><?php esc_html_e('Buttons & Custom Actions', 'telenexa-commerce-for-telegram'); ?></small>
                                </div>
                            </a>

                            <a href="<?php echo esc_url(add_query_arg(array('page' => 'woogram_main', 'tab' => 'logs'), admin_url('admin.php'))); ?>" class="milmit-shortcut-card">
                                <span class="dashicons dashicons-list-view"></span>
                                <div class="milmit-shortcut-text">
                                    <strong><?php esc_html_e('Activity Logs', 'telenexa-commerce-for-telegram'); ?></strong>
                                    <small><?php esc_html_e('Inspect events, orders and error diagnostics', 'telenexa-commerce-for-telegram'); ?></small>
                                </div>
                            </a>

                            <a href="<?php echo esc_url(admin_url('admin.php?page=woogram_settings&tab=proxy')); ?>" class="milmit-shortcut-card">
                                <span class="dashicons dashicons-shield"></span>
                                <div class="milmit-shortcut-text">
                                    <strong><?php esc_html_e('Proxy', 'telenexa-commerce-for-telegram'); ?></strong>
                                    <small><?php esc_html_e('Connection Proxy Settings', 'telenexa-commerce-for-telegram'); ?></small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Top Selling Products via Telegram -->
            <div class="milmit-card" style="margin-top: 24px;">
                <div class="milmit-card-header">
                    <div class="milmit-card-header-title">
                        <span class="dashicons dashicons-star-filled"></span>
                        <h3><?php esc_html_e('Top Selling Products via Telegram', 'telenexa-commerce-for-telegram'); ?></h3>
                    </div>
                    <?php if (!empty($today_sales) || !empty($month_sales)): ?>
                        <div class="milmit-card-header-extra" style="font-size: 13px; color: #64748b;">
                            <span><?php // translators: %s: Today sales formatted amount
echo wp_kses_post(sprintf(__('Today: %s', 'telenexa-commerce-for-telegram'), function_exists('wc_price') ? wc_price($today_sales) : esc_html(number_format_i18n($today_sales)))); ?></span>
                            <span style="margin: 0 8px;">•</span>
                            <span><?php // translators: %s: Month sales formatted amount
echo wp_kses_post(sprintf(__('This Month: %s', 'telenexa-commerce-for-telegram'), function_exists('wc_price') ? wc_price($month_sales) : esc_html(number_format_i18n($month_sales)))); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="milmit-card-body" style="padding: 0;">
                    <?php if (!empty($top_products)): ?>
                        <table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
                            <thead>
                                <tr>
                                    <th style="width: 60px;"><?php esc_html_e('Image', 'telenexa-commerce-for-telegram'); ?></th>
                                    <th><?php esc_html_e('Product Name', 'telenexa-commerce-for-telegram'); ?></th>
                                    <th><?php esc_html_e('Price', 'telenexa-commerce-for-telegram'); ?></th>
                                    <th><?php esc_html_e('Telegram Sales', 'telenexa-commerce-for-telegram'); ?></th>
                                    <th style="width: 100px;"><?php esc_html_e('Action', 'telenexa-commerce-for-telegram'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_products as $p): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($p['image'])): ?>
                                                <img src="<?php echo esc_url($p['image']); ?>" width="40" height="40" style="border-radius: 6px; object-fit: cover;">
                                            <?php else: ?>
                                                <span class="dashicons dashicons-format-image" style="font-size: 32px; color: #cbd5e1;"></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html($p['name']); ?></strong>
                                        </td>
                                        <td><?php echo wp_kses_post($p['price']); ?></td>
                                        <td>
                                            <span class="milmit-tag success">
                                                <?php // translators: %d: Units sold count
echo esc_html(sprintf(__('%d Units Sold', 'telenexa-commerce-for-telegram'), (int)$p['sales'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url(get_edit_post_link($p['id'])); ?>" class="button button-small">
                                                <?php esc_html_e('Edit', 'telenexa-commerce-for-telegram'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding: 32px; text-align: center; color: #64748b;">
                            <span class="dashicons dashicons-cart" style="font-size: 36px; height: 36px; margin-bottom: 8px;"></span>
                            <p><?php esc_html_e('No sales recorded via Telegram yet.', 'telenexa-commerce-for-telegram'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($current_tab === 'broadcast'): ?>
            <!-- ========================================================= -->
            <!-- TAB 2: BROADCAST STUDIO (Interactive Composer & Live Mockup)-->
            <!-- ========================================================= -->
            <div class="milmit-broadcast-studio">
                
                <!-- Left: Composer Form -->
                <div class="milmit-card milmit-composer-card">
                    <div class="milmit-card-header">
                        <div class="milmit-card-header-title">
                            <span class="dashicons dashicons-megaphone"></span>
                            <h3><?php esc_html_e('Compose Broadcast Message', 'telenexa-commerce-for-telegram'); ?></h3>
                        </div>
                    </div>
                    <div class="milmit-card-body">
                        <form method="post" id="milmit-broadcast-form">
                            <?php wp_nonce_field('woogram_broadcast_action', 'woogram_broadcast_nonce'); ?>

                            <!-- Target Selection -->
                            <div class="milmit-form-group">
                                <label class="milmit-form-label"><?php esc_html_e('Select Target Audience', 'telenexa-commerce-for-telegram'); ?></label>
                                <div class="milmit-target-grid">
                                    <label class="milmit-target-card">
                                        <input type="radio" name="telegram_target" value="0" checked="checked">
                                        <div class="milmit-target-content">
                                            <span class="dashicons dashicons-networking"></span>
                                            <div class="milmit-target-title"><?php esc_html_e('All Audience', 'telenexa-commerce-for-telegram'); ?></div>
                                            <div class="milmit-target-desc"><?php esc_html_e('Users, Groups & Channel', 'telenexa-commerce-for-telegram'); ?></div>
                                        </div>
                                    </label>

                                    <label class="milmit-target-card">
                                        <input type="radio" name="telegram_target" value="1">
                                        <div class="milmit-target-content">
                                            <span class="dashicons dashicons-admin-users"></span>
                                            <div class="milmit-target-title"><?php esc_html_e('Subscribers Only', 'telenexa-commerce-for-telegram'); ?></div>
                                            <div class="milmit-target-desc"><?php // translators: %d: Registered user count
echo esc_html(sprintf(__('%d registered users', 'telenexa-commerce-for-telegram'), (int)$sub_count)); ?></div>
                                        </div>
                                    </label>

                                    <label class="milmit-target-card">
                                        <input type="radio" name="telegram_target" value="2">
                                        <div class="milmit-target-content">
                                            <span class="dashicons dashicons-groups"></span>
                                            <div class="milmit-target-title"><?php esc_html_e('Groups Only', 'telenexa-commerce-for-telegram'); ?></div>
                                            <div class="milmit-target-desc"><?php // translators: %d: Registered group count
echo esc_html(sprintf(__('%d registered groups', 'telenexa-commerce-for-telegram'), (int)$grp_count)); ?></div>
                                        </div>
                                    </label>

                                    <label class="milmit-target-card">
                                        <input type="radio" name="telegram_target" value="4">
                                        <div class="milmit-target-content">
                                            <span class="dashicons dashicons-megaphone"></span>
                                            <div class="milmit-target-title"><?php esc_html_e('Telegram Channel', 'telenexa-commerce-for-telegram'); ?></div>
                                            <div class="milmit-target-desc"><?php echo !empty($channel_user) ? esc_html($channel_user) : esc_html__('Not set', 'telenexa-commerce-for-telegram'); ?></div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Formatting Toolbar -->
                            <div class="milmit-form-group">
                                <label class="milmit-form-label" for="telegram_new_message"><?php esc_html_e('Message Content', 'telenexa-commerce-for-telegram'); ?></label>
                                
                                <div class="milmit-composer-toolbar">
                                    <button type="button" class="milmit-tool-btn" data-tag="bold" title="<?php esc_attr_e('Bold (*text*)', 'telenexa-commerce-for-telegram'); ?>">
                                        <b>B</b>
                                    </button>
                                    <button type="button" class="milmit-tool-btn" data-tag="italic" title="<?php esc_attr_e('Italic (_text_)', 'telenexa-commerce-for-telegram'); ?>">
                                        <i>I</i>
                                    </button>
                                    <button type="button" class="milmit-tool-btn" data-tag="link" title="<?php esc_attr_e('Link ([title](url))', 'telenexa-commerce-for-telegram'); ?>">
                                        <span class="dashicons dashicons-admin-links"></span>
                                    </button>
                                    <button type="button" class="milmit-tool-btn" data-tag="code" title="<?php esc_attr_e('Monospace (`code`)', 'telenexa-commerce-for-telegram'); ?>">
                                        <span class="dashicons dashicons-editor-code"></span>
                                    </button>

                                    <span class="milmit-tool-separator"></span>

                                    <!-- Quick Emojis -->
                                    <div class="milmit-quick-emojis">
                                        <button type="button" class="milmit-emoji-btn">🔥</button>
                                        <button type="button" class="milmit-emoji-btn">🛍️</button>
                                        <button type="button" class="milmit-emoji-btn">🎁</button>
                                        <button type="button" class="milmit-emoji-btn">⚡</button>
                                        <button type="button" class="milmit-emoji-btn">📢</button>
                                        <button type="button" class="milmit-emoji-btn">✅</button>
                                        <button type="button" class="milmit-emoji-btn">⭐</button>
                                    </div>
                                </div>

                                <textarea name="telegram_new_message" id="telegram_new_message" rows="8" class="milmit-textarea" placeholder="<?php esc_attr_e('Write your Telegram message here... You can use Telegram Markdown formatting.', 'telenexa-commerce-for-telegram'); ?>"></textarea>
                                
                                <div class="milmit-composer-footer">
                                    <div class="milmit-char-counter">
                                        <span id="milmit-char-count">0</span> / 4096 <?php esc_html_e('characters', 'telenexa-commerce-for-telegram'); ?>
                                    </div>
                                    <div class="milmit-var-chips">
                                        <span class="milmit-var-label"><?php esc_html_e('Insert dynamic variables:', 'telenexa-commerce-for-telegram'); ?></span>
                                        <button type="button" class="milmit-var-chip" data-var="%TITLE%"><?php esc_html_e('Product Title', 'telenexa-commerce-for-telegram'); ?></button>
                                        <button type="button" class="milmit-var-chip" data-var="%LINK%"><?php esc_html_e('Website Link', 'telenexa-commerce-for-telegram'); ?></button>
                                    </div>
                                </div>
                            </div>

                            <!-- Send Action -->
                            <div class="milmit-form-actions">
                                <button type="submit" class="milmit-btn-primary milmit-btn-lg" id="milmit-send-btn">
                                    <span class="dashicons dashicons-controls-play"></span>
                                    <?php esc_html_e('Send Broadcast Now', 'telenexa-commerce-for-telegram'); ?>
                                </button>
                                <span class="milmit-help-text" style="margin-right:15px;">
                                    <?php esc_html_e('Messages will be dispatched in background queue.', 'telenexa-commerce-for-telegram'); ?>
                                </span>
                            </div>

                        </form>
                    </div>
                </div>

                <!-- Right: Telegram Live Mobile Mockup -->
                <div class="milmit-preview-container">
                    <div class="milmit-telegram-phone">
                        <!-- Phone Notch / Speaker -->
                        <div class="milmit-phone-notch">
                            <span class="milmit-phone-speaker"></span>
                        </div>

                        <!-- Telegram Chat Header -->
                        <div class="milmit-telegram-header">
                            <div class="milmit-tg-back"><span class="dashicons dashicons-arrow-right-alt2"></span></div>
                            <div class="milmit-tg-avatar">
                                <svg viewBox="0 0 24 24" fill="white" width="18" height="18"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.75-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .37z"/></svg>
                            </div>
                            <div class="milmit-tg-info">
                                <div class="milmit-tg-title"><?php echo !empty($bot_username) ? esc_html($bot_username) : 'TeleNexa Shop Bot'; ?></div>
                                <div class="milmit-tg-status"><?php esc_html_e('bot', 'telenexa-commerce-for-telegram'); ?></div>
                            </div>
                            <div class="milmit-tg-actions">
                                <span class="dashicons dashicons-ellipsis"></span>
                            </div>
                        </div>

                        <!-- Telegram Chat Screen -->
                        <div class="milmit-telegram-canvas">
                            <div class="milmit-tg-date-badge"><?php esc_html_e('Today', 'telenexa-commerce-for-telegram'); ?></div>

                            <!-- Dynamic Chat Bubble -->
                            <div class="milmit-tg-bubble">
                                <div class="milmit-tg-message-text" id="milmit-tg-live-preview">
                                    <?php esc_html_e('Your live message preview will appear here in real time as you type...', 'telenexa-commerce-for-telegram'); ?>
                                </div>
                                <div class="milmit-tg-meta">
                                    <span class="milmit-tg-time" id="milmit-tg-live-time"><?php echo esc_html(gmdate('H:i')); ?></span>
                                    <span class="milmit-tg-checks">✓✓</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Home Bar -->
                        <div class="milmit-phone-home-bar"></div>
                    </div>
                    <p class="milmit-preview-hint"><?php esc_html_e('Real-time Telegram phone preview with Markdown parsing', 'telenexa-commerce-for-telegram'); ?></p>
                </div>

            </div>

        <?php elseif ($current_tab === 'subscribers'): ?>
            <!-- ========================================================= -->
            <!-- TAB 3: SUBSCRIBERS & GROUPS (Audience Manager)            -->
            <!-- ========================================================= -->
            <div class="milmit-card">
                <div class="milmit-card-header">
                    <div class="milmit-card-header-title">
                        <span class="dashicons dashicons-admin-users"></span>
                        <h3><?php esc_html_e('Audience Management', 'telenexa-commerce-for-telegram'); ?></h3>
                    </div>

                    <!-- Sub-navigation: Users vs Groups -->
                    <div class="milmit-sub-pills">
                        <a href="<?php echo esc_url(add_query_arg(array('page' => 'woogram_main', 'tab' => 'subscribers', 'view' => 'users'), admin_url('admin.php'))); ?>" class="milmit-pill <?php echo $audience_subtab === 'users' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-admin-users"></span>
                            <?php esc_html_e('Subscribers', 'telenexa-commerce-for-telegram'); ?> (<?php echo esc_html(number_format_i18n($sub_count)); ?>)
                        </a>
                        <a href="<?php echo esc_url(add_query_arg(array('page' => 'woogram_main', 'tab' => 'subscribers', 'view' => 'groups'), admin_url('admin.php'))); ?>" class="milmit-pill <?php echo $audience_subtab === 'groups' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-groups"></span>
                            <?php esc_html_e('Groups', 'telenexa-commerce-for-telegram'); ?> (<?php echo esc_html(number_format_i18n($grp_count)); ?>)
                        </a>
                        <a href="<?php echo esc_url(admin_url($audience_subtab === 'users' ? 'edit.php?post_type=woogram_user' : 'edit.php?post_type=woogram_group')); ?>" class="milmit-pill" style="border: 1px dashed #cbd5e1; background: #f8fafc; color: #64748b;" target="_blank" title="<?php esc_attr_e('View in WordPress list table', 'telenexa-commerce-for-telegram'); ?>">
                            <span class="dashicons dashicons-external"></span>
                            <?php esc_html_e('Advanced WordPress View', 'telenexa-commerce-for-telegram'); ?>
                        </a>
                    </div>
                    
                    <!-- Search Filter -->
                    <div class="milmit-search-box">
                        <span class="dashicons dashicons-search"></span>
                        <input type="text" id="milmit-audience-search" placeholder="<?php esc_attr_e('Search by Name, Username or Chat ID...', 'telenexa-commerce-for-telegram'); ?>">
                    </div>
                </div>

                <div class="milmit-card-body p-0">
                    <?php if ($audience_subtab === 'users'): ?>
                        <!-- Unique Subscribers List -->
                        <?php
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$all_subscribers = $wpdb->get_results("
                            SELECT p.ID, p.post_title, p.post_date,
                                   m1.meta_value as first_name,
                                   m2.meta_value as last_name,
                                   m3.meta_value as username
                            FROM {$wpdb->posts} p
                            LEFT JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = 'telegram_first_name'
                            LEFT JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = 'telegram_last_name'
                            LEFT JOIN {$wpdb->postmeta} m3 ON p.ID = m3.post_id AND m3.meta_key = 'telegram_username'
                            WHERE p.post_type = 'woogram_user' AND p.post_status != 'trash'
                            GROUP BY p.post_title
                            ORDER BY p.ID DESC
                        ");

                        if (!empty($all_subscribers)):
                        ?>
                            <div class="milmit-table-responsive">
                                <table class="milmit-data-table" id="milmit-subscribers-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;"><?php esc_html_e('Avatar', 'telenexa-commerce-for-telegram'); ?></th>
                                            <th><?php esc_html_e('Full Name', 'telenexa-commerce-for-telegram'); ?></th>
                                            <th><?php esc_html_e('Username', 'telenexa-commerce-for-telegram'); ?></th>
                                            <th><?php esc_html_e('Chat ID', 'telenexa-commerce-for-telegram'); ?></th>
                                            <th><?php esc_html_e('Subscribe Date', 'telenexa-commerce-for-telegram'); ?></th>
                                            <th style="text-align: left; width: 90px;"><?php esc_html_e('Action', 'telenexa-commerce-for-telegram'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_subscribers as $sub):
                                            $cid        = $sub->post_title;
                                            $full_name  = trim(($sub->first_name ?? '') . ' ' . ($sub->last_name ?? ''));
                                            if (empty($full_name)) $full_name = __('Telegram User', 'telenexa-commerce-for-telegram');
                                            $uname      = $sub->username ?? '';
                                            $delete_url = wp_nonce_url(add_query_arg(array('page' => 'woogram_main', 'tab' => 'subscribers', 'view' => 'users', 'action' => 'delete_user', 'user_id' => $sub->ID), admin_url('admin.php')), 'delete_user_' . $sub->ID);
                                        ?>
                                            <tr class="milmit-audience-row">
                                                <td>
                                                    <div class="milmit-avatar-circle">
                                                        <?php echo esc_html(mb_substr($full_name, 0, 1, 'UTF-8')); ?>
                                                    </div>
                                                </td>
                                                <td class="col-name">
                                                    <strong><?php echo esc_html($full_name); ?></strong>
                                                </td>
                                                <td class="col-username">
                                                    <?php if (!empty($uname)): ?>
                                                        <a href="https://t.me/<?php echo esc_attr($uname); ?>" target="_blank" class="milmit-tg-link">
                                                            @<?php echo esc_html($uname); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="milmit-text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="col-chatid">
                                                    <code class="milmit-chatid-tag" title="<?php esc_attr_e('Click to copy', 'telenexa-commerce-for-telegram'); ?>"><?php echo esc_html($cid); ?></code>
                                                </td>
                                                <td class="col-date">
                                                    <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $sub->post_date)); ?>
                                                </td>
                                                <td style="text-align: left;">
                                                    <a href="<?php echo esc_url($delete_url); ?>" class="milmit-btn-action-delete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this subscriber?', 'telenexa-commerce-for-telegram'); ?>');" title="<?php esc_attr_e('Delete', 'telenexa-commerce-for-telegram'); ?>">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="milmit-empty-state">
                                <span class="dashicons dashicons-admin-users"></span>
                                <h3><?php esc_html_e('No Subscribers Found', 'telenexa-commerce-for-telegram'); ?></h3>
                                <p><?php esc_html_e('When visitors interact with your bot, they will be registered here automatically.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Unique Groups List -->
                        <?php
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$all_groups = $wpdb->get_results("
                            SELECT p.ID, p.post_title, p.post_date,
                                   m1.meta_value as group_title
                            FROM {$wpdb->posts} p
                            LEFT JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = 'telegram_group_title'
                            WHERE p.post_type = 'woogram_group' AND p.post_status != 'trash'
                            GROUP BY p.post_title
                            ORDER BY p.ID DESC
                        ");

                        if (!empty($all_groups)):
                        ?>
                            <div class="milmit-table-responsive">
                                <table class="milmit-data-table" id="milmit-groups-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;"><?php esc_html_e('Icon', 'telenexa-commerce-for-telegram'); ?></th>
                                            <th><?php esc_html_e('Group Name', 'telenexa-commerce-for-telegram'); ?></th>
                                            <th><?php esc_html_e('Group Chat ID', 'telenexa-commerce-for-telegram'); ?></th>
                                            <th><?php esc_html_e('Subscribe Date', 'telenexa-commerce-for-telegram'); ?></th>
                                            <th style="text-align: left; width: 90px;"><?php esc_html_e('Action', 'telenexa-commerce-for-telegram'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_groups as $grp):
                                            $cid         = $grp->post_title;
                                            $group_title = !empty($grp->group_title) ? $grp->group_title : __('Telegram Group', 'telenexa-commerce-for-telegram');
                                            $delete_url  = wp_nonce_url(add_query_arg(array('page' => 'woogram_main', 'tab' => 'subscribers', 'view' => 'groups', 'action' => 'delete_group', 'group_id' => $grp->ID), admin_url('admin.php')), 'delete_group_' . $grp->ID);
                                        ?>
                                            <tr class="milmit-audience-row">
                                                <td>
                                                    <div class="milmit-avatar-circle" style="background:#8b5cf6;">
                                                        <span class="dashicons dashicons-groups" style="color:#fff;font-size:18px;margin-top:2px;"></span>
                                                    </div>
                                                </td>
                                                <td class="col-name">
                                                    <strong><?php echo esc_html($group_title); ?></strong>
                                                </td>
                                                <td class="col-chatid">
                                                    <code class="milmit-chatid-tag" title="<?php esc_attr_e('Click to copy', 'telenexa-commerce-for-telegram'); ?>"><?php echo esc_html($cid); ?></code>
                                                </td>
                                                <td class="col-date">
                                                    <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $grp->post_date)); ?>
                                                </td>
                                                <td style="text-align: left;">
                                                    <a href="<?php echo esc_url($delete_url); ?>" class="milmit-btn-action-delete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this group?', 'telenexa-commerce-for-telegram'); ?>');" title="<?php esc_attr_e('Delete', 'telenexa-commerce-for-telegram'); ?>">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="milmit-empty-state">
                                <span class="dashicons dashicons-groups"></span>
                                <h3><?php esc_html_e('No Groups Found', 'telenexa-commerce-for-telegram'); ?></h3>
                                <p><?php esc_html_e('When the bot is added to Telegram groups, they will be listed here.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($current_tab === 'logs'): ?>
            <!-- ========================================================= -->
            <!-- TAB 4: ACTIVITY LOGS & DIAGNOSTICS                       -->
            <!-- ========================================================= -->
            <div class="milmit-card">
                <div class="milmit-card-header">
                    <div class="milmit-card-header-title">
                        <span class="dashicons dashicons-list-view"></span>
                        <h3><?php esc_html_e('System Activity & Logs', 'telenexa-commerce-for-telegram'); ?></h3>
                    </div>

                    <div class="milmit-card-header-actions">
                        <?php if (!empty($logs)): 
                            $clear_url = wp_nonce_url(add_query_arg(array('page' => 'woogram_main', 'tab' => 'logs', 'action' => 'clear_logs'), admin_url('admin.php')), 'clear_bot_logs');
                        ?>
                            <a href="<?php echo esc_url($clear_url); ?>" class="milmit-btn-danger" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all activity logs?', 'telenexa-commerce-for-telegram'); ?>');">
                                <span class="dashicons dashicons-trash"></span>
                                <?php esc_html_e('Clear All Logs', 'telenexa-commerce-for-telegram'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="milmit-card-body p-0">
                    <?php if (!empty($logs)): ?>
                        <div class="milmit-table-responsive">
                            <table class="milmit-data-table">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;"><?php esc_html_e('Event Type', 'telenexa-commerce-for-telegram'); ?></th>
                                        <th style="width: 160px;"><?php esc_html_e('Date & Time', 'telenexa-commerce-for-telegram'); ?></th>
                                        <th style="width: 140px;"><?php esc_html_e('Chat ID', 'telenexa-commerce-for-telegram'); ?></th>
                                        <th><?php esc_html_e('Description', 'telenexa-commerce-for-telegram'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): 
                                        $type = isset($log[0]) ? esc_html($log[0]) : 'INFO';
                                        $date = isset($log[1]) ? esc_html($log[1]) : '';
                                        $cid  = isset($log[2]) ? esc_html($log[2]) : '';
                                        $desc = isset($log[3]) ? esc_html($log[3]) : '';
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="milmit-log-badge <?php echo esc_attr(strtolower($type)); ?>"><?php echo esc_html($type); ?></span>
                                            </td>
                                            <td><?php echo esc_html($date); ?></td>
                                            <td>
                                                <?php if (!empty($cid)): ?>
                                                    <code><?php echo esc_html($cid); ?></code>
                                                <?php else: ?>
                                                    <span class="milmit-text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo esc_html($desc); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="milmit-empty-state">
                            <span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span>
                            <h3><?php esc_html_e('Log is Clean', 'telenexa-commerce-for-telegram'); ?></h3>
                            <p><?php esc_html_e('There are no errors or recorded events in the activity log.', 'telenexa-commerce-for-telegram'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>

    </main>
</div>

