<?php
if (!defined('ABSPATH')) exit;

function woogram_user_columns($columns) {
    $columns['first_name'] = __('First Name', 'telenexa-commerce-for-telegram');
    $columns['last_name']  = __('Last Name', 'telenexa-commerce-for-telegram');
    $columns['username']   = __('Username', 'telenexa-commerce-for-telegram');
    $columns['sdate']      = __('Subscribe Date', 'telenexa-commerce-for-telegram');
    unset($columns['cb'], $columns['date']);
    if (defined('WP_DEBUG') && false === WP_DEBUG) {
        unset($columns['title']);
    }
    return apply_filters('woogram_manage_user_columns', $columns);
}
add_filter('manage_edit-woogram_user_columns', 'woogram_user_columns');

function woogram_group_columns($columns) {
    $columns['name']  = __('Group Name', 'telenexa-commerce-for-telegram');
    $columns['sdate'] = __('Subscribe Date', 'telenexa-commerce-for-telegram');
    unset($columns['cb'], $columns['date']);
    if (defined('WP_DEBUG') && false === WP_DEBUG) {
        unset($columns['title']);
    }
    return apply_filters('woogram_manage_group_columns', $columns);
}
add_filter('manage_edit-woogram_group_columns', 'woogram_group_columns');

add_filter('bulk_actions-edit-woogram_user', function($actions) {
    unset($actions['edit']);
    return apply_filters('woogram_bulk_actions_user', $actions);
});

add_filter('bulk_actions-edit-woogram_group', function($actions) {
    unset($actions['edit']);
    return apply_filters('woogram_bulk_actions_group', $actions);
});

add_action('manage_woogram_user_posts_custom_column', 'woogram_manage_columns', 10, 2);
add_action('manage_woogram_group_posts_custom_column', 'woogram_manage_columns', 10, 2);

function woogram_manage_columns($column, $post_id) {
    switch ($column) {
        case 'name':
            echo esc_html((string) get_post_meta($post_id, 'telegram_group_title', true));
            break;
        case 'first_name':
            echo esc_html((string) get_post_meta($post_id, 'telegram_first_name', true));
            break;
        case 'last_name':
            echo esc_html((string) get_post_meta($post_id, 'telegram_last_name', true));
            break;
        case 'username':
            echo esc_html((string) get_post_meta($post_id, 'telegram_username', true));
            break;
        case 'sdate':
            echo esc_html((string) get_the_date('', $post_id));
            break;
        default:
            break;
    }
}
