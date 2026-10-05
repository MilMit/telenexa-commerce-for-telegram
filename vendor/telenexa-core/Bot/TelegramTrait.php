<?php
namespace TeleNexa\Bot;
use TeleNexa\Telegram\Update;
use TeleNexa\Http\TelegramApiAdapter;

/** Telegram behavior for WooBot. */
trait TelegramTrait {
    public static function handle()  {
        $started_at = microtime(true);
        $input = TelegramApiAdapter::request('getInput');
        if (empty($input))  {
            self::log('ERROR: WebHook parse json empty ');
            return false;
        }
        $post = json_decode($input, true);
        if (empty($post))  {
            self::log('ERROR: JSON EXCEPTION > '.$input);
            return false;
        }
        $update = new Update($post, self::$username);
        $update_id = $update->getUpdateId();
        self::$update_type = $update->getUpdateType();
        if (self::$update_type === 'pre_checkout_query') {
            self::handlePreCheckoutQuery($update->getPreCheckoutQuery());
            return true;
        }
        if(self::$update_type==='message') {
            $message = $update->getMessage();
            self::$message_id = $message->getMessageId();
            self::$command = sanitize_text_field( self::fixPersianChar($message->getText()) );
            $from = $message->getFrom();
            self::$chat_id = self::fixPersianChar($from->getId());
            $chat = $message->getChat();
            self::$chat_type = $chat->getType();
            if(self::$chat_type == 'private')  {
            }  elseif(self::$chat_type == 'group' OR self::$chat_type == 'supergroup')  {
                self::$group_chat_id = $chat->getId();
                self::$group_title = $chat->getTitle();
            }
            self::$first_name = $from->getFirstName();
            self::$last_name = $from->getLastName();
            self::$user_username = $from->getUsername();
        }  elseif(self::$update_type==='callback_query') {
            $callback_query = $update->getCallbackQuery();
            self::$callback_query_id = $callback_query->getId();
            $callback_data = $callback_query->getData();
            $message = $callback_query->getMessage();
            self::$message_id = $message->getMessageId();
            self::$chat_id = self::fixPersianChar($callback_query->getFrom()->getId());
            self::$first_name = $callback_query->getFrom()->getFirstName();
            self::$last_name = $callback_query->getFrom()->getLastName();
            self::$user_username = $callback_query->getFrom()->getUsername();
            self::$command = sanitize_text_field( self::fixPersianChar($callback_query->getData()) );
            $chat = $message->getChat();
            self::$chat_type = $chat->getType();
            if(self::$chat_type == 'private')  {
            }  elseif(self::$chat_type == 'group' OR self::$chat_type == 'supergroup')  {
                self::$group_chat_id = $chat->getId();
                self::$group_title = $chat->getTitle();
            }
        }  else if(self::$update_type === 'edited_message') {
            return;
        }
        self::updateUser($update_id);
        self::updateGroup($update_id);
        $o = self::getUser();
        self::initSession();
        // CoreTrait::init() normally runs during WordPress init, before the
        // Telegram user's language session is loaded. Rebuild per-request
        // pages now so /start and /home use the selected language.
        self::init('in');
        self::beginNavigation();
        if (self::$update_type === 'message' && $message->getSuccessfulPayment()) {
            self::handleSuccessfulPayment($message);
            self::updateSession();
            return true;
        }
        if(self::$update_type==='callback_query') {
            self::setSession('last_callback_query_id', self::$callback_query_id);
            self::$callback_ack_sent = false;
            self::$callback_message_edited = false;
            self::$callback_replacement_sent = false;
            // Acknowledge immediately. Catalog queries, proxy retries and
            // message cleanup must not leave Telegram's spinner running.
            self::answerCallback('');
        }
        self::log(self::$command);
        self::logStructured('update_received', ['update_type' => self::$update_type, 'command' => self::$command, 'message_id' => (int) self::$message_id]);
        $membership_check = self::$command === '/membership_check';
        if ($membership_check) {
            // Do not reuse the previous membership result when the user taps
            // the verification button after joining a channel or group.
            self::resetMembershipCache();
        }
        if (!self::enforceMembership()) {
            self::updateSession();
            self::cleanupCallbackSourceMessage();
            self::logStructured('update_completed', ['update_type' => self::$update_type, 'command' => self::$command, 'skipped' => true, 'reason' => 'membership_required']);
            return true;
        }
        if ($membership_check) {
            self::answerCallback(self::botTranslate('Membership verified.', 'telenexa-commerce-for-telegram'));
            self::$command = '/home';
        }
        $skip_command = false;
        if(!empty(self::$group_chat_id)) {
            $bot_info = self::getBotInfo();
            $group_command = explode('@', self::$command);
            if( !empty($group_command[1]) && $group_command[1] === trim($bot_info['name'], '@') ) {
                self::$command = $group_command[0];
            }  else  {
                if(self::$update_type!=='callback_query')$skip_command = true;
            }
            if(!$skip_command && !self::$enabledingroups)$skip_command = true;
        }
        if(!$skip_command) {
            try {
                self::commands();
            } catch (\Throwable $e) {
                self::$navigation_render_failed = true;
                self::logStructured('command_exception', [
                    'command' => self::$command,
                    'message' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                ]);
                self::sendMessage(['text' => self::botTranslate('Something went wrong while processing this action. Please try again.', 'telenexa-commerce-for-telegram'), 'keyboard' => [[self::btnHome()]]]);
            }
        }
        // Retire the previous page only after the new page has rendered.
        self::cleanupCallbackSourceMessage();
        self::updateSession();
        self::logStructured('update_completed', ['update_type' => self::$update_type, 'command' => self::$command, 'duration_ms' => (int) round((microtime(true) - $started_at) * 1000), 'skipped' => $skip_command]);
    }
    public static function sendMessage(array $params)  {
        $track_message = in_array(self::$update_type, ['message', 'callback_query'], true);
        if(!empty($params['keyboard'])) {
            $params['reply_markup'] = self::keyboard($params['keyboard']);
            unset($params['keyboard']);
        }
        if(empty($params['chat_id'])) {
            if(!empty(self::$group_chat_id)) {
                $params['chat_id'] = self::fixPersianChar(self::$group_chat_id);
            }  else  {
                $params['chat_id'] = self::fixPersianChar(self::$chat_id);
            }
        }
        $params['parse_mode'] = 'HTML';
        $params['disable_web_page_preview'] = false;
        $params['disable_notification'] = false;
        $params['text'] = self::parseText($params['text'],'HTML').self::$sign;
        $result = TelegramApiAdapter::request('sendMessage', $params);
        if ($track_message) {
            self::rememberNavigationMessage($result);
        }
        self::handleTelegramApiResponse($result, 'sendMessage', $params);
    }
    public static function editMessage(array $params)  {
        $original_params = $params;
        if(!empty($params['keyboard'])) {
            $params['reply_markup'] = self::keyboard($params['keyboard']);
            unset($params['keyboard']);
        }
        if(empty($params['chat_id'])) {
            if(!empty(self::$group_chat_id)) {
                $params['chat_id'] = self::fixPersianChar(self::$group_chat_id);
            }  else  {
                $params['chat_id'] = self::fixPersianChar(self::$chat_id);
            }
        }
        $params['message_id'] = self::$message_id;
        $params['text'] = self::parseText($params['text'],'HTML').self::$sign;
        if(self::isCallback())  {
            $result = TelegramApiAdapter::request('editMessageText', $params);
        }  else  {
            $result = TelegramApiAdapter::request('sendMessage', $params);
        }
        // A compact-navigation callback may have removed the source message.
        // Fall back to a fresh message instead of leaving the user without a page.
        if (self::isCallback() && isset($result->ok) && $result->ok === false) {
            if (stripos($result->getDescription(), 'message is not modified') !== false) {
                self::$callback_message_edited = true;
                self::$bot_message_ids[] = (int) self::$message_id;
                return;
            }
            self::sendMessage($original_params);
            return;
        }
        if (self::isCallback() && is_object($result) && method_exists($result, 'isOk') && $result->isOk()) {
            self::$callback_message_edited = true;
            self::$bot_message_ids[] = (int) self::$message_id;
        } elseif (!self::isCallback()) {
            self::rememberNavigationMessage($result);
        }
        self::handleTelegramApiResponse($result, 'editMessage', $params);
    }

    /** Finish navigation for both callback and typed commands. */
    public static function cleanupCallbackSourceMessage() {
        self::clearNavigationMessages();
    }
    public static function sendPhoto(array $params, $photo_url = null)  {
        $telegram_max_length_photo_caption = 1024;
        if ($photo_url === null && isset($params['photo'])) {
            $photo_url = $params['photo'];
        }
        if(empty($photo_url)) {
            return false;
        }
        if(!empty($params['keyboard'])) {
            $params['reply_markup'] = self::keyboard($params['keyboard']);
            unset($params['keyboard']);
        }
        if(empty($params['chat_id'])) {
            if(!empty(self::$group_chat_id)) {
                $params['chat_id'] = self::fixPersianChar(self::$group_chat_id);
            }  else  {
                $params['chat_id'] = self::fixPersianChar(self::$chat_id);
            }
        }
        if(!empty($params['text']) && empty($params['caption'])) {
            if(function_exists('mb_internal_encoding')) mb_internal_encoding("UTF-8");
            $params['caption'] = self::parseText($params['text'],'HTML').self::$sign;
            $params['caption'] = mb_substr($params['caption'], 0, $telegram_max_length_photo_caption);
            unset($params['text']);
        } elseif (isset($params['caption'])) {
            $caption = self::parseText($params['caption'], 'HTML').self::$sign;
            $params['caption'] = function_exists('mb_substr')
                ? mb_substr($caption, 0, $telegram_max_length_photo_caption)
                : substr($caption, 0, $telegram_max_length_photo_caption);
        }
        $params['photo'] = $photo_url;
        $result = TelegramApiAdapter::request('sendPhoto', $params);
        if (in_array(self::$update_type, ['message', 'callback_query'], true)) {
            self::rememberNavigationMessage($result);
        }
        self::handleTelegramApiResponse($result, 'sendPhoto', $params);
    }

    /**
     * Inspect Telegram API response, manage retry for 429 and network errors, and record structured log.
     */
    protected static function handleTelegramApiResponse($result, $action = 'sendMessage', array $params = []) {
        if (!is_object($result)) {
            self::log("Telegram error {$action}: result is not an object");
            self::logStructured('api_empty_response', ['action' => $action, 'chat_id' => $params['chat_id'] ?? '']);
            return;
        }

        if (isset($result->ok) && $result->ok === false) {
            $desc = isset($result->description) ? (string)$result->description : 'Unknown error';
            $error_code = isset($result->error_code) ? (int)$result->error_code : 0;
            $raw = method_exists($result, 'getRawData') ? $result->getRawData() : [];

            // Check for 429 Too Many Requests or timeout
            if ($error_code === 429 || stripos($desc, 'Too Many Requests') !== false) {
                $retry_after = 2;
                if (!empty($raw['parameters']['retry_after'])) {
                    $retry_after = (int)$raw['parameters']['retry_after'];
                } elseif (preg_match('/retry after (\d+)/i', $desc, $matches)) {
                    $retry_after = (int)$matches[1];
                }

                self::logStructured('rate_limit_exceeded', [
                    'action'      => $action,
                    'chat_id'     => $params['chat_id'] ?? '',
                    'retry_after' => $retry_after,
                    'description' => $desc,
                ]);

                // Asynchronously retry via Action Scheduler or WP-Cron
                if (!empty($params['chat_id']) && $action === 'sendMessage') {
                    if (function_exists('as_schedule_single_action')) {
                        as_schedule_single_action(time() + $retry_after + 1, 'woogram_retry_send_message', [$params], 'telenexa-commerce-for-telegram');
                    } else {
                        wp_schedule_single_event(time() + $retry_after + 1, 'woogram_retry_send_message', [$params]);
                    }
                }
            } else {
                self::logStructured('telegram_api_error', [
                    'action'      => $action,
                    'chat_id'     => $params['chat_id'] ?? '',
                    'error_code'  => $error_code,
                    'description' => $desc,
                ]);
            }

            self::log("Telegram error {$action}: {$desc}");
        }
    }

    /**
     * Asynchronous retry worker for messages delayed due to rate limits or timeouts.
     */
    public static function executeRetrySendMessage(array $params) {
        if (!empty($params['chat_id'])) {
            $result = TelegramApiAdapter::request('sendMessage', $params);
            if (isset($result->ok) && $result->ok) {
                self::logStructured('retry_send_success', ['chat_id' => $params['chat_id']]);
            }
        }
    }

    /**
     * High-res structured logging for enterprise monitoring and debugging.
     */
    public static function logStructured($event, array $context = []) {
        if (function_exists('woogram_log_event')) woogram_log_event($event, $context);
    }

    protected static function rememberNavigationMessage($result) {
        if (!is_object($result) || (isset($result->ok) && $result->ok === false)) {
            self::$navigation_render_failed = true;
            return;
        }
        // Internal Telegram entities expose getters through __call(), so
        // method_exists() cannot be used for them.
        $messages = $result->getResult();
        foreach (is_array($messages) ? $messages : [$messages] as $message) {
            $message_id = is_object($message) ? $message->getMessageId() : 0;
            if ($message_id) {
                if (self::$update_type === 'callback_query' && (int) $message_id !== (int) self::$message_id) {
                    self::$callback_replacement_sent = true;
                }
                self::$bot_message_ids[] = (int) $message_id;
                self::setSession('navigation_message_ids', array_values(array_unique(array_merge(self::$previous_navigation_ids, self::$bot_message_ids))));
            }
        }
    }

    public static function beginNavigation() {
        $ids = self::getSession('navigation_message_ids');
        self::$previous_navigation_ids = is_array($ids) ? array_values(array_filter(array_map('absint', $ids))) : [];
        self::$bot_message_ids = [];
        self::$callback_message_edited = false;
        self::$callback_replacement_sent = false;
        self::$navigation_render_failed = false;
    }

    public static function clearNavigationMessages() {
        $current_ids = array_values(array_unique(self::$bot_message_ids));
        $previous_ids = self::$previous_navigation_ids;
        if (self::$update_type === 'callback_query' && (int) self::$message_id > 0) {
            $previous_ids[] = (int) self::$message_id;
        }
        if (self::$callback_message_edited) $current_ids[] = (int) self::$message_id;
        if (self::$navigation_render_failed || empty($current_ids) || woogram_option('compact_navigation') === '0') {
            self::setSession('navigation_message_ids', array_values(array_unique(array_merge($previous_ids, $current_ids))));
            return;
        }
        $message_ids = array_values(array_diff(array_unique($previous_ids), $current_ids));
        $remaining = [];
        foreach ($message_ids as $message_id) {
            if (!$message_id) continue;
            $params = [
                'chat_id' => !empty(self::$group_chat_id) ? self::fixPersianChar(self::$group_chat_id) : self::fixPersianChar(self::$chat_id),
                'message_id' => (int) $message_id,
            ];
            $result = TelegramApiAdapter::request('deleteMessage', $params);
            if (isset($result->ok) && $result->ok === false) {
                $remaining[] = $message_id;
                self::log('Telegram navigation cleanup: ' . $result->description);
            }
        }

        self::setSession('navigation_message_ids', array_values(array_unique(array_merge($remaining, $current_ids))));
    }
    public static function answerCallback($text,$show_alert=false)  {
        // A callback answer is valid only for callback_query updates. A
        // normal /start message may still have an old callback ID in the
        // session; reusing it causes Telegram's "query is too old" error.
        if (self::$update_type !== 'callback_query') return true;
        if (self::$callback_ack_sent) return true;
        $callback_query_id = self::getSession('last_callback_query_id');
        if(empty($callback_query_id))return;
        $params = [
            'callback_query_id' => $callback_query_id,
            'text' => str_replace(['\r\n', '\r', '\n'], ["\r\n", "\r", "\n"], (string) $text),
            'show_alert' => (bool) ($show_alert && self::$display_alert_in_bot),
            'cache_time' => 5,
        ];
        self::$callback_ack_sent = true;
        return TelegramApiAdapter::request('answerCallbackQuery', $params);
    }
    public static function keyboard(array $keyboard_layout)  {
        // Sparse numeric keys encode as JSON objects, not Telegram arrays.
        // Normalize only the row/column lists, preserving button field names.
        $rows = [];
        foreach ($keyboard_layout as $row) {
            if (is_array($row) && $row !== []) {
                $rows[] = array_values($row);
            }
        }
        $keyboard_layout = $rows;
        if (self::isInlineKeyboard($keyboard_layout)) {
            return ['inline_keyboard' => $keyboard_layout];
        }
        return [
            'keyboard'          => $keyboard_layout,
            'resize_keyboard'   => true,
            'one_time_keyboard' => false,
            'selective'         => false,
        ];
    }
    public static function isInlineKeyboard(array $keyboard_layout)  {
        $inline_keyboard_keys = ['url','callback_data','switch_inline_query','switch_inline_query_current_chat','callback_game'];
        return self::findKey($keyboard_layout, $inline_keyboard_keys);
    }
    protected static function findKey(array $array,array $keys_search)  {
        foreach ($array as $key => $item)  {
            if ( in_array($key, $keys_search) )  {
                return true;
            }  else  {
                if (is_array($item) && self::findKey($item, $keys_search))  {
                    return true;
                }
            }
        }
        return false;
    }
    public static function parseText($text, $type)  {
        $text = (string) $text;
        // Unescape literal newlines so \n does not render as text in chat
        $text = str_replace(['\r\n', '\r', '\n'], ["\r\n", "\r", "\n"], $text);
        if (function_exists('remove_filter')) {
            remove_filter( 'the_content', 'wpautop' );
            if (isset($GLOBALS['wp_embed'])) {
                remove_filter( 'the_content', array( $GLOBALS['wp_embed'], 'autoembed' ), 8 );
            }
        }
        if ($type == 'text')  {
            $text = str_replace('<b>', '*', $text);
            $text = str_replace('</b>', '*', $text);
        }
        $text = str_replace('%CHAT_ID%', (string) (self::$chat_id ?? ''), $text);
        if (function_exists('get_post_meta')) {
            $o = self::getUser();
            if ($o)  {
                $text = str_replace('%FIRST_NAME%', get_post_meta($o->ID, 'telegram_first_name', true), $text);
                $text = str_replace('%LAST_NAME%', get_post_meta($o->ID, 'telegram_last_name', true), $text);
                $text = str_replace('%USERNAME%', get_post_meta($o->ID, 'telegram_username', true), $text);
                $text = str_replace('%LOCATION_LATITUDE%', get_post_meta($o->ID, 'telegram_last_latitude', true), $text);
                $text = str_replace('%LOCATION_LONGITUDE%', get_post_meta($o->ID, 'telegram_last_longitude', true), $text);
            }  else  {
                $o = self::getGroup();
                if ($o)  {
                    $text = str_replace('%FIRST_NAME%', (string) get_post_meta($o->ID, 'telegram_name', true), $text);
                    $text = str_replace('%LAST_NAME%', (string) get_post_meta($o->ID, 'telegram_last_name', true), $text);
                    $text = str_replace('%USERNAME%', (string) get_post_meta($o->ID, 'telegram_username', true), $text);
                }
            }
        }
        $allowed_tags = ($type === 'HTML') ? '<b><strong><i><em><u><ins><s><strike><del><a><code><pre><blockquote><tg-spoiler>' : '';
        return str_replace('×', 'x', strip_tags( html_entity_decode( $text ), $allowed_tags ));
    }
    public static function isCallback()  {
        return self::$update_type === 'callback_query';
    }
    public static function log($message)  {
        if ( self::isTestMod() ) {
            echo "\n{$message}\n";
        }
        woogram_log('request',self::$chat_id,$message);
    }
    public static function isTestMod()  {
        if ( woogram_option('testmod') == 'yes' OR self::$command == '/debug') return true;
        return false;
    }
    public static function testMod()  {
        if ( self::isTestMod() )  {
            $params = array();
            if(!empty(self::$group_chat_id)) {
                $params['chat_id'] = self::fixPersianChar(self::$group_chat_id);
            }  else  {
                $params['chat_id'] = self::fixPersianChar(self::$chat_id);
            }
            $site_url = get_site_url();
            $wp_version = get_bloginfo('version');
            $woo_version = function_exists('WC') && isset(WC()->version) ? WC()->version : 'N/A';
            global $woogram_version;
            echo "Site: {$site_url}\n";
            echo "WP version: {$wp_version}\n";
            echo "Woo version: {$woo_version}\n";
            echo "Bot version: {$woogram_version}\n";
            $params['parse_mode'] = 'HTML';
            $params['disable_web_page_preview'] = false;
            $params['disable_notification'] = false;
            $params['text'] = self::botTranslate('⚠️ Bot Test Mode Active ⚠️', 'telenexa-commerce-for-telegram');
            $result = TelegramApiAdapter::request('sendMessage', $params);
            if(isset($result->ok) && $result->ok === false) {
                self::log('Telegram error send message: '.$result->description);
            }  else if(!isset($result->ok))  {
                self::log('Telegram error send message: result empty');
            }
        }
    }
}
