<?php
if (!defined('ABSPATH')) exit;

class TeleNexa_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'woogram_widget',
            __('TeleNexa Telegram Widget', 'telenexa-commerce-for-telegram'),
            ['description' => __('Add bot or channel link to your website', 'telenexa-commerce-for-telegram')]
        );
    }

    public function widget($args, $instance) {
        echo wp_kses_post($args['before_widget']);
        if (!empty($instance['title'])) {
            $title = apply_filters('widget_title', $instance['title']);
            echo wp_kses_post($args['before_title']) . esc_html($title) . wp_kses_post($args['after_title']);
        }
        $target = !empty($instance['target']) ? $instance['target'] : 'bot';
        if ($target === 'bot') {
            $link = 'https://telegram.me/' . woogram_option('username');
        } else {
            $link = 'https://telegram.me/' . str_replace('@', '', (string) woogram_option('channelusername'));
        }
        $text = !empty($instance['text']) ? $instance['text'] : __('Follow us on Telegram', 'telenexa-commerce-for-telegram');
        ?>
        <a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($link); ?>" style="background: #28a5e7; border-radius: 4px; color: white; padding: 6px 12px; display: inline-flex; align-items: center; gap: 8px; margin: 8px 0; font-size: 13px; text-decoration: none;">
            <img src="<?php echo esc_url(plugins_url('img/telegramiconmini.jpg', __FILE__)); ?>" style="width: 20px; height: 20px; border-radius: 50%;" alt="telegram-icon">
            <span><?php echo esc_html($text); ?></span>
        </a>
        <?php
        echo wp_kses_post($args['after_widget']);
    }

    public function form($instance) {
        $title  = !empty($instance['title']) ? $instance['title'] : '';
        $text   = !empty($instance['text']) ? $instance['text'] : __('Follow us on Telegram', 'telenexa-commerce-for-telegram');
        $target = !empty($instance['target']) ? $instance['target'] : 'bot';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title:', 'telenexa-commerce-for-telegram'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('text')); ?>"><?php esc_html_e('Text:', 'telenexa-commerce-for-telegram'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('text')); ?>" name="<?php echo esc_attr($this->get_field_name('text')); ?>" type="text" value="<?php echo esc_attr($text); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('target')); ?>"><?php esc_html_e('Target:', 'telenexa-commerce-for-telegram'); ?></label>
            <select id="<?php echo esc_attr($this->get_field_id('target')); ?>" name="<?php echo esc_attr($this->get_field_name('target')); ?>">
                <option value="bot" <?php selected($target, 'bot'); ?>><?php esc_html_e('Bot', 'telenexa-commerce-for-telegram'); ?></option>
                <option value="channel" <?php selected($target, 'channel'); ?>><?php esc_html_e('Channel', 'telenexa-commerce-for-telegram'); ?></option>
            </select>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance           = $old_instance;
        $instance['title']  = !empty($new_instance['title']) ? wp_strip_all_tags((string) $new_instance['title']) : '';
        $instance['text']   = !empty($new_instance['text']) ? wp_strip_all_tags((string) $new_instance['text']) : '';
        $instance['target'] = (!empty($new_instance['target']) && $new_instance['target'] === 'channel') ? 'channel' : 'bot';
        return $instance;
    }
}

if (!class_exists('woogram_widget', false)) {
    class_alias('TeleNexa_Widget', 'woogram_widget');
}
