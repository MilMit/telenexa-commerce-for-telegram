<?php
if (!defined('ABSPATH')) exit;
// Included by woogram_render_settings_panel(); these are view-local variables.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if (defined('WOOGRAM_SETTINGS_PANEL_LOADED')) {
    return;
}
define('WOOGRAM_SETTINGS_PANEL_LOADED', true);

woogram_footer_text_call();

$tabs = array(
    'basic'         => array('name' => __('Basic', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-admin-generic', 'desc' => __('Connection & Security', 'telenexa-commerce-for-telegram')),
    'menu'          => array('name' => __('Main Menu', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-menu', 'desc' => __('Buttons & Custom Actions', 'telenexa-commerce-for-telegram')),
    'search'        => array('name' => __('Search & Filters', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-search', 'desc' => __('SKU, Price, Stock & Sorting', 'telenexa-commerce-for-telegram')),
    'display'       => array('name' => __('Catalog & Products', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-layout', 'desc' => __('Gallery, Reviews, Badges', 'telenexa-commerce-for-telegram')),
    'order'         => array('name' => __('Cart & Checkout', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-cart', 'desc' => __('Stock check, Tax, Shipping, Locks', 'telenexa-commerce-for-telegram')),
    'notifications' => array('name' => __('Notifications', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-bell', 'desc' => __('Order alerts, Stock & Price drops', 'telenexa-commerce-for-telegram')),
    'membership'    => array('name' => __('Required Membership', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-groups', 'desc' => __('Channels & Groups Access Gate', 'telenexa-commerce-for-telegram')),
    'webapp'        => array('name' => __('Mini App', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-smartphone', 'desc' => __('Telegram Web App settings', 'telenexa-commerce-for-telegram')),
    'marketing'     => array('name' => __('Marketing & Backup', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-awards', 'desc' => __('Referrals, Campaigns, Export/Import', 'telenexa-commerce-for-telegram')),
    'messages'      => array('name' => __('Messages & WPML', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-format-chat', 'desc' => __('Texts & Translations', 'telenexa-commerce-for-telegram')),
    'proxy'         => array('name' => __('Proxy', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-shield', 'desc' => __('Connection Proxy Settings', 'telenexa-commerce-for-telegram')),
    'commands'      => array('name' => __('Commands', 'telenexa-commerce-for-telegram'), 'icon' => 'dashicons-editor-help', 'desc' => __('BotFather Menu Guide', 'telenexa-commerce-for-telegram'))
);

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation; no state is changed.
$requested_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
$current = isset($tabs[$requested_tab]) ? $requested_tab : 'basic';
$options = get_option('woogram_settings', array());
$bot_token = isset($options['token']) ? trim($options['token']) : '';
$bot_username = isset($options['username']) ? trim($options['username']) : '';
$is_test_mode = isset($options['testmod']) && $options['testmod'] === 'yes';
$ssl_active = is_ssl();
?>

<div class="wrap milmit-settings-wrap" dir="rtl">
    <!-- Header Branding -->
    <header class="milmit-header">
        <div class="milmit-header-main">
            <div class="milmit-brand-badge">
                <div class="milmit-logo-icon" style="background:transparent;padding:0;display:flex;align-items:center;justify-content:center;">
                    <img src="<?php echo esc_url(plugins_url('img/woogram-icon.svg', dirname(__DIR__) . '/telenexa.php')); ?>" width="34" height="34" alt="TeleNexa" style="border-radius:8px;box-shadow:0 3px 10px rgba(34,158,217,0.35);display:block;">
                </div>
                <div class="milmit-brand-titles">
                    <div class="milmit-title-row">
                        <h1 class="milmit-plugin-title"><?php esc_html_e('TeleNexa Commerce for Telegram', 'telenexa-commerce-for-telegram'); ?></h1>
                        <span class="milmit-version-badge"><?php // translators: %s: Plugin version number
printf(esc_html__('Version %s', 'telenexa-commerce-for-telegram'), '1.0.0'); ?></span>
                        <a href="https://milmit.net" target="_blank" class="milmit-domain-badge">milmit.net</a>
                    </div>
                    <p class="milmit-subtitle"><?php esc_html_e('Comprehensive settings, smart communication management and direct sales of WooCommerce products in Telegram', 'telenexa-commerce-for-telegram'); ?></p>
                </div>
            </div>

            <div class="milmit-header-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=woogram_main')); ?>" class="milmit-btn-ghost">
                    <span class="dashicons dashicons-dashboard"></span>
                    <?php esc_html_e('Dashboard', 'telenexa-commerce-for-telegram'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woogram_send')); ?>" class="milmit-btn-ghost milmit-btn-accent">
                    <span class="dashicons dashicons-megaphone"></span>
                    <?php esc_html_e('Broadcast Message', 'telenexa-commerce-for-telegram'); ?>
                </a>
                <a href="https://milmit.net" target="_blank" class="milmit-btn-ghost">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e('Support Website', 'telenexa-commerce-for-telegram'); ?>
                </a>
            </div>
        </div>

        <!-- Quick Status Bar -->
        <div class="milmit-status-bar">
            <div class="milmit-status-item">
                <span class="milmit-status-label"><?php esc_html_e('Bot Token Status:', 'telenexa-commerce-for-telegram'); ?></span>
                <?php if (!empty($bot_token)): ?>
                    <span class="milmit-pill milmit-pill-success">
                        <span class="milmit-indicator"></span> <?php esc_html_e('Connected', 'telenexa-commerce-for-telegram'); ?>
                        <?php if (!empty($bot_username)): ?>(@<?php echo esc_html($bot_username); ?>)<?php endif; ?>
                    </span>
                <?php else: ?>
                    <span class="milmit-pill milmit-pill-danger">
                        <span class="milmit-indicator"></span> <?php esc_html_e('No Token Entered', 'telenexa-commerce-for-telegram'); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="milmit-status-item">
                <span class="milmit-status-label"><?php esc_html_e('Server SSL Status:', 'telenexa-commerce-for-telegram'); ?></span>
                <?php if ($ssl_active): ?>
                    <span class="milmit-pill milmit-pill-success"><span class="milmit-indicator"></span> <?php esc_html_e('Active (Direct Webhook Ready)', 'telenexa-commerce-for-telegram'); ?></span>
                <?php else: ?>
                    <span class="milmit-pill milmit-pill-warning"><span class="milmit-indicator"></span> <?php esc_html_e('Inactive (Recommended: milmit.net webhook)', 'telenexa-commerce-for-telegram'); ?></span>
                <?php endif; ?>
            </div>

            <div class="milmit-status-item">
                <span class="milmit-status-label"><?php esc_html_e('Test Mode:', 'telenexa-commerce-for-telegram'); ?></span>
                <?php if ($is_test_mode): ?>
                    <span class="milmit-pill milmit-pill-warning"><span class="milmit-indicator"></span> <?php esc_html_e('Enabled (Full Logging)', 'telenexa-commerce-for-telegram'); ?></span>
                <?php else: ?>
                    <span class="milmit-pill milmit-pill-neutral"><?php esc_html_e('Disabled', 'telenexa-commerce-for-telegram'); ?></span>
                <?php endif; ?>
            </div>

            <div class="milmit-status-item milmit-status-item-right">
                <span class="milmit-status-label"><?php esc_html_e('Reference Website:', 'telenexa-commerce-for-telegram'); ?></span>
                <a href="https://milmit.net" target="_blank" class="milmit-status-link">milmit.net</a>
            </div>
        </div>
    </header>

    <!-- Notices & Alerts -->
    <?php
    if ($is_test_mode) {
        echo '<div class="milmit-alert milmit-alert-warning">
            <span class="dashicons dashicons-warning"></span>
            <div><strong>' . esc_html__('Bot test mode is active:', 'telenexa-commerce-for-telegram') . '</strong> ' . esc_html__('In this mode, error logging is enabled and performance may experience delays. Please disable it when testing is complete.', 'telenexa-commerce-for-telegram') . '</div>
        </div>';
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress supplies this read-only settings status flag.
    if (isset($_GET['settings-updated']) && sanitize_key(wp_unslash($_GET['settings-updated'])) !== '') {

        echo '<div class="milmit-alert milmit-alert-success"><span class="dashicons dashicons-saved"></span><div>' . esc_html__('Setting updated.', 'telenexa-commerce-for-telegram') . '</div></div>';
        $sync = (array) get_option('woogram_webhook_sync_status', []);
        if ($sync) echo '<p>' . esc_html__('Webhook Result:', 'telenexa-commerce-for-telegram') . ' ' . esc_html(!empty($sync['ok']) ? 'OK' : 'Failed') . ' — ' . esc_html($sync['checked_at'] ?? '') . '</p>';
    }
    settings_errors('woogram_settings');
    echo '<p><a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=woogram_register_webhook'), 'woogram_register_webhook')) . '">' . esc_html__('Register webhook again', 'telenexa-commerce-for-telegram') . '</a></p>';
    ?>

    <!-- Keep Import outside the settings form; nested forms prevent Save from submitting. -->
    <form id="woogram_import_settings_form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin.php?page=woogram_settings')); ?>"></form>

    <!-- Main Settings Form -->
    <form method="post" action="options.php" id="milmit_settings_form" class="milmit-form">
        <?php settings_fields('woogram_settings_options'); ?>

        <div class="milmit-layout">
            <!-- Sidebar Navigation Tabs -->
            <aside class="milmit-sidebar">
                <nav class="milmit-tabs-nav" role="tablist">
                    <?php foreach ($tabs as $key => $tab): 
                        $active = ($key === $current);
                    ?>
                        <button type="button" 
                                class="milmit-tab-btn <?php echo $active ? 'is-active' : ''; ?>" 
                                data-tab="<?php echo esc_attr($key); ?>"
                                role="tab" 
                                aria-selected="<?php echo $active ? 'true' : 'false'; ?>">
                            <span class="dashicons <?php echo esc_attr($tab['icon']); ?> milmit-tab-icon"></span>
                            <div class="milmit-tab-info">
                                <span class="milmit-tab-title"><?php echo esc_html($tab['name']); ?></span>
                                <span class="milmit-tab-desc"><?php echo esc_html($tab['desc']); ?></span>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </nav>

                <div class="milmit-sidebar-card">
                    <div class="milmit-sidebar-card-title">
                        <span class="dashicons dashicons-admin-site-alt3"></span>
                        <?php esc_html_e('Reference & Support', 'telenexa-commerce-for-telegram'); ?>
                    </div>
                    <p class="milmit-sidebar-card-desc"><?php esc_html_e('Dedicated Telegram bot site and latest WooCommerce updates & plugins', 'telenexa-commerce-for-telegram'); ?></p>
                    <a href="https://milmit.net" target="_blank" class="milmit-link-btn"><?php esc_html_e('Visit milmit.net &rarr;', 'telenexa-commerce-for-telegram'); ?></a>
                </div>
            </aside>

            <!-- Main Content Area -->
            <main class="milmit-content">
                
                <!-- ========================================================================= -->
                <!-- TAB 1: BASIC SETTINGS -->
                <!-- ========================================================================= -->
                <section class="milmit-tab-pane <?php echo ($current === 'basic') ? 'is-active' : ''; ?>" id="tab-basic" role="tabpanel">
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-admin-generic milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Basic settings', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle"><?php esc_html_e('Bot connection info, access keys, and basic configuration', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>

                        <div class="milmit-card-body">
                            <!-- Bot Token -->
                            <div class="milmit-field-group">
                                <div class="milmit-field-header">
                                    <label for="token" class="milmit-label"><?php esc_html_e('Bot Token', 'telenexa-commerce-for-telegram'); ?> <span class="milmit-required">*</span></label>
                                    <span class="milmit-badge milmit-badge-secret"><?php esc_html_e('Keep confidential', 'telenexa-commerce-for-telegram'); ?></span>
                                </div>
                                <div class="milmit-input-addon-wrap">
                                    <input id="token" 
                                           type="password" 
                                           name="woogram_settings[token]" 
                                           value="<?php echo isset($options['token']) ? esc_attr($options['token']) : ''; ?>" 
                                           placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ..." 
                                           class="milmit-input milmit-ltr" 
                                           autocomplete="off" />
                                    <button type="button" class="milmit-addon-btn milmit-toggle-token" title="<?php esc_attr_e('Show/Hide token', 'telenexa-commerce-for-telegram'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                </div>
                                <p class="milmit-help-text">
                                    <?php // translators: %s: Link to @BotFather
echo wp_kses_post(sprintf(__('Get the Telegram bot connection token from %s and enter it here.', 'telenexa-commerce-for-telegram'), '<a href="https://t.me/BotFather" target="_blank" rel="noopener noreferrer" class="milmit-link">@BotFather</a>')); ?>
                                </p>
                            </div>

                            <!-- Bot Username -->
                            <div class="milmit-field-group">
                                <label for="bot_username_input" class="milmit-label"><?php esc_html_e('Bot Username', 'telenexa-commerce-for-telegram'); ?> <span class="milmit-required">*</span></label>
                                <div class="milmit-input-addon-wrap">
                                    <span class="milmit-addon-prefix">@</span>
                                    <input id="bot_username_input" 
                                           type="text" 
                                           name="woogram_settings[username]" 
                                           value="<?php echo isset($options['username']) ? esc_attr($options['username']) : ''; ?>" 
                                           placeholder="milmit_bot" 
                                           class="milmit-input milmit-ltr" />
                                </div>
                                <p class="milmit-help-text"><?php // translators: %s: Example username code snippet
echo wp_kses_post(sprintf(__('Telegram bot username without @ sign (e.g. %s)', 'telenexa-commerce-for-telegram'), '<code>milmit_bot</code>')); ?></p>
                            </div>

                            <!-- Channel Username -->
                            <div class="milmit-field-group">
                                <label for="channelusername" class="milmit-label"><?php esc_html_e('Channel Username', 'telenexa-commerce-for-telegram'); ?></label>
                                <div class="milmit-input-addon-wrap">
                                    <span class="milmit-addon-prefix">@</span>
                                    <input id="channelusername" 
                                           type="text" 
                                           name="woogram_settings[channelusername]" 
                                           value="<?php echo isset($options['channelusername']) ? esc_attr($options['channelusername']) : ''; ?>" 
                                           placeholder="my_channel" 
                                           class="milmit-input milmit-ltr" />
                                </div>
                                <p class="milmit-help-text">
                                    <?php esc_html_e('Your shop channel ID (optional). To send products or broadcasts automatically, the bot must be a channel Administrator.', 'telenexa-commerce-for-telegram'); ?>
                                </p>
                            </div>

                            <!-- Connection Mode -->
                            <div class="milmit-field-group">
                                <label class="milmit-label"><?php esc_html_e('Connection Mode', 'telenexa-commerce-for-telegram'); ?></label>
                                <div class="milmit-radio-cards">
                                    <!-- Option 0: Direct Webhook -->
                                    <label class="milmit-radio-card <?php echo (!$ssl_active ? 'is-disabled' : ''); ?> <?php echo (isset($options['mode']) && $options['mode'] == 0 && $ssl_active) ? 'is-selected' : ''; ?>">
                                        <input type="radio" 
                                               name="woogram_settings[mode]" 
                                               value="0" 
                                               <?php if (!$ssl_active) echo 'disabled'; ?>
                                               <?php echo (isset($options['mode']) && $options['mode'] == 0 && $ssl_active) ? 'checked="checked"' : ''; ?>>
                                        <div class="milmit-radio-card-content">
                                            <div class="milmit-radio-card-title">
                                                <span class="dashicons dashicons-rest-api"></span>
                                                وب‌هوک مستقیم تلگرام (Direct Webhook)
                                                <?php if ($ssl_active): ?>
                                                    <span class="milmit-chip milmit-chip-success">پیشنهادی</span>
                                                <?php else: ?>
                                                    <span class="milmit-chip milmit-chip-danger">نیازمند SSL</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="milmit-radio-card-desc">اتصال مستقیم تلگرام به وب‌سایت شما با بیشترین سرعت. نیازمند گواهی معتبر SSL (HTTPS) بر روی دامنه سایت است.</p>
                                        </div>
                                    </label>

                                    <!-- Option 1: MilMit Gateway -->
                                    <label class="milmit-radio-card <?php echo (!isset($options['mode']) || $options['mode'] == 1 || !$ssl_active) ? 'is-selected' : ''; ?>">
                                        <input type="radio" 
                                               name="woogram_settings[mode]" 
                                               value="1" 
                                               <?php echo (!isset($options['mode']) || $options['mode'] == 1 || !$ssl_active) ? 'checked="checked"' : ''; ?>>
                                        <div class="milmit-radio-card-content">
                                            <div class="milmit-radio-card-title">
                                                <span class="dashicons dashicons-cloud"></span>
                                                وب‌هوک واسط milmit.net (بدون نیاز به SSL)
                                                <span class="milmit-chip milmit-chip-info">milmit.net</span>
                                            </div>
                                            <p class="milmit-radio-card-desc">
                                                اگر هاست یا دامنه شما SSL ندارد، درخواست‌های وب‌هوک توسط گیت‌وی ایمن سرور milmit.net به سایت شما منتقل می‌شود. هیچ‌گونه اطلاعاتی در سرور ذخیره نمی‌شود.
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Target Broadcast -->
                            <div class="milmit-field-group">
                                <label for="broadcast_target" class="milmit-label"><?php esc_html_e('Default content broadcast', 'telenexa-commerce-for-telegram'); ?></label>
                                <select id="broadcast_target" name="woogram_settings[target]" class="milmit-select">
                                    <option value="0" <?php echo (isset($options['target']) && $options['target'] == 0) ? 'selected="selected"' : ''; ?>><?php esc_html_e('Users, Groups, Channel', 'telenexa-commerce-for-telegram'); ?> (همه)</option>
                                    <option value="1" <?php echo (isset($options['target']) && $options['target'] == 1) ? 'selected="selected"' : ''; ?>><?php esc_html_e('Users', 'telenexa-commerce-for-telegram'); ?> (کاربران)</option>
                                    <option value="2" <?php echo (isset($options['target']) && $options['target'] == 2) ? 'selected="selected"' : ''; ?>><?php esc_html_e('Groups', 'telenexa-commerce-for-telegram'); ?> (گروه‌ها)</option>
                                    <option value="3" <?php echo (isset($options['target']) && $options['target'] == 3) ? 'selected="selected"' : ''; ?>><?php esc_html_e('Users, Groups', 'telenexa-commerce-for-telegram'); ?> (کاربران و گروه‌ها)</option>
                                    <option value="4" <?php echo (!isset($options['target']) || $options['target'] == 4) ? 'selected="selected"' : ''; ?>><?php esc_html_e('Channel', 'telenexa-commerce-for-telegram'); ?> (فقط کانال)</option>
                                </select>
                                <p class="milmit-help-text"><?php esc_html_e('Default destination when broadcasting posts and products.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>

                            <!-- Enable in groups -->
                            <div class="milmit-field-group milmit-field-inline">
                                <div>
                                    <label class="milmit-label"><?php esc_html_e('Bot enabled in groups', 'telenexa-commerce-for-telegram'); ?></label>
                                    <p class="milmit-help-text"><?php esc_html_e('Allow users to add the bot to Telegram groups?', 'telenexa-commerce-for-telegram'); ?></p>
                                </div>
                                <div class="milmit-toggle-wrapper">
                                    <label class="milmit-switch">
                                        <input type="radio" name="woogram_settings[enabledingroups]" value="1" <?php echo (isset($options['enabledingroups']) && $options['enabledingroups'] == 1) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-toggle-opt"><?php esc_html_e('Yes', 'telenexa-commerce-for-telegram'); ?></span>
                                    </label>
                                    <label class="milmit-switch">
                                        <input type="radio" name="woogram_settings[enabledingroups]" value="0" <?php echo (!isset($options['enabledingroups']) || $options['enabledingroups'] == 0) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-toggle-opt"><?php esc_html_e('No', 'telenexa-commerce-for-telegram'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Test Mode -->
                            <div class="milmit-field-group milmit-field-inline">
                                <div>
                                    <label class="milmit-label"><?php esc_html_e('Bot test mode', 'telenexa-commerce-for-telegram'); ?></label>
                                    <p class="milmit-help-text"><?php esc_html_e('For testing send and receive message. After testing please off it.', 'telenexa-commerce-for-telegram'); ?></p>
                                </div>
                                <div class="milmit-toggle-wrapper">
                                    <label class="milmit-switch">
                                        <input type="radio" name="woogram_settings[testmod]" value="yes" <?php echo ($is_test_mode) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-toggle-opt milmit-toggle-opt-warn"><?php esc_html_e('On', 'telenexa-commerce-for-telegram'); ?></span>
                                    </label>
                                    <label class="milmit-switch">
                                        <input type="radio" name="woogram_settings[testmod]" value="no" <?php echo (!$is_test_mode) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-toggle-opt"><?php esc_html_e('Off', 'telenexa-commerce-for-telegram'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Webhook Secret Token -->
                            <div class="milmit-field-group">
                                <div class="milmit-field-header">
                                    <label for="webhook_secret_token" class="milmit-label"><?php esc_html_e('Webhook Secret Token', 'telenexa-commerce-for-telegram'); ?></label>
                                    <span class="milmit-badge milmit-badge-secret"><?php esc_html_e('High Security', 'telenexa-commerce-for-telegram'); ?></span>
                                </div>
                                <div class="milmit-input-addon-wrap">
                                    <input id="webhook_secret_token" 
                                           type="text" 
                                           name="woogram_settings[webhook_secret_token]" 
                                           value="<?php echo isset($options['webhook_secret_token']) ? esc_attr($options['webhook_secret_token']) : ''; ?>" 
                                           placeholder="a-z A-Z 0-9 _-" 
                                           class="milmit-input milmit-ltr" />
                                    <button type="button" class="milmit-btn-ghost milmit-btn-sm" id="btn_generate_secret">
                                        <?php esc_html_e('Generate Secret', 'telenexa-commerce-for-telegram'); ?>
                                    </button>
                                </div>
                                <p class="milmit-help-text">
                                    <?php esc_html_e('Secret token sent by Telegram in X-Telegram-Bot-Api-Secret-Token header. Protects webhook from spoofing.', 'telenexa-commerce-for-telegram'); ?>
                                </p>
                            </div>

                            <!-- Rate Limiting -->
                            <div class="milmit-field-group milmit-field-inline">
                                <div>
                                    <label class="milmit-label"><?php esc_html_e('Flood & Rate Limiting Protection', 'telenexa-commerce-for-telegram'); ?></label>
                                    <p class="milmit-help-text"><?php esc_html_e('Prevent spam, bot attacks and server overload by throttling user requests.', 'telenexa-commerce-for-telegram'); ?></p>
                                </div>
                                <div class="milmit-toggle-wrapper">
                                    <label class="milmit-switch">
                                        <input type="radio" name="woogram_settings[security_rate_limit_enable]" value="1" <?php checked(isset($options['security_rate_limit_enable']) ? $options['security_rate_limit_enable'] : 1, 1); ?>>
                                        <span class="milmit-toggle-opt milmit-toggle-opt-accent"><?php esc_html_e('Enabled', 'telenexa-commerce-for-telegram'); ?></span>
                                    </label>
                                    <label class="milmit-switch">
                                        <input type="radio" name="woogram_settings[security_rate_limit_enable]" value="0" <?php checked(isset($options['security_rate_limit_enable']) ? $options['security_rate_limit_enable'] : 1, 0); ?>>
                                        <span class="milmit-toggle-opt"><?php esc_html_e('Disabled', 'telenexa-commerce-for-telegram'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Connection Testing Tools -->
                            <div class="milmit-field-group" style="background: var(--surface-subtle); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-subtle);">
                                <label class="milmit-label" style="margin-bottom: 8px;"><?php esc_html_e('Connection Diagnostics & Health Check', 'telenexa-commerce-for-telegram'); ?></label>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <button type="button" class="milmit-btn-ghost" id="btn_test_telegram_connection">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php esc_html_e('Test Bot Connection (getMe)', 'telenexa-commerce-for-telegram'); ?>
                                    </button>
                                    <button type="button" class="milmit-btn-ghost" id="btn_test_telegram_webhook">
                                        <span class="dashicons dashicons-cloud"></span>
                                        <?php esc_html_e('Test Webhook Status (getWebhookInfo)', 'telenexa-commerce-for-telegram'); ?>
                                    </button>
                                    <button type="button" class="milmit-btn-ghost" id="btn_run_system_tests">
                                        <span class="dashicons dashicons-shield"></span>
                                        <?php esc_html_e('Run System Unit Tests', 'telenexa-commerce-for-telegram'); ?>
                                    </button>
                                </div>
                                <div id="milmit_diag_result" style="display: none; margin-top: 12px; padding: 12px; border-radius: var(--radius-sm); font-size: 13px;"></div>
                            </div>


                        </div>
                    </div>
                </section>

                <!-- ========================================================================= -->
                <!-- TAB: REQUIRED MEMBERSHIP                                                   -->
                <!-- ========================================================================= -->
                <section class="milmit-tab-pane <?php echo ($current === 'membership') ? 'is-active' : ''; ?>" id="tab-membership" role="tabpanel">
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-groups milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Required Membership', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle"><?php esc_html_e('Ask users to join selected Telegram channels or groups before using the store.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>
                        <div class="milmit-card-body">
                            <div class="milmit-membership-intro">
                                <span class="dashicons dashicons-lock"></span>
                                <div>
                                    <strong><?php esc_html_e('A professional access gate for your community', 'telenexa-commerce-for-telegram'); ?></strong>
                                    <p><?php esc_html_e('The bot checks every private user with Telegram getChatMember. The bot must be an administrator in private channels/groups for the check to work reliably.', 'telenexa-commerce-for-telegram'); ?></p>
                                </div>
                            </div>

                            <input type="hidden" name="woogram_settings[membership_enabled]" value="0" />
                            <label class="milmit-checkbox-row">
                                <input type="checkbox" name="woogram_settings[membership_enabled]" value="1" <?php checked($options['membership_enabled'] ?? 0, 1); ?> />
                                <span><strong><?php esc_html_e('Enable required membership', 'telenexa-commerce-for-telegram'); ?></strong><small><?php esc_html_e('Users who have not joined all enabled channels/groups will see the join screen instead of the store.', 'telenexa-commerce-for-telegram'); ?></small></span>
                            </label>

                            <input type="hidden" name="woogram_settings[membership_apply_to_admins]" value="0" />
                            <label class="milmit-checkbox-row">
                                <input type="checkbox" name="woogram_settings[membership_apply_to_admins]" value="1" <?php checked($options['membership_apply_to_admins'] ?? 1, 1); ?> />
                                <span><strong><?php esc_html_e('Apply to administrators too', 'telenexa-commerce-for-telegram'); ?></strong><small><?php esc_html_e('Telegram administrators and store admins must also pass the membership check.', 'telenexa-commerce-for-telegram'); ?></small></span>
                            </label>

                            <div class="milmit-membership-list" id="woogram_membership_list">
                                <?php
                                $membership_rows = is_array($options['membership_requirements'] ?? null) ? $options['membership_requirements'] : [];
                                if (!$membership_rows) $membership_rows = [['enabled' => '0', 'type' => 'channel', 'title' => '', 'chat_id' => '', 'url' => '']];
                                foreach ($membership_rows as $index => $requirement):
                                    $index = (int) $index;
                                ?>
                                    <div class="milmit-membership-row" data-membership-row>
                                        <div class="milmit-membership-row-head">
                                            <span class="milmit-membership-number"><?php echo esc_html($index + 1); ?></span>
                                            <strong><?php esc_html_e('Required destination', 'telenexa-commerce-for-telegram'); ?></strong>
                                            <button type="button" class="button-link-delete milmit-remove-membership" <?php echo count($membership_rows) <= 1 ? 'style="display:none"' : ''; ?>><?php esc_html_e('Remove', 'telenexa-commerce-for-telegram'); ?></button>
                                        </div>
                                        <div class="milmit-membership-fields">
                                            <label class="milmit-label"><?php esc_html_e('Title', 'telenexa-commerce-for-telegram'); ?><input type="text" name="woogram_settings[membership_requirements][<?php echo esc_attr($index); ?>][title]" value="<?php echo esc_attr($requirement['title'] ?? ''); ?>" placeholder="<?php esc_attr_e('Main channel', 'telenexa-commerce-for-telegram'); ?>" class="milmit-input" /></label>
                                            <label class="milmit-label"><?php esc_html_e('Type', 'telenexa-commerce-for-telegram'); ?><select name="woogram_settings[membership_requirements][<?php echo esc_attr($index); ?>][type]" class="milmit-select"><option value="channel" <?php selected($requirement['type'] ?? 'channel', 'channel'); ?>><?php esc_html_e('Channel', 'telenexa-commerce-for-telegram'); ?></option><option value="group" <?php selected($requirement['type'] ?? '', 'group'); ?>><?php esc_html_e('Group', 'telenexa-commerce-for-telegram'); ?></option></select></label>
                                            <label class="milmit-label"><?php esc_html_e('Username or Chat ID', 'telenexa-commerce-for-telegram'); ?><input type="text" name="woogram_settings[membership_requirements][<?php echo esc_attr($index); ?>][chat_id]" value="<?php echo esc_attr($requirement['chat_id'] ?? ''); ?>" placeholder="@my_channel or -1001234567890" class="milmit-input milmit-ltr" /></label>
                                            <label class="milmit-label milmit-membership-url"><?php esc_html_e('Join URL', 'telenexa-commerce-for-telegram'); ?><input type="url" name="woogram_settings[membership_requirements][<?php echo esc_attr($index); ?>][url]" value="<?php echo esc_attr($requirement['url'] ?? ''); ?>" placeholder="https://t.me/my_channel" class="milmit-input milmit-ltr" /></label>
                                        </div>
                                        <label class="milmit-membership-enabled"><input type="hidden" name="woogram_settings[membership_requirements][<?php echo esc_attr($index); ?>][enabled]" value="0" /><input type="checkbox" name="woogram_settings[membership_requirements][<?php echo esc_attr($index); ?>][enabled]" value="1" <?php checked($requirement['enabled'] ?? 0, 1); ?> /> <?php esc_html_e('Enable this destination', 'telenexa-commerce-for-telegram'); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="button" id="woogram_add_membership"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Add channel or group', 'telenexa-commerce-for-telegram'); ?></button>
                            <p class="milmit-help-text"><?php esc_html_e('For a public channel, enter its @username. For a private channel or group, enter the numeric Chat ID and provide an invite URL.', 'telenexa-commerce-for-telegram'); ?></p>
                        </div>
                    </div>
                </section>

                <!-- ========================================================================= -->
                <!-- TAB 2: MESSAGES SETTINGS -->
                <!-- ========================================================================= -->
                <section class="milmit-tab-pane <?php echo ($current === 'messages') ? 'is-active' : ''; ?>" id="tab-messages" role="tabpanel">
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-format-chat milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Messages settings', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle"><?php esc_html_e('Automated message texts, menus, descriptions and bot signature', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>

                        <div class="milmit-card-body">
                            <!-- Variables toolbar -->
                            <div class="milmit-variables-box">
                                <div class="milmit-variables-title">
                                    <span class="dashicons dashicons-tag"></span>
                                    <?php esc_html_e('Dynamic variables available (click to insert into text):', 'telenexa-commerce-for-telegram'); ?>
                                </div>
                                <div class="milmit-variable-chips">
                                    <button type="button" class="milmit-chip-btn" data-insert="%FIRST_NAME%">%FIRST_NAME% <small>(<?php esc_html_e('First Name', 'telenexa-commerce-for-telegram'); ?> کاربر)</small></button>
                                    <button type="button" class="milmit-chip-btn" data-insert="%LAST_NAME%">%LAST_NAME% <small>(<?php esc_html_e('Last Name', 'telenexa-commerce-for-telegram'); ?>)</small></button>
                                    <button type="button" class="milmit-chip-btn" data-insert="%USERNAME%">%USERNAME% <small>(یوزرنیم تلگرام)</small></button>
                                </div>
                            </div>

                            <!-- Language settings -->
                            <div class="milmit-field-group">
                                <label for="default_language" class="milmit-label"><?php esc_html_e('Default bot language', 'telenexa-commerce-for-telegram'); ?></label>
                                <select id="default_language" name="woogram_settings[default_language]" class="milmit-input">
                                    <?php
                                    $language_options = [
                                        'fa' => __('Persian (فارسی)', 'telenexa-commerce-for-telegram'),
                                        'en' => __('English', 'telenexa-commerce-for-telegram'),
                                        'de' => __('German (Deutsch)', 'telenexa-commerce-for-telegram'),
                                        'es' => __('Spanish (Español)', 'telenexa-commerce-for-telegram'),
                                        'fr' => __('French (Français)', 'telenexa-commerce-for-telegram'),
                                        'it' => __('Italian (Italiano)', 'telenexa-commerce-for-telegram'),
                                        'pt' => __('Portuguese (Português)', 'telenexa-commerce-for-telegram'),
                                        'tr' => __('Turkish (Türkçe)', 'telenexa-commerce-for-telegram'),
                                        'ar' => __('Arabic (العربية)', 'telenexa-commerce-for-telegram'),
                                        'ru' => __('Russian (Русский)', 'telenexa-commerce-for-telegram'),
                                        'zh' => __('Chinese (中文)', 'telenexa-commerce-for-telegram'),
                                        'ja' => __('Japanese (日本語)', 'telenexa-commerce-for-telegram'),
                                    ];
                                    $default_language = isset($options['default_language']) ? (string) $options['default_language'] : 'fa';
                                    foreach ($language_options as $language_code => $language_label) :
                                        ?>
                                        <option value="<?php echo esc_attr($language_code); ?>" <?php selected($default_language, $language_code); ?>><?php echo esc_html($language_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="milmit-help-text"><?php esc_html_e('Used for new Telegram users until they choose another language.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>

                            <div class="milmit-field-group">
                                <label class="milmit-checkbox-label">
                                    <input type="hidden" name="woogram_settings[wpml_language_sync]" value="0" />
                                    <input type="checkbox" name="woogram_settings[wpml_language_sync]" value="1" <?php checked((string) ($options['wpml_language_sync'] ?? '1'), '1'); ?> />
                                    <?php esc_html_e('Synchronize the bot language with the active WPML language', 'telenexa-commerce-for-telegram'); ?>
                                </label>
                                <p class="milmit-help-text"><?php esc_html_e('When enabled, WPML can translate dynamic bot messages. A user-selected Telegram language still has priority.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>

                            <!-- Start Message -->
                            <div class="milmit-field-group">
                                <label for="wmuser" class="milmit-label"><?php esc_html_e('Start Message', 'telenexa-commerce-for-telegram'); ?> <code>/start</code></label>
                                <textarea rows="4" 
                                          id="wmuser" 
                                          name="woogram_settings[wmuser]" 
                                          data-emojiable="true" 
                                          class="milmit-textarea"><?php echo isset($options['wmuser']) ? esc_textarea(woogram_fix_emoji_input($options['wmuser'], 0)) : ''; ?></textarea>
                                <p class="milmit-help-text"><?php esc_html_e('Message displayed to the user after clicking Start.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>

                            <!-- Bye Message -->
                            <div class="milmit-field-group">
                                <label for="bmuser" class="milmit-label"><?php esc_html_e('Bye Message', 'telenexa-commerce-for-telegram'); ?> <code>/stop</code></label>
                                <textarea rows="3" 
                                          id="bmuser" 
                                          name="woogram_settings[bmuser]" 
                                          data-emojiable="true" 
                                          class="milmit-textarea"><?php echo isset($options['bmuser']) ? esc_textarea(woogram_fix_emoji_input($options['bmuser'], 0)) : ''; ?></textarea>
                                <p class="milmit-help-text"><?php esc_html_e('Goodbye message when a user leaves or unsubscribes.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>

                            <!-- Home Message -->
                            <div class="milmit-field-group">
                                <label for="messagehome" class="milmit-label"><?php esc_html_e('Home message', 'telenexa-commerce-for-telegram'); ?> <code>/home</code></label>
                                <textarea rows="3" 
                                          id="messagehome" 
                                          name="woogram_settings[messagehome]" 
                                          data-emojiable="true" 
                                          class="milmit-textarea"><?php echo isset($options['messagehome']) ? esc_textarea(woogram_fix_emoji_input($options['messagehome'], 0)) : ''; ?></textarea>
                                <p class="milmit-help-text"><?php esc_html_e('Message when returning to the home screen and main menu.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>

                            <!-- Contact Us -->
                            <div class="milmit-field-group">
                                <label for="contactuspage" class="milmit-label"><?php esc_html_e('Contact us', 'telenexa-commerce-for-telegram'); ?> (متن ارتباط با ما)</label>
                                <textarea id="contactuspage" 
                                          rows="3" 
                                          name="woogram_settings[contactuspage]" 
                                          data-emojiable="true" 
                                          class="milmit-textarea"><?php echo isset($options['contactuspage']) ? esc_textarea(woogram_fix_emoji_input($options['contactuspage'], 0)) : ''; ?></textarea>
                                
                                <div class="milmit-subfield" style="margin-top: 10px;">
                                    <label for="contactuspageurl" class="milmit-label-sub"><?php esc_html_e('Link to site', 'telenexa-commerce-for-telegram'); ?> (لینک وب‌سایت در صفحه تماس)</label>
                                    <input id="contactuspageurl" 
                                           type="url" 
                                           name="woogram_settings[contactuspageurl]" 
                                           value="<?php echo isset($options['contactuspageurl']) ? esc_attr($options['contactuspageurl']) : 'https://milmit.net'; ?>" 
                                           class="milmit-input milmit-ltr" 
                                           placeholder="https://milmit.net" />
                                </div>
                            </div>

                            <!-- Bot Signature -->
                            <div class="milmit-field-group">
                                <label for="botsign" class="milmit-label"><?php esc_html_e('Messages sign', 'telenexa-commerce-for-telegram'); ?> (امضای انتهای پیام‌ها)</label>
                                <textarea id="botsign" 
                                          rows="2" 
                                          name="woogram_settings[botsign]" 
                                          data-emojiable="true" 
                                          class="milmit-textarea"><?php echo isset($options['botsign']) ? esc_textarea(woogram_fix_emoji_input($options['botsign'], 0)) : ''; ?></textarea>
                                <p class="milmit-help-text"><?php esc_html_e('Text, link, or separator placed at the end of all sent messages.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========================================================================= -->
                <!-- TAB 3: DISPLAY SETTINGS -->
                <!-- ========================================================================= -->
                <section class="milmit-tab-pane <?php echo ($current === 'display') ? 'is-active' : ''; ?>" id="tab-display" role="tabpanel">
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-layout milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Display settings', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle"><?php esc_html_e('Layout of products, photos, texts and purchase buttons in Telegram', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>

                        <div class="milmit-card-body">
                            
                            <!-- Sub-section: Catalog & Categories -->
                            <h3 class="milmit-section-title"><span class="dashicons dashicons-category"></span> <?php esc_html_e('Catalog & Category Layout', 'telenexa-commerce-for-telegram'); ?></h3>
                            
                            <div class="milmit-grid-2">
                                <!-- Products per page -->
                                <div class="milmit-field-group">
                                    <label class="milmit-label"><?php esc_html_e('Product per page', 'telenexa-commerce-for-telegram'); ?></label>
                                    <div class="milmit-number-input">
                                        <input type="number" 
                                               name="woogram_settings[display_productsperpage]" 
                                               min="1" 
                                               max="50" 
                                               value="<?php echo isset($options['display_productsperpage']) ? intval($options['display_productsperpage']) : 5; ?>" 
                                               class="milmit-input" />
                                        <span class="milmit-input-unit"><?php esc_html_e('products per page', 'telenexa-commerce-for-telegram'); ?></span>
                                    </div>
                                    <p class="milmit-help-text"><?php esc_html_e('Number of products displayed per page with next/previous buttons.', 'telenexa-commerce-for-telegram'); ?></p>
                                </div>

                                <div class="milmit-field-group">
                                    <label class="milmit-label"><?php esc_html_e('Newest products sorting', 'telenexa-commerce-for-telegram'); ?></label>
                                    <?php $newest_order = isset($options['newest_orderby']) ? $options['newest_orderby'] : 'date'; ?>
                                    <select name="woogram_settings[newest_orderby]" class="milmit-select">
                                        <option value="date" <?php selected($newest_order, 'date'); ?>><?php esc_html_e('Newest published', 'telenexa-commerce-for-telegram'); ?></option>
                                        <option value="modified" <?php selected($newest_order, 'modified'); ?>><?php esc_html_e('Recently updated', 'telenexa-commerce-for-telegram'); ?></option>
                                        <option value="popularity" <?php selected($newest_order, 'popularity'); ?>><?php esc_html_e('Most popular', 'telenexa-commerce-for-telegram'); ?></option>
                                        <option value="price" <?php selected($newest_order, 'price'); ?>><?php esc_html_e('Lowest price', 'telenexa-commerce-for-telegram'); ?></option>
                                    </select>
                                    <p class="milmit-help-text"><?php esc_html_e('Controls the order used by the New Products button.', 'telenexa-commerce-for-telegram'); ?></p>
                                </div>

                                <!-- Category display columns -->
                                <div class="milmit-field-group">
                                    <label class="milmit-label"><?php esc_html_e('Category display', 'telenexa-commerce-for-telegram'); ?></label>
                                    <div class="milmit-segmented-group">
                                        <?php 
                                        $cat_cols = isset($options['display_categories']) ? intval($options['display_categories']) : 1;
                                        $cols_options = array(
                                            1 => __('1 Column', 'telenexa-commerce-for-telegram'),
                                            2 => __('2 Columns', 'telenexa-commerce-for-telegram'),
                                            3 => __('3 Columns', 'telenexa-commerce-for-telegram'),
                                            4 => __('List View', 'telenexa-commerce-for-telegram')
                                        );
                                        foreach ($cols_options as $val => $lbl): ?>
                                            <label class="milmit-segmented-item">
                                                <input type="radio" name="woogram_settings[display_categories]" value="<?php echo esc_attr($val); ?>" <?php echo ($cat_cols == $val) ? 'checked="checked"' : ''; ?>>
                                                <span class="milmit-segmented-label"><?php echo esc_html($lbl); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="milmit-help-text"><?php esc_html_e('Number of category button columns in Telegram keyboard.', 'telenexa-commerce-for-telegram'); ?></p>
                                </div>
                            </div>

                            <!-- Category & Catalog toggles grid -->
                            <div class="milmit-toggles-grid">
                                <div class="milmit-toggle-card">
                                    <div class="milmit-toggle-card-info">
                                        <div class="milmit-toggle-card-title"><?php esc_html_e('Display category count', 'telenexa-commerce-for-telegram'); ?></div>
                                        <div class="milmit-toggle-card-desc"><?php esc_html_e('Show available count next to category title', 'telenexa-commerce-for-telegram'); ?></div>
                                    </div>
                                    <label class="milmit-switch-ios">
                                        <input type="checkbox" name="woogram_settings[display_categorycount]" value="1" <?php echo (isset($options['display_categorycount']) && $options['display_categorycount'] == 1) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-slider-ios"></span>
                                    </label>
                                </div>

                                <div class="milmit-toggle-card">
                                    <div class="milmit-toggle-card-info">
                                        <div class="milmit-toggle-card-title"><?php esc_html_e('Hide empty category', 'telenexa-commerce-for-telegram'); ?></div>
                                        <div class="milmit-toggle-card-desc"><?php esc_html_e('Do not display categories that have no products', 'telenexa-commerce-for-telegram'); ?></div>
                                    </div>
                                    <label class="milmit-switch-ios">
                                        <input type="checkbox" name="woogram_settings[display_hideemptycategory]" value="1" <?php echo (isset($options['display_hideemptycategory']) && $options['display_hideemptycategory'] == 1) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-slider-ios"></span>
                                    </label>
                                </div>

                                <div class="milmit-toggle-card">
                                    <div class="milmit-toggle-card-info">
                                        <div class="milmit-toggle-card-title"><?php esc_html_e('Hide products if subcategory exist', 'telenexa-commerce-for-telegram'); ?></div>
                                        <div class="milmit-toggle-card-desc"><?php esc_html_e('Hide products if subcategory exist', 'telenexa-commerce-for-telegram'); ?></div>
                                    </div>
                                    <label class="milmit-switch-ios">
                                        <input type="checkbox" name="woogram_settings[hide_products_edge_subcategory]" value="1" <?php echo (isset($options['hide_products_edge_subcategory']) && $options['hide_products_edge_subcategory'] == 1) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-slider-ios"></span>
                                    </label>
                                </div>

                                <div class="milmit-toggle-card">
                                    <div class="milmit-toggle-card-info">
                                        <div class="milmit-toggle-card-title"><?php esc_html_e('Only display instock products', 'telenexa-commerce-for-telegram'); ?></div>
                                        <div class="milmit-toggle-card-desc"><?php esc_html_e('Only show products in the bot that are in stock', 'telenexa-commerce-for-telegram'); ?></div>
                                    </div>
                                    <label class="milmit-switch-ios">
                                        <input type="checkbox" name="woogram_settings[only_display_instock_products]" value="1" <?php echo (isset($options['only_display_instock_products']) && $options['only_display_instock_products'] == 1) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-slider-ios"></span>
                                    </label>
                                </div>

                                <div class="milmit-toggle-card">
                                    <div class="milmit-toggle-card-info">
                                        <div class="milmit-toggle-card-title"><?php esc_html_e('Display alert in bot', 'telenexa-commerce-for-telegram'); ?></div>
                                        <div class="milmit-toggle-card-desc"><?php esc_html_e('Display alert in bot', 'telenexa-commerce-for-telegram'); ?></div>
                                    </div>
                                    <label class="milmit-switch-ios">
                                        <input type="checkbox" name="woogram_settings[display_alert_in_bot]" value="1" <?php echo (isset($options['display_alert_in_bot']) && $options['display_alert_in_bot'] == 1) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-slider-ios"></span>
                                    </label>
                                </div>
                            </div>

                            <hr class="milmit-divider" />

                            <!-- Sub-section: Product Card Design -->
                            <h3 class="milmit-section-title"><span class="dashicons dashicons-format-image"></span> ظاهر کارت محصول و تصاویر</h3>

                            <!-- Product display mode -->
                            <div class="milmit-field-group">
                                <label class="milmit-label"><?php esc_html_e('Product display', 'telenexa-commerce-for-telegram'); ?></label>
                                <div class="milmit-segmented-group">
                                    <?php 
                                    $p_disp = isset($options['display_product']) ? intval($options['display_product']) : 1;
                                    ?>
                                    <label class="milmit-segmented-item">
                                        <input type="radio" name="woogram_settings[display_product]" value="1" <?php echo ($p_disp == 1) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-segmented-label"><span class="dashicons dashicons-format-image"></span> تصویر و متن</span>
                                    </label>
                                    <label class="milmit-segmented-item">
                                        <input type="radio" name="woogram_settings[display_product]" value="2" <?php echo ($p_disp == 2) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-segmented-label"><span class="dashicons dashicons-editor-paragraph"></span> <?php esc_html_e('Display text', 'telenexa-commerce-for-telegram'); ?></span>
                                    </label>
                                    <label class="milmit-segmented-item">
                                        <input type="radio" name="woogram_settings[display_product]" value="3" <?php echo ($p_disp == 3) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-segmented-label"><span class="dashicons dashicons-arrow-down-alt"></span> تصویر در انتهای پیام</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Photo size & Link type -->
                            <div class="milmit-grid-2">
                                <div class="milmit-field-group">
                                    <label class="milmit-label"><?php esc_html_e('Photo size', 'telenexa-commerce-for-telegram'); ?></label>
                                    <div class="milmit-segmented-group">
                                        <?php 
                                        $photo_size = isset($options['display_photosize']) ? intval($options['display_photosize']) : 2;
                                        ?>
                                        <label class="milmit-segmented-item">
                                            <input type="radio" name="woogram_settings[display_photosize]" value="1" <?php echo ($photo_size == 1) ? 'checked="checked"' : ''; ?>>
                                            <span class="milmit-segmented-label"><?php esc_html_e('Thumbnail', 'telenexa-commerce-for-telegram'); ?></span>
                                        </label>
                                        <label class="milmit-segmented-item">
                                            <input type="radio" name="woogram_settings[display_photosize]" value="2" <?php echo ($photo_size == 2) ? 'checked="checked"' : ''; ?>>
                                            <span class="milmit-segmented-label"><?php esc_html_e('Medium', 'telenexa-commerce-for-telegram'); ?></span>
                                        </label>
                                        <label class="milmit-segmented-item">
                                            <input type="radio" name="woogram_settings[display_photosize]" value="3" <?php echo ($photo_size == 3) ? 'checked="checked"' : ''; ?>>
                                            <span class="milmit-segmented-label"><?php esc_html_e('Large', 'telenexa-commerce-for-telegram'); ?></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="milmit-field-group">
                                    <label class="milmit-label"><?php esc_html_e('Product card details', 'telenexa-commerce-for-telegram'); ?></label>
                                    <label class="milmit-checkbox-row"><input type="hidden" name="woogram_settings[display_product_sku]" value="0" /><input type="checkbox" name="woogram_settings[display_product_sku]" value="1" <?php checked(isset($options['display_product_sku']) ? $options['display_product_sku'] : 1, 1); ?> /> <span><?php esc_html_e('Show SKU', 'telenexa-commerce-for-telegram'); ?></span></label>
                                    <label class="milmit-checkbox-row"><input type="hidden" name="woogram_settings[display_sale_badge]" value="0" /><input type="checkbox" name="woogram_settings[display_sale_badge]" value="1" <?php checked(isset($options['display_sale_badge']) ? $options['display_sale_badge'] : 1, 1); ?> /> <span><?php esc_html_e('Show discount badge', 'telenexa-commerce-for-telegram'); ?></span></label>
                                </div>

                                <div class="milmit-field-group">
                                    <label class="milmit-label"><?php esc_html_e('Product link', 'telenexa-commerce-for-telegram'); ?></label>
                                    <div class="milmit-segmented-group">
                                        <?php 
                                        $link_type = isset($options['display_product_link_type']) ? intval($options['display_product_link_type']) : 1;
                                        ?>
                                        <label class="milmit-segmented-item">
                                            <input type="radio" name="woogram_settings[display_product_link_type]" value="1" <?php echo ($link_type == 1) ? 'checked="checked"' : ''; ?>>
                                            <span class="milmit-segmented-label"><?php esc_html_e('Simple Link', 'telenexa-commerce-for-telegram'); ?></span>
                                        </label>
                                        <label class="milmit-segmented-item">
                                            <input type="radio" name="woogram_settings[display_product_link_type]" value="2" <?php echo ($link_type == 2) ? 'checked="checked"' : ''; ?>>
                                            <span class="milmit-segmented-label"><?php esc_html_e('WordPress Permalink', 'telenexa-commerce-for-telegram'); ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Description mode & length -->
                            <div class="milmit-grid-2">
                                <div class="milmit-field-group">
                                    <label class="milmit-label"><?php esc_html_e('Product display description', 'telenexa-commerce-for-telegram'); ?></label>
                                    <select name="woogram_settings[display_product_excerpt]" class="milmit-select">
                                        <?php $desc_m = isset($options['display_product_excerpt']) ? intval($options['display_product_excerpt']) : 0; ?>
                                        <option value="1" <?php echo ($desc_m == 1) ? 'selected="selected"' : ''; ?>><?php esc_html_e('Short description', 'telenexa-commerce-for-telegram'); ?> (توضیح کوتاه ووکامرس)</option>
                                        <option value="2" <?php echo ($desc_m == 2) ? 'selected="selected"' : ''; ?>><?php esc_html_e('Description limited', 'telenexa-commerce-for-telegram'); ?> (محدود به تعداد کلمه)</option>
                                        <option value="3" <?php echo ($desc_m == 3) ? 'selected="selected"' : ''; ?>><?php esc_html_e('Description full', 'telenexa-commerce-for-telegram'); ?> (متن کامل توضیحات)</option>
                                        <option value="0" <?php echo ($desc_m == 0) ? 'selected="selected"' : ''; ?>><?php esc_html_e('No', 'telenexa-commerce-for-telegram'); ?> (عدم نمایش توضیحات)</option>
                                    </select>
                                </div>

                                <div class="milmit-field-group">
                                    <label class="milmit-label"><?php esc_html_e('Description limited length', 'telenexa-commerce-for-telegram'); ?></label>
                                    <div class="milmit-number-input">
                                        <input type="number" 
                                               name="woogram_settings[display_product_excerpt_length]" 
                                               min="5" 
                                               max="500" 
                                               value="<?php echo isset($options['display_product_excerpt_length']) ? intval($options['display_product_excerpt_length']) : 55; ?>" 
                                               class="milmit-input" />
                                        <span class="milmit-input-unit"><?php esc_html_e('words', 'telenexa-commerce-for-telegram'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Search mode & Stock display -->
                            <div class="milmit-grid-2">
                                <div class="milmit-field-group">
                                    <label class="milmit-label"><?php esc_html_e('Product search', 'telenexa-commerce-for-telegram'); ?></label>
                                    <select name="woogram_settings[display_search]" class="milmit-select">
                                        <?php $search_m = isset($options['display_search']) ? intval($options['display_search']) : 1; ?>
                                        <option value="1" <?php echo ($search_m == 1) ? 'selected="selected"' : ''; ?>><?php esc_html_e('Only title', 'telenexa-commerce-for-telegram'); ?> (فقط عنوان محصول)</option>
                                        <option value="2" <?php echo ($search_m == 2) ? 'selected="selected"' : ''; ?>><?php esc_html_e('Title and text', 'telenexa-commerce-for-telegram'); ?> (عنوان و متن توضیحات)</option>
                                        <option value="0" <?php echo ($search_m == 0) ? 'selected="selected"' : ''; ?>><?php esc_html_e('Off', 'telenexa-commerce-for-telegram'); ?> (غیرفعال)</option>
                                    </select>
                                </div>

                                <div class="milmit-field-group">
                                    <label class="milmit-label"><?php esc_html_e('Show product qty in stock', 'telenexa-commerce-for-telegram'); ?></label>
                                    <select name="woogram_settings[display_showqty]" class="milmit-select">
                                        <?php $qty_m = isset($options['display_showqty']) ? intval($options['display_showqty']) : 0; ?>
                                        <option value="1" <?php echo ($qty_m == 1) ? 'selected="selected"' : ''; ?>><?php esc_html_e('Show number', 'telenexa-commerce-for-telegram'); ?> (نمایش عدد موجودی انبار)</option>
                                        <option value="2" <?php echo ($qty_m == 2) ? 'selected="selected"' : ''; ?>><?php esc_html_e('Only show in stock or not', 'telenexa-commerce-for-telegram'); ?> (فقط نمایش موجود/ناموجود)</option>
                                        <option value="0" <?php echo ($qty_m == 0) ? 'selected="selected"' : ''; ?>><?php esc_html_e('Not show', 'telenexa-commerce-for-telegram'); ?> (عدم نمایش وضعیت موجودی)</option>
                                    </select>
                                </div>
                            </div>

                            <hr class="milmit-divider" />

                            <!-- Sub-section: Elements Matrix -->
                            <h3 class="milmit-section-title"><span class="dashicons dashicons-visibility"></span> محل نمایش دکمه‌ها، قیمت و المان‌ها</h3>
                            <p class="milmit-help-text" style="margin-bottom: 15px;">مشخص کنید هر یک از المان‌ها در داخل ربات تلگرام یا در پیام‌های ارسالی به کانال و گروه‌ها نمایش داده شوند.</p>

                            <div class="milmit-matrix-wrap">
                                <table class="milmit-matrix-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Element / Button', 'telenexa-commerce-for-telegram'); ?></th>
                                            <th class="milmit-th-check">
                                                <span class="dashicons dashicons-smartphone"></span>
                                                <?php esc_html_e('Display in Bot', 'telenexa-commerce-for-telegram'); ?>
                                            </th>
                                            <th class="milmit-th-check">
                                                <span class="dashicons dashicons-megaphone"></span>
                                                <?php esc_html_e('Display in Broadcast (Channel, Groups, Users)', 'telenexa-commerce-for-telegram'); ?>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $matrix_items = array(
                                            'display_price_discounted' => __('Discounted Sale Price', 'telenexa-commerce-for-telegram'),
                                            'display_price'            => __('Regular Price', 'telenexa-commerce-for-telegram'),
                                            'display_linkbuyonsite'    => __('Online Purchase Link', 'telenexa-commerce-for-telegram'),
                                            'display_linkquickbuy'     => __('Quick Buy Link', 'telenexa-commerce-for-telegram'),
                                            'display_buttonorder'      => __('Order Button', 'telenexa-commerce-for-telegram'),
                                            'display_buttonviewonsite' => __('View on Website Button', 'telenexa-commerce-for-telegram')
                                        );

                                        foreach ($matrix_items as $f_key => $f_title):
                                            $val_arr = (isset($options[$f_key]) && is_array($options[$f_key])) ? $options[$f_key] : array();
                                            $in_checked = in_array('in', $val_arr);
                                            $out_checked = in_array('out', $val_arr);
                                        ?>
                                            <tr>
                                                <td class="milmit-td-title">
                                                    <strong><?php echo esc_html($f_title); ?></strong>
                                                    <span style="display:none;"><input type="checkbox" name="woogram_settings[<?php echo esc_attr($f_key); ?>][]" value="test" checked="checked"></span>
                                                </td>
                                                <td class="milmit-td-check">
                                                    <label class="milmit-custom-checkbox">
                                                        <input type="checkbox" name="woogram_settings[<?php echo esc_attr($f_key); ?>][]" value="in" <?php echo $in_checked ? 'checked="checked"' : ''; ?>>
                                                        <span class="milmit-checkmark"></span>
                                                    </label>
                                                </td>
                                                <td class="milmit-td-check">
                                                    <label class="milmit-custom-checkbox">
                                                        <input type="checkbox" name="woogram_settings[<?php echo esc_attr($f_key); ?>][]" value="out" <?php echo $out_checked ? 'checked="checked"' : ''; ?>>
                                                        <span class="milmit-checkmark"></span>
                                                    </label>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- How to get quantity -->
                            <div class="milmit-field-group" style="margin-top: 20px;">
                                <label for="display_howgetqty" class="milmit-label"><?php esc_html_e('How to get the order number', 'telenexa-commerce-for-telegram'); ?></label>
                                <textarea id="display_howgetqty" 
                                          name="woogram_settings[display_howgetqty]" 
                                          rows="3" 
                                          class="milmit-textarea milmit-ltr" 
                                          placeholder="1 2 3 4 5&#10;6 7 8 9 10"><?php echo isset($options['display_howgetqty']) ? esc_textarea($options['display_howgetqty']) : "1 2 3 4 5\n6 7 8 9 10"; ?></textarea>
                                <p class="milmit-help-text">
                                    دکمه‌های انتخاب سریع تعداد توسط مشتری (اعداد را با فاصله و هر سطر را با اینتر جدا کنید). اگر می‌خواهید کاربر خودش عدد را تایپ کند این فیلد را خالی بگذارید.
                                </p>
                            </div>

                        </div>
                    </div>
                </section>

                <!-- ========================================================================= -->
                <!-- TAB 4: ORDER SETTINGS -->
                <!-- ========================================================================= -->
                <section class="milmit-tab-pane <?php echo ($current === 'order') ? 'is-active' : ''; ?>" id="tab-order" role="tabpanel">
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-cart milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Order settings', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle"><?php esc_html_e('Order process, shopping cart, and customer checkout fields', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>

                        <div class="milmit-card-body">
                            <!-- Order mode -->
                            <div class="milmit-field-group">
                                <label class="milmit-label"><?php esc_html_e('Ordering & Purchase Flow', 'telenexa-commerce-for-telegram'); ?></label>
                                <div class="milmit-radio-cards">
                                    <?php $cart_status = isset($options['order_cart_status']) ? intval($options['order_cart_status']) : 1; ?>
                                    
                                    <label class="milmit-radio-card <?php echo ($cart_status == 1) ? 'is-selected' : ''; ?>">
                                        <input type="radio" name="woogram_settings[order_cart_status]" value="1" <?php echo ($cart_status == 1) ? 'checked="checked"' : ''; ?>>
                                        <div class="milmit-radio-card-content">
                                            <div class="milmit-radio-card-title">
                                                <span class="dashicons dashicons-cart"></span>
                                                <?php esc_html_e('Multi-Item Shopping Cart (Recommended)', 'telenexa-commerce-for-telegram'); ?>
                                                <span class="milmit-chip milmit-chip-success"><?php esc_html_e('Standard', 'telenexa-commerce-for-telegram'); ?></span>
                                            </div>
                                            <p class="milmit-radio-card-desc">مشتری می‌تواند چند کالا را به سبد خرید اضافه کند و تمام آنها را با هم در یک فاکتور تسویه نماید.</p>
                                        </div>
                                    </label>

                                    <label class="milmit-radio-card <?php echo ($cart_status == 0) ? 'is-selected' : ''; ?>">
                                        <input type="radio" name="woogram_settings[order_cart_status]" value="0" <?php echo ($cart_status == 0) ? 'checked="checked"' : ''; ?>>
                                        <div class="milmit-radio-card-content">
                                            <div class="milmit-radio-card-title">
                                                <span class="dashicons dashicons-yes"></span>
                                                <?php esc_html_e('Direct Single-Product Order', 'telenexa-commerce-for-telegram'); ?>
                                            </div>
                                            <p class="milmit-radio-card-desc">با کلیک روی سفارش، بلافاصله فرایند تسویه‌حساب همان تک‌محصول آغاز می‌شود.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <hr class="milmit-divider" />

                            <h3 class="milmit-section-title"><span class="dashicons dashicons-id-alt"></span> فیلدهای مورد نیاز در فرایند ثبت سفارش</h3>
                            <p class="milmit-help-text" style="margin-bottom: 15px;">مشخص کنید ربات تلگرام در هنگام ثبت سفارش کدام اطلاعات را از مشتری درخواست کند:</p>

                            <!-- Checkout Fields Toggles Grid -->
                            <div class="milmit-toggles-grid">
                                <?php
                                $checkout_toggles = array(
                                    'register_req_qty'        => array('title' => __('Get quantity in add to cart process', 'telenexa-commerce-for-telegram'), 'desc' => __('Quantity requested by buyer', 'telenexa-commerce-for-telegram')),
                                    'register_req_name'       => array('title' => __('Get name in order process', 'telenexa-commerce-for-telegram'), 'desc' => __('Buyer full name for invoice', 'telenexa-commerce-for-telegram')),
                                    'register_req_email'      => array('title' => __('Get email in order process', 'telenexa-commerce-for-telegram'), 'desc' => __('For sending invoices and order notifications', 'telenexa-commerce-for-telegram')),
                                    'register_req_state'      => array('title' => __('Get state in order process', 'telenexa-commerce-for-telegram'), 'desc' => __('Select state for shipping calculation', 'telenexa-commerce-for-telegram')),
                                    'register_req_city'       => array('title' => __('Get city in order process', 'telenexa-commerce-for-telegram'), 'desc' => __('Destination city for order delivery', 'telenexa-commerce-for-telegram')),
                                    'register_req_address'    => array('title' => __('Get address in order process', 'telenexa-commerce-for-telegram'), 'desc' => __('Street, alley, and house number for delivery', 'telenexa-commerce-for-telegram')),
                                    'register_req_zip'        => array('title' => __('Get postal code in order process', 'telenexa-commerce-for-telegram'), 'desc' => __('Postal code for delivery location', 'telenexa-commerce-for-telegram')),
                                    'register_req_shipping'   => array('title' => __('Get shipping method in order process', 'telenexa-commerce-for-telegram'), 'desc' => __('Select from WooCommerce shipping methods', 'telenexa-commerce-for-telegram')),
                                    'register_req_payment'    => array('title' => __('Get payment method in order process', 'telenexa-commerce-for-telegram'), 'desc' => __('Payment gateway or payment method for order', 'telenexa-commerce-for-telegram')),
                                    'register_req_ordernote'  => array('title' => __('Get order note in order process', 'telenexa-commerce-for-telegram'), 'desc' => __('Optional customer notes or instructions', 'telenexa-commerce-for-telegram'))
                                );

                                foreach ($checkout_toggles as $f_name => $f_info):
                                    $is_checked = isset($options[$f_name]) && $options[$f_name] == 1;
                                ?>
                                    <div class="milmit-toggle-card">
                                        <div class="milmit-toggle-card-info">
                                            <div class="milmit-toggle-card-title"><?php echo esc_html($f_info['title']); ?></div>
                                            <div class="milmit-toggle-card-desc"><?php echo esc_html($f_info['desc']); ?></div>
                                        </div>
                                        <label class="milmit-switch-ios">
                                            <input type="checkbox" name="woogram_settings[<?php echo esc_attr($f_name); ?>]" value="1" <?php echo $is_checked ? 'checked="checked"' : ''; ?>>
                                            <span class="milmit-slider-ios"></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Phone requirement mode -->
                            <div class="milmit-field-group" style="margin-top: 25px;">
                                <label class="milmit-label"><?php esc_html_e('Get phone in order process', 'telenexa-commerce-for-telegram'); ?></label>
                                <div class="milmit-segmented-group">
                                    <?php $phone_m = isset($options['register_req_phone']) ? intval($options['register_req_phone']) : 1; ?>
                                    <label class="milmit-segmented-item">
                                        <input type="radio" name="woogram_settings[register_req_phone]" value="1" <?php echo ($phone_m == 1) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-segmented-label"><?php esc_html_e('Mobile or Landline', 'telenexa-commerce-for-telegram'); ?></span>
                                    </label>
                                    <label class="milmit-segmented-item">
                                        <input type="radio" name="woogram_settings[register_req_phone]" value="3" <?php echo ($phone_m == 3) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-segmented-label"><?php esc_html_e('Mobile Only', 'telenexa-commerce-for-telegram'); ?></span>
                                    </label>
                                    <label class="milmit-segmented-item">
                                        <input type="radio" name="woogram_settings[register_req_phone]" value="2" <?php echo ($phone_m == 2) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-segmented-label"><?php esc_html_e('Landline Only', 'telenexa-commerce-for-telegram'); ?></span>
                                    </label>
                                    <label class="milmit-segmented-item">
                                        <input type="radio" name="woogram_settings[register_req_phone]" value="0" <?php echo ($phone_m == 0) ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-segmented-label"><?php esc_html_e('Do Not Collect', 'telenexa-commerce-for-telegram'); ?></span>
                                    </label>
                                </div>
                            </div>

                        </div>
                    </div>
                </section>

                <!-- ========================================================================= -->
                <!-- TAB 5: PROXY SETTINGS -->
                <!-- ========================================================================= -->
                <section class="milmit-tab-pane <?php echo ($current === 'proxy') ? 'is-active' : ''; ?>" id="tab-proxy" role="tabpanel">
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-shield milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Proxy settings', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle">تنظیمات پروکسی در صورتی که سرور هاست شما به تلگرام دسترسی ندارد</p>
                            </div>
                        </div>

                        <div class="milmit-card-body">
                            <!-- Proxy Status -->
                            <?php $proxy_active = isset($options['proxy_status']) && $options['proxy_status'] == 1; ?>
                            <div class="milmit-field-group milmit-field-inline">
                                <div>
                                    <label class="milmit-label"><?php esc_html_e('Proxy status', 'telenexa-commerce-for-telegram'); ?></label>
                                    <p class="milmit-help-text"><?php esc_html_e('Enable proxy if your hosting server is restricted from accessing Telegram.', 'telenexa-commerce-for-telegram'); ?></p>
                                </div>
                                <div class="milmit-toggle-wrapper">
                                    <label class="milmit-switch">
                                        <input type="radio" name="woogram_settings[proxy_status]" value="1" id="proxy_status_enable" <?php echo $proxy_active ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-toggle-opt"><?php esc_html_e('Enable', 'telenexa-commerce-for-telegram'); ?></span>
                                    </label>
                                    <label class="milmit-switch">
                                        <input type="radio" name="woogram_settings[proxy_status]" value="0" id="proxy_status_disable" <?php echo !$proxy_active ? 'checked="checked"' : ''; ?>>
                                        <span class="milmit-toggle-opt"><?php esc_html_e('Disable', 'telenexa-commerce-for-telegram'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Proxy Configuration Container -->
                            <div id="milmit_proxy_fields" class="milmit-proxy-container <?php echo !$proxy_active ? 'is-collapsed' : ''; ?>">
                                
                                <!-- Proxy Type -->
                                <div class="milmit-field-group">
                                    <label class="milmit-label"><?php esc_html_e('Proxy type', 'telenexa-commerce-for-telegram'); ?></label>
                                    <div class="milmit-segmented-group">
                                        <?php 
                                        $p_type = isset($options['proxy_type']) ? $options['proxy_type'] : 'http'; 
                                        $p_types = array('http' => 'HTTP', 'https' => 'HTTPS', 'socks4' => 'SOCKS 4', 'socks5' => 'SOCKS 5');
                                        foreach ($p_types as $pt_k => $pt_lbl):
                                        ?>
                                            <label class="milmit-segmented-item">
                                                <input type="radio" name="woogram_settings[proxy_type]" value="<?php echo esc_attr($pt_k); ?>" <?php echo ($p_type === $pt_k) ? 'checked="checked"' : ''; ?>>
                                                <span class="milmit-segmented-label"><?php echo esc_html($pt_lbl); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="milmit-grid-2">
                                    <!-- Proxy Address -->
                                    <div class="milmit-field-group">
                                        <label for="proxy_address" class="milmit-label"><?php esc_html_e('Proxy address', 'telenexa-commerce-for-telegram'); ?></label>
                                        <input id="proxy_address" 
                                               type="text" 
                                               name="woogram_settings[proxy_address]" 
                                               value="<?php echo isset($options['proxy_address']) ? esc_attr($options['proxy_address']) : ''; ?>" 
                                               placeholder="127.0.0.1 یا proxy.example.com" 
                                               class="milmit-input milmit-ltr" />
                                    </div>

                                    <!-- Proxy Port -->
                                    <div class="milmit-field-group">
                                        <label for="proxy_port" class="milmit-label"><?php esc_html_e('Proxy port', 'telenexa-commerce-for-telegram'); ?></label>
                                        <input id="proxy_port" 
                                               type="text" 
                                               name="woogram_settings[proxy_port]" 
                                               value="<?php echo isset($options['proxy_port']) ? esc_attr($options['proxy_port']) : ''; ?>" 
                                               placeholder="1080 یا 8080" 
                                               class="milmit-input milmit-ltr" />
                                    </div>
                                </div>

                                <div class="milmit-grid-2">
                                    <!-- Proxy Username -->
                                    <div class="milmit-field-group">
                                        <label for="proxy_username" class="milmit-label"><?php esc_html_e('Proxy username', 'telenexa-commerce-for-telegram'); ?></label>
                                        <input id="proxy_username" 
                                               type="text" 
                                               name="woogram_settings[proxy_username]" 
                                               value="<?php echo isset($options['proxy_username']) ? esc_attr($options['proxy_username']) : ''; ?>" 
                                               placeholder="<?php esc_attr_e('Leave blank to keep current password', 'telenexa-commerce-for-telegram'); ?>" 
                                               class="milmit-input milmit-ltr" />
                                    </div>

                                    <!-- Proxy Password -->
                                    <div class="milmit-field-group">
                                        <label for="proxy_password" class="milmit-label"><?php esc_html_e('Proxy password', 'telenexa-commerce-for-telegram'); ?></label>
                                        <input id="proxy_password" 
                                               type="password" 
                                               name="woogram_settings[proxy_password]" 
                                               value="" 
                                               placeholder="<?php esc_attr_e('Optional', 'telenexa-commerce-for-telegram'); ?>" 
                                               class="milmit-input milmit-ltr" />
                                    </div>
                                </div>

                                <div class="milmit-field-group" style="background: var(--surface-subtle); padding: 14px; border-radius: var(--radius-md);">
                                    <label class="milmit-label"><?php esc_html_e('Fallback proxy', 'telenexa-commerce-for-telegram'); ?></label>
                                    <p class="milmit-help-text"><?php esc_html_e('If the primary proxy cannot connect, TeleNexa tries this proxy automatically.', 'telenexa-commerce-for-telegram'); ?></p>
                                    <div class="milmit-grid-2">
                                        <label class="milmit-label"><input type="hidden" name="woogram_settings[proxy_status_fallback]" value="0" /><input type="checkbox" name="woogram_settings[proxy_status_fallback]" value="1" <?php checked($options['proxy_status_fallback'] ?? 0, 1); ?> /> <?php esc_html_e('Enable fallback', 'telenexa-commerce-for-telegram'); ?></label>
                                        <select name="woogram_settings[proxy_type_fallback]" class="milmit-select">
                                            <?php foreach ($p_types as $pt_k => $pt_lbl): ?><option value="<?php echo esc_attr($pt_k); ?>" <?php selected($options['proxy_type_fallback'] ?? 'http', $pt_k); ?>><?php echo esc_html($pt_lbl); ?></option><?php endforeach; ?>
                                        </select>
                                        <input type="text" name="woogram_settings[proxy_address_fallback]" value="<?php echo esc_attr($options['proxy_address_fallback'] ?? ''); ?>" placeholder="fallback.example.com" class="milmit-input milmit-ltr" />
                                        <input type="number" min="1" max="65535" name="woogram_settings[proxy_port_fallback]" value="<?php echo esc_attr($options['proxy_port_fallback'] ?? 8080); ?>" placeholder="8080" class="milmit-input milmit-ltr" />
                                        <input type="text" name="woogram_settings[proxy_username_fallback]" value="<?php echo esc_attr($options['proxy_username_fallback'] ?? ''); ?>" placeholder="<?php esc_attr_e('Username (optional)', 'telenexa-commerce-for-telegram'); ?>" class="milmit-input milmit-ltr" />
                                        <input type="password" name="woogram_settings[proxy_password_fallback]" value="" placeholder="<?php esc_attr_e('Password (leave blank to keep)', 'telenexa-commerce-for-telegram'); ?>" class="milmit-input milmit-ltr" />
                                    </div>
                                </div>

                                <?php $proxy_health = class_exists('TeleNexa\\Http\\ProxyManager') ? \TeleNexa\Http\ProxyManager::health() : []; ?>
                                <div class="milmit-field-group" style="background: var(--surface-subtle); padding: 14px; border-radius: var(--radius-md);">
                                    <label class="milmit-label"><?php esc_html_e('Proxy health', 'telenexa-commerce-for-telegram'); ?></label>
                                    <?php if (empty($proxy_health)): ?>
                                        <p class="milmit-help-text"><?php esc_html_e('No proxy test has been recorded yet.', 'telenexa-commerce-for-telegram'); ?></p>
                                    <?php else: foreach ($proxy_health as $health_name => $health): ?>
                                        <p class="milmit-help-text"><strong><?php echo esc_html(ucfirst($health_name)); ?>:</strong> <?php echo !empty($health['ok']) ? esc_html__('Online', 'telenexa-commerce-for-telegram') : esc_html__('Failed', 'telenexa-commerce-for-telegram'); ?> — <?php echo esc_html($health['last_check'] ?? ''); ?><?php if (!empty($health['error'])): ?> — <?php echo esc_html($health['error']); ?><?php endif; ?></p>
                                    <?php endforeach; endif; ?>
                                    <button type="button" class="milmit-btn-ghost" id="btn_test_telegram_proxy"><span class="dashicons dashicons-shield"></span> <?php esc_html_e('Test Proxy & Failover', 'telenexa-commerce-for-telegram'); ?></button>
                                </div>

                                <div class="milmit-field-group" style="background: var(--surface-subtle); padding: 14px; border-radius: var(--radius-md);">
                                    <label class="milmit-label"><?php esc_html_e('Connection performance & logging', 'telenexa-commerce-for-telegram'); ?></label>
                                    <div class="milmit-grid-2">
                                        <label class="milmit-label"><?php esc_html_e('Request timeout (seconds)', 'telenexa-commerce-for-telegram'); ?><input type="number" min="5" max="20" name="woogram_settings[telegram_http_timeout]" value="<?php echo esc_attr($options['telegram_http_timeout'] ?? 6); ?>" class="milmit-input milmit-ltr" /></label>
                                        <label class="milmit-label"><?php esc_html_e('Retry count', 'telenexa-commerce-for-telegram'); ?><select name="woogram_settings[telegram_api_retries]" class="milmit-select"><option value="0" <?php selected($options['telegram_api_retries'] ?? 0, 0); ?>>0</option><option value="1" <?php selected($options['telegram_api_retries'] ?? 0, 1); ?>>1</option><option value="2" <?php selected($options['telegram_api_retries'] ?? 0, 2); ?>>2</option></select></label>
                                    </div>
                                    <label class="milmit-checkbox-row"><input type="hidden" name="woogram_settings[woogram_logging_enabled]" value="0" /><input type="checkbox" name="woogram_settings[woogram_logging_enabled]" value="1" <?php checked($options['woogram_logging_enabled'] ?? 1, 1); ?> /> <span><?php esc_html_e('Enable detailed bot, button, API and proxy logs', 'telenexa-commerce-for-telegram'); ?></span></label>
                                    <label><?php esc_html_e('Log level', 'telenexa-commerce-for-telegram'); ?>
                                        <select name="woogram_settings[woogram_log_level]">
                                            <option value="errors" <?php selected($options['woogram_log_level'] ?? 'all', 'errors'); ?>><?php esc_html_e('Errors only', 'telenexa-commerce-for-telegram'); ?></option>
                                            <option value="all" <?php selected($options['woogram_log_level'] ?? 'all', 'all'); ?>><?php esc_html_e('All events', 'telenexa-commerce-for-telegram'); ?></option>
                                        </select>
                                    </label>
                                    <label><?php esc_html_e('Log retention (days)', 'telenexa-commerce-for-telegram'); ?> <input type="number" min="1" max="30" name="woogram_settings[woogram_log_retention_days]" value="<?php echo esc_attr($options['woogram_log_retention_days'] ?? 7); ?>" /></label>
                                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=woogram_download_logs'), 'woogram_download_logs')); ?>"><?php esc_html_e('Download sanitized logs', 'telenexa-commerce-for-telegram'); ?></a>
                                </div>

                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========================================================================= -->
                <!-- TAB: MAIN MENU & BUTTONS -->
                <!-- ========================================================================= -->
                <section class="milmit-tab-pane <?php echo ($current === 'menu') ? 'is-active' : ''; ?>" id="tab-menu" role="tabpanel">
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-menu milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Main Menu & Button Customization', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle"><?php esc_html_e('Toggle buttons, customize multilingual titles, rearrange order, and add custom buttons.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>
                        <div class="milmit-card-body">
                            <?php
                            $menu_items = \TeleNexa\WooBot::getActiveMenuItems();
                            ?>
                            <div class="milmit-field-group">
                                <label class="milmit-label"><?php esc_html_e('Core Menu Buttons', 'telenexa-commerce-for-telegram'); ?></label>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <?php foreach ($menu_items as $m_key => $m_item): ?>
                                        <div style="background: var(--surface-subtle); padding: 12px 16px; border-radius: var(--radius-md); border: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                                            <div style="display: flex; align-items: center; gap: 10px; min-width: 140px;">
                                                <input type="hidden" name="woogram_settings[main_menu_items][<?php echo esc_attr($m_key); ?>][enabled]" value="0" />
                                                <input type="checkbox" name="woogram_settings[main_menu_items][<?php echo esc_attr($m_key); ?>][enabled]" value="1" <?php checked(!empty($m_item['enabled']), 1); ?> />
                                                <strong><?php echo esc_html($m_item['title_fa']); ?></strong>
                                            </div>
                                            <div style="display: flex; gap: 8px; flex-grow: 1; max-width: 450px;">
                                                <input type="text" name="woogram_settings[main_menu_items][<?php echo esc_attr($m_key); ?>][title_fa]" value="<?php echo esc_attr($m_item['title_fa']); ?>" placeholder="عنوان فارسی" class="milmit-input" style="flex: 1;" />
                                                <input type="text" name="woogram_settings[main_menu_items][<?php echo esc_attr($m_key); ?>][title_en]" value="<?php echo esc_attr($m_item['title_en']); ?>" placeholder="English title" class="milmit-input milmit-ltr" style="flex: 1;" />
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="font-size: 12px; color: var(--text-muted);"><?php esc_html_e('Priority:', 'telenexa-commerce-for-telegram'); ?></span>
                                                <input type="number" name="woogram_settings[main_menu_items][<?php echo esc_attr($m_key); ?>][order]" value="<?php echo esc_attr($m_item['order']); ?>" style="width: 55px;" class="milmit-input milmit-ltr" />
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <input type="hidden" name="woogram_settings[feature_menu_commands]" value="0" />
                            <label class="milmit-checkbox-row" style="margin-top: 14px;">
                                <input type="checkbox" name="woogram_settings[feature_menu_commands]" value="1" <?php checked(isset($options['feature_menu_commands']) ? $options['feature_menu_commands'] : 1, 1); ?> />
                                <span><strong><?php esc_html_e('Telegram command menu', 'telenexa-commerce-for-telegram'); ?></strong><small><?php esc_html_e('Expose standard commands in the Telegram menu when supported.', 'telenexa-commerce-for-telegram'); ?></small></span>
                            </label>
                            <input type="hidden" name="woogram_settings[compact_navigation]" value="0" />
                            <label class="milmit-checkbox-row">
                                <input type="checkbox" name="woogram_settings[compact_navigation]" value="1" <?php checked(isset($options['compact_navigation']) ? $options['compact_navigation'] : 1, 1); ?> />
                                <span><strong><?php esc_html_e('Clean Telegram navigation', 'telenexa-commerce-for-telegram'); ?></strong><small><?php esc_html_e('Replace the previous bot screen when users navigate with buttons, so the chat stays compact.', 'telenexa-commerce-for-telegram'); ?></small></span>
                            </label>
                        </div>
                    </div>
                </section>

                <!-- ========================================================================= -->
                <!-- TAB: SEARCH & FILTERS -->
                <!-- ========================================================================= -->
                <section class="milmit-tab-pane <?php echo ($current === 'search') ? 'is-active' : ''; ?>" id="tab-search" role="tabpanel">
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-search milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Advanced Search & Filtering', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle"><?php esc_html_e('Configure search behavior, SKU lookups, price filters, stock filtering, and sorting.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>
                        <div class="milmit-card-body">
                            <input type="hidden" name="woogram_settings[feature_live_search]" value="0" />
                            <label class="milmit-checkbox-row">
                                <input type="checkbox" name="woogram_settings[feature_live_search]" value="1" <?php checked(isset($options['feature_live_search']) ? $options['feature_live_search'] : 1, 1); ?> />
                                <span><strong><?php esc_html_e('Enable Advanced Product Search', 'telenexa-commerce-for-telegram'); ?></strong><small><?php esc_html_e('Keep product search available via /search and interactive search hub.', 'telenexa-commerce-for-telegram'); ?></small></span>
                            </label>

                            <input type="hidden" name="woogram_settings[search_sku_enable]" value="0" />
                            <label class="milmit-checkbox-row">
                                <input type="checkbox" name="woogram_settings[search_sku_enable]" value="1" <?php checked(isset($options['search_sku_enable']) ? $options['search_sku_enable'] : 1, 1); ?> />
                                <span><strong><?php esc_html_e('Direct Search by Product SKU', 'telenexa-commerce-for-telegram'); ?></strong><small><?php esc_html_e('Allow customers to find products instantly by typing or scanning SKU codes.', 'telenexa-commerce-for-telegram'); ?></small></span>
                            </label>

                            <input type="hidden" name="woogram_settings[search_price_filter_enable]" value="0" />
                            <label class="milmit-checkbox-row">
                                <input type="checkbox" name="woogram_settings[search_price_filter_enable]" value="1" <?php checked(isset($options['search_price_filter_enable']) ? $options['search_price_filter_enable'] : 1, 1); ?> />
                                <span><strong><?php esc_html_e('Interactive Price Range Filters', 'telenexa-commerce-for-telegram'); ?></strong><small><?php esc_html_e('Display price filter shortcuts in the search interface.', 'telenexa-commerce-for-telegram'); ?></small></span>
                            </label>

                            <div class="milmit-field-group">
                                <label for="search_default_sort" class="milmit-label"><?php esc_html_e('Default Product Sorting Order', 'telenexa-commerce-for-telegram'); ?></label>
                                <select id="search_default_sort" name="woogram_settings[search_default_sort]" class="milmit-select">
                                    <option value="date" <?php selected(isset($options['search_default_sort']) ? $options['search_default_sort'] : 'date', 'date'); ?>><?php esc_html_e('Newest First', 'telenexa-commerce-for-telegram'); ?></option>
                                    <option value="popularity" <?php selected(isset($options['search_default_sort']) ? $options['search_default_sort'] : 'date', 'popularity'); ?>><?php esc_html_e('Best Selling (Popularity)', 'telenexa-commerce-for-telegram'); ?></option>
                                    <option value="price_asc" <?php selected(isset($options['search_default_sort']) ? $options['search_default_sort'] : 'date', 'price_asc'); ?>><?php esc_html_e('Price: Low to High', 'telenexa-commerce-for-telegram'); ?></option>
                                    <option value="price_desc" <?php selected(isset($options['search_default_sort']) ? $options['search_default_sort'] : 'date', 'price_desc'); ?>><?php esc_html_e('Price: High to Low', 'telenexa-commerce-for-telegram'); ?></option>
                                    <option value="on_sale" <?php selected(isset($options['search_default_sort']) ? $options['search_default_sort'] : 'date', 'on_sale'); ?>><?php esc_html_e('Discounted Products First', 'telenexa-commerce-for-telegram'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========================================================================= -->
                <!-- TAB: NOTIFICATIONS -->
                <!-- ========================================================================= -->
                <section class="milmit-tab-pane <?php echo ($current === 'notifications') ? 'is-active' : ''; ?>" id="tab-notifications" role="tabpanel">
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-bell milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Automated Notifications & Alerts', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle"><?php esc_html_e('Configure customer and admin Telegram alerts for orders, stock, price drops, and abandoned carts.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>
                        <div class="milmit-card-body">
                            <input type="hidden" name="woogram_settings[notify_customer_new_order]" value="0" />
                            <label class="milmit-checkbox-row">
                                <input type="checkbox" name="woogram_settings[notify_customer_new_order]" value="1" <?php checked(isset($options['notify_customer_new_order']) ? $options['notify_customer_new_order'] : 1, 1); ?> />
                                <span><strong><?php esc_html_e('Send Order Confirmation to Customer', 'telenexa-commerce-for-telegram'); ?></strong><small><?php esc_html_e('Instantly notify the customer via Telegram with order number and tracking button.', 'telenexa-commerce-for-telegram'); ?></small></span>
                            </label>

                            <input type="hidden" name="woogram_settings[notify_status_change]" value="0" />
                            <label class="milmit-checkbox-row">
                                <input type="checkbox" name="woogram_settings[notify_status_change]" value="1" <?php checked(isset($options['notify_status_change']) ? $options['notify_status_change'] : 1, 1); ?> />
                                <span><strong><?php esc_html_e('Notify Customer on Order Status Changes', 'telenexa-commerce-for-telegram'); ?></strong><small><?php esc_html_e('Send updates when order status changes to Processing, Completed, Cancelled, etc.', 'telenexa-commerce-for-telegram'); ?></small></span>
                            </label>

                            <input type="hidden" name="woogram_settings[notify_admin_new_order]" value="0" />
                            <label class="milmit-checkbox-row">
                                <input type="checkbox" name="woogram_settings[notify_admin_new_order]" value="1" <?php checked(isset($options['notify_admin_new_order']) ? $options['notify_admin_new_order'] : 1, 1); ?> />
                                <span><strong><?php esc_html_e('Send New Order Alert to Admin', 'telenexa-commerce-for-telegram'); ?></strong><small><?php esc_html_e('Send instant notification to store administrator Telegram chat upon every new order.', 'telenexa-commerce-for-telegram'); ?></small></span>
                            </label>

                            <div class="milmit-field-group">
                                <label for="admin_notification_chat_id" class="milmit-label"><?php esc_html_e('Admin Telegram Chat ID', 'telenexa-commerce-for-telegram'); ?></label>
                                <input id="admin_notification_chat_id" 
                                       type="text" 
                                       name="woogram_settings[admin_notification_chat_id]" 
                                       value="<?php echo isset($options['admin_notification_chat_id']) ? esc_attr($options['admin_notification_chat_id']) : ''; ?>" 
                                       placeholder="123456789" 
                                       class="milmit-input milmit-ltr" />
                                <p class="milmit-help-text"><?php esc_html_e('Numeric Telegram Chat ID where admin notifications should be delivered.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>

                            <input type="hidden" name="woogram_settings[feature_abandoned_cart]" value="0" />
                            <label class="milmit-checkbox-row">
                                <input type="checkbox" name="woogram_settings[feature_abandoned_cart]" value="1" <?php checked(isset($options['feature_abandoned_cart']) ? $options['feature_abandoned_cart'] : 1, 1); ?> />
                                <span><strong><?php esc_html_e('Abandoned Cart Reminder (Action Scheduler)', 'telenexa-commerce-for-telegram'); ?></strong><small><?php esc_html_e('Send a friendly reminder if a customer leaves items in their cart without completing payment.', 'telenexa-commerce-for-telegram'); ?></small></span>
                            </label>

                            <div class="milmit-field-group">
                                <label for="abandoned_cart_delay_hours" class="milmit-label"><?php esc_html_e('Abandoned Cart Delay (Hours)', 'telenexa-commerce-for-telegram'); ?></label>
                                <input id="abandoned_cart_delay_hours" 
                                       type="number" 
                                       name="woogram_settings[abandoned_cart_delay_hours]" 
                                       value="<?php echo isset($options['abandoned_cart_delay_hours']) ? esc_attr($options['abandoned_cart_delay_hours']) : 3; ?>" 
                                       min="1" 
                                       max="72" 
                                       class="milmit-input milmit-ltr" 
                                       style="width: 80px;" />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========================================================================= -->
                <!-- TAB: TELEGRAM MINI APP -->
                <!-- ========================================================================= -->
                <section class="milmit-tab-pane <?php echo ($current === 'webapp') ? 'is-active' : ''; ?>" id="tab-webapp" role="tabpanel">
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-smartphone milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Telegram Mini App (Web App)', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle"><?php esc_html_e('Interactive storefront inside Telegram with modern product grid, search, live cart, and checkout.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>
                        <div class="milmit-card-body">
                            <input type="hidden" name="woogram_settings[feature_webapp_enable]" value="0" />
                            <label class="milmit-checkbox-row">
                                <input type="checkbox" name="woogram_settings[feature_webapp_enable]" value="1" <?php checked(isset($options['feature_webapp_enable']) ? $options['feature_webapp_enable'] : 1, 1); ?> />
                                <span><strong><?php esc_html_e('Enable Telegram Mini App', 'telenexa-commerce-for-telegram'); ?></strong><small><?php esc_html_e('Allow customers to open the interactive web app inside Telegram.', 'telenexa-commerce-for-telegram'); ?></small></span>
                            </label>

                            <div class="milmit-field-group">
                                <label class="milmit-label"><?php esc_html_e('Mini App Direct URL', 'telenexa-commerce-for-telegram'); ?></label>
                                <div class="milmit-input-addon-wrap">
                                    <input type="text" readonly value="<?php echo esc_url(\TeleNexa\WooBot::getWebAppUrl()); ?>" class="milmit-input milmit-ltr" id="milmit_webapp_url_input" />
                                    <button type="button" class="milmit-btn-ghost milmit-btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('milmit_webapp_url_input').value); alert('Copied!');">
                                        <?php esc_html_e('Copy URL', 'telenexa-commerce-for-telegram'); ?>
                                    </button>
                                </div>
                                <p class="milmit-help-text">
                                    <?php // translators: %s: Command code snippet
echo wp_kses_post(sprintf(__('You can connect this URL to @BotFather via %s or set it as a menu button for your bot.', 'telenexa-commerce-for-telegram'), '<code>/newapp</code>')); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- TAB: MARKETING & BACKUP SETTINGS -->
                <!-- ========================================================================= -->
                <section class="milmit-tab-pane <?php echo ($current === 'marketing') ? 'is-active' : ''; ?>" id="tab-marketing" role="tabpanel">
                    <!-- Referral & Invite System -->
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-share-alt2 milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Referral & Invitation Program', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle"><?php esc_html_e('Empower your customers to invite friends via /start ref_XXXX and track referral statistics', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>
                        <div class="milmit-card-body">
                            <div class="milmit-form-group">
                                <label class="milmit-toggle-wrap">
                                    <input type="checkbox" name="woogram_settings[feature_referrals]" value="yes" <?php checked(isset($options['feature_referrals']) ? $options['feature_referrals'] : 'yes', 'yes'); ?>>
                                    <span class="milmit-toggle-slider"></span>
                                    <span class="milmit-toggle-label"><?php esc_html_e('Enable Referral & Invitation System', 'telenexa-commerce-for-telegram'); ?></span>
                                </label>
                                <p class="milmit-help-text"><?php esc_html_e('Generates a unique personal invite link for every Telegram subscriber with /referral command and tracking.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>

                            <div class="milmit-form-group">
                                <label class="milmit-form-label"><?php esc_html_e('Referral Program Description / Reward Message', 'telenexa-commerce-for-telegram'); ?></label>
                                <textarea name="woogram_settings[referral_reward_text]" rows="3" class="milmit-input"><?php echo esc_textarea(isset($options['referral_reward_text']) ? $options['referral_reward_text'] : __('Invite your friends to our Telegram bot and earn exclusive shopping rewards!', 'telenexa-commerce-for-telegram')); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Flash Sale & Time-Limited Campaigns -->
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-megaphone milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Time-Limited Campaigns & Flash Sales', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle"><?php esc_html_e('Display an urgent promotional banner across bot menus and catalog views', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>
                        <div class="milmit-card-body">
                            <div class="milmit-form-group">
                                <label class="milmit-toggle-wrap">
                                    <input type="checkbox" name="woogram_settings[enable_campaign_banner]" value="yes" <?php checked(isset($options['enable_campaign_banner']) ? $options['enable_campaign_banner'] : 'no', 'yes'); ?>>
                                    <span class="milmit-toggle-slider"></span>
                                    <span class="milmit-toggle-label"><?php esc_html_e('Enable Promotional Campaign Banner', 'telenexa-commerce-for-telegram'); ?></span>
                                </label>
                            </div>

                            <div class="milmit-form-group">
                                <label class="milmit-form-label"><?php esc_html_e('Campaign Banner Announcement', 'telenexa-commerce-for-telegram'); ?></label>
                                <textarea name="woogram_settings[campaign_banner_text]" rows="2" class="milmit-input"><?php echo esc_textarea(isset($options['campaign_banner_text']) ? $options['campaign_banner_text'] : ''); ?></textarea>
                                <p class="milmit-help-text"><?php esc_html_e('Example: Special festival discount! Use code FLASH20 at checkout for 20% off.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>

                            <div class="milmit-form-group">
                                <label class="milmit-form-label"><?php esc_html_e('Telegram-Exclusive Coupon Code', 'telenexa-commerce-for-telegram'); ?></label>
                                <input type="text" name="woogram_settings[telegram_exclusive_coupon]" value="<?php echo esc_attr(isset($options['telegram_exclusive_coupon']) ? $options['telegram_exclusive_coupon'] : ''); ?>" class="milmit-input" placeholder="e.g. TG20" />
                                <p class="milmit-help-text"><?php esc_html_e('Create this coupon code in WooCommerce > Marketing > Coupons for special bot discounts.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Feedback & Post-Purchase Rating -->
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-star-filled milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Post-Purchase Rating & Feedback', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle"><?php esc_html_e('Prompt customers for 1-5 star feedback when orders are completed', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>
                        <div class="milmit-card-body">
                            <div class="milmit-form-group">
                                <label class="milmit-toggle-wrap">
                                    <input type="checkbox" name="woogram_settings[enable_order_rating]" value="1" <?php checked(isset($options['enable_order_rating']) ? $options['enable_order_rating'] : '1', '1'); ?>>
                                    <span class="milmit-toggle-slider"></span>
                                    <span class="milmit-toggle-label"><?php esc_html_e('Enable Post-Purchase Rating Survey', 'telenexa-commerce-for-telegram'); ?></span>
                                </label>
                                <p class="milmit-help-text"><?php esc_html_e('Sends star rating buttons to the customer in Telegram once the order status changes to Completed.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Telegram Stars Payments -->
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-star-filled milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Telegram Stars Payments', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle"><?php esc_html_e('Use Telegram Stars for fully digital products sold inside Telegram.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>
                        <div class="milmit-card-body">
                            <label class="milmit-toggle-wrap">
                                <input type="hidden" name="woogram_settings[telegram_stars_enable]" value="0" />
                                <input type="checkbox" name="woogram_settings[telegram_stars_enable]" value="1" <?php checked(isset($options['telegram_stars_enable']) ? $options['telegram_stars_enable'] : 0, 1); ?> />
                                <span class="milmit-toggle-slider"></span>
                                <span class="milmit-toggle-label"><?php esc_html_e('Enable Telegram Stars for digital products', 'telenexa-commerce-for-telegram'); ?></span>
                            </label>
                            <div class="milmit-form-group">
                                <label class="milmit-form-label"><?php esc_html_e('Stars per store currency unit', 'telenexa-commerce-for-telegram'); ?></label>
                                <input type="number" min="0" step="0.000001" name="woogram_settings[telegram_stars_per_currency_unit]" value="<?php echo esc_attr(isset($options['telegram_stars_per_currency_unit']) ? $options['telegram_stars_per_currency_unit'] : 0); ?>" class="milmit-input milmit-ltr" />
                                <p class="milmit-help-text"><?php esc_html_e('Set the conversion rate carefully. Leave 0 to keep Stars disabled. Physical or mixed carts always use the normal WooCommerce payment flow.', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Backup (Export / Import) -->
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-database-export milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Backup & Migration (Export / Import Settings)', 'telenexa-commerce-for-telegram'); ?></h2>
                                    <p class="milmit-card-subtitle"><?php esc_html_e('Export all TeleNexa configurations to JSON or import settings from another site', 'telenexa-commerce-for-telegram'); ?></p>
                            </div>
                        </div>
                        <div class="milmit-card-body">
                            <div class="milmit-grid-2">
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px;">
                                    <h4 style="margin: 0 0 8px; color: #0f172a;"><?php esc_html_e('Export Settings', 'telenexa-commerce-for-telegram'); ?></h4>
                                    <p style="font-size: 13px; color: #64748b; margin-bottom: 14px;"><?php esc_html_e('Download a complete JSON file containing your tokens, menu structure, and bot preferences.', 'telenexa-commerce-for-telegram'); ?></p>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?action=woogram_export_settings'), 'woogram_export_settings_nonce')); ?>" class="button button-primary">
                                        <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                                        <?php esc_html_e('Download Settings JSON', 'telenexa-commerce-for-telegram'); ?>
                                    </a>
                                </div>

                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px;">
                                    <h4 style="margin: 0 0 8px; color: #0f172a;"><?php esc_html_e('Import Settings', 'telenexa-commerce-for-telegram'); ?></h4>
                                    <p style="font-size: 13px; color: #64748b; margin-bottom: 14px;"><?php esc_html_e('Upload a valid TeleNexa JSON backup file to overwrite current settings safely.', 'telenexa-commerce-for-telegram'); ?></p>
                                    <div>
                                        <input type="hidden" name="woogram_import_nonce" value="<?php echo esc_attr(wp_create_nonce('woogram_import_settings_nonce')); ?>" form="woogram_import_settings_form" />
                                        <input type="hidden" name="woogram_import_settings_action" value="1" form="woogram_import_settings_form" />
                                        <input type="file" name="woogram_import_file" accept=".json" required style="margin-bottom: 10px; font-size: 12px;" form="woogram_import_settings_form" />
                                        <div>
                                            <button type="submit" form="woogram_import_settings_form" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to overwrite current settings with this file?', 'telenexa-commerce-for-telegram')); ?>');">
                                                <span class="dashicons dashicons-upload" style="vertical-align: middle;"></span>
                                                <?php esc_html_e('Restore / Import Settings', 'telenexa-commerce-for-telegram'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- TAB 7: COMMANDS SETTINGS -->
                <!-- ========================================================================= -->
                <section class="milmit-tab-pane <?php echo ($current === 'commands') ? 'is-active' : ''; ?>" id="tab-commands" role="tabpanel">
                    <div class="milmit-card">
                        <div class="milmit-card-header">
                            <span class="dashicons dashicons-editor-help milmit-card-icon"></span>
                            <div>
                                <h2 class="milmit-card-title"><?php esc_html_e('Add commands to bot menu', 'telenexa-commerce-for-telegram'); ?></h2>
                                <p class="milmit-card-subtitle">آموزش گام‌به‌گام افزودن دستورات منو به ربات تلگرام از طریق BotFather</p>
                            </div>
                        </div>

                        <div class="milmit-card-body">
                            <!-- Stepper Guide -->
                            <div class="milmit-stepper">
                                <div class="milmit-step-item">
                                    <div class="milmit-step-number">۱</div>
                                    <div class="milmit-step-content">
                                        <h4><?php esc_html_e('Open @BotFather Bot', 'telenexa-commerce-for-telegram'); ?></h4>
                                        <p>در تلگرام به آیدی <a href="https://t.me/BotFather" target="_blank" class="milmit-link">@BotFather</a> بروید و دکمه Start را بزنید.</p>
                                    </div>
                                </div>

                                <div class="milmit-step-item">
                                    <div class="milmit-step-number">۲</div>
                                    <div class="milmit-step-content">
                                        <h4><?php esc_html_e('Send Register Menu Command', 'telenexa-commerce-for-telegram'); ?></h4>
                                        <p><?php // translators: %s: Command code snippet
echo wp_kses_post(sprintf(__('Send the %s command to @BotFather.', 'telenexa-commerce-for-telegram'), '<code>/setcommands</code>')); ?></p>
                                    </div>
                                </div>

                                <div class="milmit-step-item">
                                    <div class="milmit-step-number">۳</div>
                                    <div class="milmit-step-content">
                                        <h4><?php esc_html_e('Select Your Shop Bot', 'telenexa-commerce-for-telegram'); ?></h4>
                                        <p><?php esc_html_e('Select your shop bot from the buttons or list provided by @BotFather.', 'telenexa-commerce-for-telegram'); ?></p>
                                    </div>
                                </div>

                                <div class="milmit-step-item">
                                    <div class="milmit-step-number">۴</div>
                                    <div class="milmit-step-content">
                                        <h4><?php esc_html_e('Copy and Send Commands Below to @BotFather', 'telenexa-commerce-for-telegram'); ?></h4>
                                        <p><?php esc_html_e('Copy the text below using the copy button and send it to @BotFather:', 'telenexa-commerce-for-telegram'); ?></p>
                                        
                                        <div class="milmit-code-block">
                                            <div class="milmit-code-block-header">
                                                <span><?php esc_html_e('Standard Bot Commands (Commands List)', 'telenexa-commerce-for-telegram'); ?></span>
                                                <button type="button" class="milmit-btn-copy" id="milmit_copy_commands">
                                                    <span class="dashicons dashicons-admin-page"></span>
                                                    <?php esc_html_e('Copy to Clipboard', 'telenexa-commerce-for-telegram'); ?>
                                                </button>
                                            </div>
                                            <textarea id="milmit_commands_text" readonly class="milmit-code-content" rows="6">home - خانه و منوی اصلی
newproducts - جدیدترین محصولات
category - دسته‌بندی محصولات
tracking - پیگیری وضعیت سفارش
cart - مشاهده سبد خرید
contactus - تماس و پشتیبانی</textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="milmit-step-item">
                                    <div class="milmit-step-number">۵</div>
                                    <div class="milmit-step-content">
                                        <h4><?php esc_html_e('View Result in Bot', 'telenexa-commerce-for-telegram'); ?></h4>
                                        <p>با انجام مراحل بالا، منوی زیبای دستورات شبیه به تصویر زیر در کنار کادر تایپ ربات شما فعال خواهد شد:</p>
                                        <div class="milmit-preview-image-wrap">
                                            <img src="<?php echo esc_url(woogram_get_plugin_uri()); ?>img/botcommands.jpg?u=2" alt="پیش‌نمایش دستورات ربات تلگرام" class="milmit-preview-image" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

            </main>
        </div>

        <!-- Sticky Footer Action Bar -->
        <footer class="milmit-footer-bar">
            <div class="milmit-footer-left">
                <button type="submit" class="milmit-btn-primary milmit-btn-save">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e('Save Changes', 'telenexa-commerce-for-telegram'); ?>
                </button>
                <span class="milmit-save-hint"><?php esc_html_e('All changes across all tabs will be saved with this button.', 'telenexa-commerce-for-telegram'); ?></span>
            </div>
            <div class="milmit-footer-right">
                <span class="milmit-footer-brand">
                    <?php esc_html_e('Design & Support:', 'telenexa-commerce-for-telegram'); ?> <a href="https://milmit.net" target="_blank" rel="noopener noreferrer">milmit.net</a>
                </span>
            </div>
        </footer>
    </form>
</div>
