<?php
if (!defined('ABSPATH')) exit;

// Keep TeleNexa post types managed within the TeleNexa Dashboard,
// without cluttering or polluting the main WordPress admin sidebar menu.
add_filter('register_post_type_args', function ($args, $post_type) {
    if (in_array($post_type, ['woogram_user', 'woogram_group', 'telegram_commands'], true)) {
        $args['show_in_menu'] = false;
    }
    return $args;
}, 10, 2);

add_action('init', function() {
    $labels_user = [
        'name'               => _x('Subscribed users', 'Post Type General Name', 'telenexa-commerce-for-telegram'),
        'singular_name'      => _x('User', 'Post Type Singular Name', 'telenexa-commerce-for-telegram'),
        'menu_name'          => __('Telegram', 'telenexa-commerce-for-telegram'),
        'name_admin_bar'     => __('Users', 'telenexa-commerce-for-telegram'),
        'parent_item_colon'  => __('Parent Item:', 'telenexa-commerce-for-telegram'),
        'all_items'          => __('Subscribed users', 'telenexa-commerce-for-telegram'),
        'add_new_item'       => __('Add New Item', 'telenexa-commerce-for-telegram'),
        'add_new'            => __('Add New', 'telenexa-commerce-for-telegram'),
        'new_item'           => __('New Item', 'telenexa-commerce-for-telegram'),
        'edit_item'          => __('Edit Item', 'telenexa-commerce-for-telegram'),
        'update_item'        => __('Update Item', 'telenexa-commerce-for-telegram'),
        'view_item'          => __('View Item', 'telenexa-commerce-for-telegram'),
        'search_items'       => __('Search Item', 'telenexa-commerce-for-telegram'),
        'not_found'          => __('Not found', 'telenexa-commerce-for-telegram'),
        'not_found_in_trash' => __('Not found in Trash', 'telenexa-commerce-for-telegram'),
    ];

    $args_user = [
        'label'               => __('Subscriber', 'telenexa-commerce-for-telegram'),
        'description'         => __('Post Type Description', 'telenexa-commerce-for-telegram'),
        'labels'              => $labels_user,
        'taxonomies'          => ['woogram_group'],
        'supports'            => ['title', 'editor', 'custom-fields'],
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => false,
        'show_in_nav_menus'   => false,
        'can_export'          => false,
        'has_archive'         => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => true,
        'rewrite'             => false,
        'capability_type'     => 'post',
    ];

    if (defined('WP_DEBUG') && false === WP_DEBUG) {
        $args_user['capabilities'] = ['create_posts' => 'do_not_allow'];
        $args_user['map_meta_cap'] = false;
    }

    $args_user = apply_filters('woogram_user_register_capabilities', $args_user);
    register_post_type('woogram_user', $args_user);

    $labels_group = [
        'name'               => _x('Subscribed groups', 'Post Type General Name', 'telenexa-commerce-for-telegram'),
        'singular_name'      => _x('Group', 'Post Type Singular Name', 'telenexa-commerce-for-telegram'),
        'menu_name'          => __('Telegram', 'telenexa-commerce-for-telegram'),
        'name_admin_bar'     => __('Groups', 'telenexa-commerce-for-telegram'),
        'parent_item_colon'  => __('Parent Item:', 'telenexa-commerce-for-telegram'),
        'all_items'          => __('Subscribed groups', 'telenexa-commerce-for-telegram'),
        'add_new_item'       => __('Add New Item', 'telenexa-commerce-for-telegram'),
        'add_new'            => __('Add New', 'telenexa-commerce-for-telegram'),
        'new_item'           => __('New Item', 'telenexa-commerce-for-telegram'),
        'edit_item'          => __('Edit Item', 'telenexa-commerce-for-telegram'),
        'update_item'        => __('Update Item', 'telenexa-commerce-for-telegram'),
        'view_item'          => __('View Item', 'telenexa-commerce-for-telegram'),
        'search_items'       => __('Search Item', 'telenexa-commerce-for-telegram'),
        'not_found'          => __('Not found', 'telenexa-commerce-for-telegram'),
        'not_found_in_trash' => __('Not found in Trash', 'telenexa-commerce-for-telegram'),
    ];

    $args_group = [
        'label'               => __('Groups', 'telenexa-commerce-for-telegram'),
        'description'         => __('Post Type Description', 'telenexa-commerce-for-telegram'),
        'labels'              => $labels_group,
        'taxonomies'          => ['woogram_group'],
        'supports'            => ['title', 'editor', 'custom-fields'],
        'hierarchical'        => false,
        'public'              => true,
        'show_ui'             => true,
        'show_in_menu'        => false,
        'show_in_nav_menus'   => false,
        'can_export'          => false,
        'has_archive'         => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => true,
        'rewrite'             => false,
        'capability_type'     => 'post',
    ];

    if (defined('WP_DEBUG') && false === WP_DEBUG) {
        $args_group['capabilities'] = ['create_posts' => 'do_not_allow'];
        $args_group['map_meta_cap'] = false;
    }

    $args_group = apply_filters('woogram_group_register_capabilities', $args_group);
    register_post_type('woogram_group', $args_group);
});

add_action('post_submitbox_misc_actions', function ($post) {
    if (!is_object($post) || get_post_type($post) !== 'product') {
        return;
    }
    wp_nonce_field('woogram_product_misc_save', 'woogram_product_misc_nonce');

    $is_scheduled = false;
    if (get_post_meta($post->ID, 'telegram_future_publish', true)) {
        $is_scheduled = true;
    }
    $target = (int) woogram_option('target');
    ?>
    <div class="misc-pub-section misc-pub-section-last">
        <span id="timestamp">
            <label>
                <input type="checkbox" value="1" <?php checked($is_scheduled, true); ?> name="woogram_m_send" id="woogram_m_send" />
                <?php echo esc_html__('Send to WooCommerce Telegram Bot', 'telenexa-commerce-for-telegram'); ?>
                <img src="<?php echo esc_url(plugins_url('img/telegramicon.png', __FILE__)); ?>" style="width:16px;position:absolute;padding:0 5px;" alt="telegram" />
                <?php if ($is_scheduled): ?>
                    - <b><?php echo esc_html__('scheduled', 'telenexa-commerce-for-telegram'); ?></b>
                <?php endif; ?>
            </label>
            <div id="woogram_m">
                <small>
                    <?php esc_html_e('Send to:', 'telenexa-commerce-for-telegram'); ?>
                    <select name="woogram_m_send_target">
                        <option value="0" <?php selected($target, 0); ?>><?php esc_html_e('Users, Groups, Channel', 'telenexa-commerce-for-telegram'); ?></option>
                        <option value="1" <?php selected($target, 1); ?>><?php esc_html_e('Users', 'telenexa-commerce-for-telegram'); ?></option>
                        <option value="2" <?php selected($target, 2); ?>><?php esc_html_e('Groups', 'telenexa-commerce-for-telegram'); ?></option>
                        <option value="3" <?php selected($target, 3); ?>><?php esc_html_e('Users, Groups', 'telenexa-commerce-for-telegram'); ?></option>
                        <option value="4" <?php selected($target, 4); ?>><?php esc_html_e('Channel', 'telenexa-commerce-for-telegram'); ?></option>
                    </select>
                </small>
            </div>
            <?php
            $last_sent = get_post_meta($post->ID, 'woogram_last_sent', true);
            if (!empty($last_sent)) {
                echo '<br><small>' . esc_html__('Last sent on: ', 'telenexa-commerce-for-telegram') . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int)$last_sent)) . '</small>';
            }
            ?>
        </span>
    </div>
    <?php
});
