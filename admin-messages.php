<?php
if (!defined('ABSPATH')) exit;

function woogram_notices() {
    global $current_screen;
    if (!isset($current_screen->post_type)) {
        return;
    }
    if ('woogram_user' === $current_screen->post_type) {
        woogram_footer_text_call();
        ?>
        <div class="notice notice-info is-dismissible">
            <p><?php echo esc_html__('List of users who have subscribed to your bot.', 'telenexa-commerce-for-telegram'); ?></p>
        </div>
        <?php
    } elseif ('woogram_group' === $current_screen->post_type) {
        woogram_footer_text_call();
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <?php echo esc_html__('List of groups where your bot has been added.', 'telenexa-commerce-for-telegram'); ?><br>
                <small>
                    <?php echo esc_html__('Some ', 'telenexa-commerce-for-telegram'); ?>
                    <strong><?php echo esc_html__('BotFather', 'telenexa-commerce-for-telegram'); ?></strong>
                    <?php echo esc_html__(' actions could be required to get this working.', 'telenexa-commerce-for-telegram'); ?>
                </small>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'woogram_notices');