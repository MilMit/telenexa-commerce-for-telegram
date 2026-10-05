<?php
namespace TeleNexa\Bot;

/** UserSession behavior for WooBot. */
trait UserSessionTrait {
    public static function getUser()  {
        global $wpdb;
        $chat_id = self::fixPersianChar(self::$chat_id);
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'woogram_user' ORDER BY ID DESC LIMIT 1",
            $chat_id
        ));
        if ( !$post_id )  {
            $persian_chat_id = self::toPersian(self::$chat_id);
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'woogram_user' ORDER BY ID DESC LIMIT 1",
                $persian_chat_id
            ));
        }
        if ( $post_id )  {
            $page = get_post($post_id);
            if ($page) {
                $page->post_title = self::fixPersianChar($page->post_title);
                return $page;
            }
        }
        return array();
    }
    public static function updateUser($update_id=0)  {
        $p = self::getUser();
        if(empty($p)) {
            $post_id = wp_insert_post([
                'post_title'   => self::fixPersianChar(self::$chat_id),
                'post_content' => '',
                'post_type'    => 'woogram_user',
                'post_status'  => 'private',
                'post_author'  => 1,
                'guid'         => $update_id
            ]);
        }  else  {
            $post_id = is_object($p) ? $p->ID : (int)$p;
            if( $update_id && is_object($p) && $p->guid === $update_id ) {
                die('repeat');
            }
            wp_update_post( ['ID' => $post_id, 'guid' => $update_id, 'post_status' => 'private'] );
        }
        if ($post_id) {
            update_post_meta($post_id, 'telegram_first_name', self::$first_name);
            update_post_meta($post_id, 'telegram_last_name', self::$last_name);
            update_post_meta($post_id, 'telegram_username', self::$user_username);
        }
    }
    public static function getGroup()  {
        global $wpdb;
        $group_chat_id = self::fixPersianChar(self::$group_chat_id);
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'woogram_group' ORDER BY ID DESC LIMIT 1",
            $group_chat_id
        ));
        if ( !$post_id )  {
            $persian_group_chat_id = self::toPersian(self::$group_chat_id);
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'woogram_group' ORDER BY ID DESC LIMIT 1",
                $persian_group_chat_id
            ));
        }
        if ( $post_id )  {
            $page = get_post($post_id);
            if ($page) {
                $page->post_title = self::fixPersianChar($page->post_title);
                return $page;
            }
        }
        return array();
    }
    public static function updateGroup($update_id=0)  {
        if(empty(self::$group_chat_id)) {
            return false;
        }
        $p = self::getGroup();
        if(empty($p)) {
            $post_id = wp_insert_post(array(
                'post_title'   => self::fixPersianChar(self::$group_chat_id),
                'post_content' => '',
                'post_type'    => 'woogram_group',
                'post_status'  => 'private',
                'post_author'  => 1,
            ));
        }  else  {
            $post_id = is_object($p) ? $p->ID : (int)$p;
            if( $update_id && is_object($p) && $p->guid === $update_id ) {
                self::log('ERROR: update_id repeat > '.self::$command);
                die('repeat');
            }
            wp_update_post( ['ID' => $post_id, 'guid' => $update_id, 'post_status' => 'private'] );
        }
        if ($post_id) {
            update_post_meta($post_id, 'telegram_group_title', self::$group_title);
        }
    }
    public static function initSession()  {
        $o = self::getUser();
        $session = json_decode(stripslashes($o->post_content), true);
        if(!empty($session) AND is_array($session)) {
            self::$session = $session;
        }  else  {
            self::$session = array();
        }
        if(!self::existPhysicalProduct()) {
            self::$register_req_state = 0;
            self::$register_req_city = 0;
            self::$register_req_address = 0;
            self::$register_req_zip = 0;
            self::$register_req_shipping = 0;
        }
    }
    public static function updateSession()  {
        $o = self::getUser();
        if(isset($o->post_content)) {
            $o->post_content = json_encode(self::$session, JSON_UNESCAPED_UNICODE);
            wp_update_post($o);
        }
    }
    public static function setSession($key, $value)  {
        if(empty(self::$session) OR !is_array(self::$session)) {
            self::$session = array();
        }
        self::$session[$key] = $value;
    }
    public static function getSession($key)  {
        if(isset(self::$session[$key])) {
            return self::$session[$key];
        }  else  {
            return NULL;
        }
    }
    public static function unsetSession($key)  {
        if(isset(self::$session[$key])) {
            unset(self::$session[$key]);
        }
    }
    public static function getUserMeta($key) {
        $u = self::getUser();
        if ($u && isset($u->ID)) {
            return get_post_meta($u->ID, $key, true);
        }
        return null;
    }
    public static function updateUserMeta($key, $value) {
        $u = self::getUser();
        if ($u && isset($u->ID)) {
            return update_post_meta($u->ID, $key, $value);
        }
        return false;
    }
}
