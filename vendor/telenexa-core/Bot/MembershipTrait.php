<?php
namespace TeleNexa\Bot;

/**
 * Enforces configurable Telegram channel/group membership before the shop is
 * opened. The check is deliberately performed on every private update so a
 * user who leaves a required channel is locked out again automatically.
 */
trait MembershipTrait {
    protected static $membership_cache = [];

    public static function resetMembershipCache() {
        self::$membership_cache = [];
    }

    public static function membershipRequirements() {
        if (woogram_option('membership_enabled') === '0') return [];
        $requirements = woogram_option('membership_requirements');
        if (!is_array($requirements)) return [];

        $result = [];
        foreach ($requirements as $requirement) {
            if (!is_array($requirement) || empty($requirement['enabled'])) continue;
            $chat_id = trim((string) ($requirement['chat_id'] ?? ''));
            if ($chat_id === '') continue;
            $title = trim((string) ($requirement['title'] ?? ''));
            $url = trim((string) ($requirement['url'] ?? ''));
            if ($url === '' && preg_match('/^@?[A-Za-z0-9_]{4,}$/', $chat_id)) {
                $url = 'https://t.me/' . ltrim($chat_id, '@');
            }
            $result[] = [
                'chat_id' => $chat_id,
                'title'   => $title !== '' ? $title : $chat_id,
                'url'     => $url,
                'type'    => in_array(($requirement['type'] ?? 'channel'), ['channel', 'group'], true) ? $requirement['type'] : 'channel',
            ];
        }
        return array_slice($result, 0, 10);
    }

    public static function membershipStatus($requirement, $user_id) {
        $cache_key = (string) $requirement['chat_id'] . ':' . (string) $user_id;
        if (array_key_exists($cache_key, self::$membership_cache)) return self::$membership_cache[$cache_key];

        $response = self::telegramApiCall('getChatMember', [
            'chat_id' => $requirement['chat_id'],
            'user_id' => (string) $user_id,
        ]);
        if (is_wp_error($response) || !is_array($response) || empty($response['ok'])) {
            self::logStructured('membership_check_error', [
                'chat_id' => $requirement['chat_id'],
                'user_id' => $user_id,
            ]);
            return self::$membership_cache[$cache_key] = false;
        }

        $member = isset($response['result']) && is_array($response['result']) ? $response['result'] : [];
        $status = (string) ($member['status'] ?? 'left');
        self::$membership_cache[$cache_key] = $status;
        return $status;
    }

    public static function isMemberOfRequirement($requirement, $user_id) {
        $status = self::membershipStatus($requirement, $user_id);
        return in_array($status, ['creator', 'administrator', 'member'], true) || $status === 'restricted';
    }

    public static function membershipMissingRequirements() {
        $missing = [];
        foreach (self::membershipRequirements() as $requirement) {
            $status = self::membershipStatus($requirement, self::$chat_id);
            if (woogram_option('membership_apply_to_admins') === '0' && in_array($status, ['creator', 'administrator'], true)) continue;
            if (!self::isMemberOfRequirement($requirement, self::$chat_id)) $missing[] = $requirement;
        }
        return $missing;
    }

    public static function enforceMembership() {
        if (self::$chat_type !== 'private' || !self::membershipRequirements()) return true;
        $missing = self::membershipMissingRequirements();
        if (!$missing) return true;

        $language = method_exists(__CLASS__, 'getLanguage') ? self::getLanguage() : 'fa';
        $text = self::botText(
            'membership.required',
            "🔒 <b>عضویت الزامی است</b>\n\nلطفاً ابتدا در همهٔ کانال‌ها و گروه‌های زیر عضو شوید، سپس روی «بررسی عضویت» بزنید.",
            "🔒 <b>Membership required</b>\n\nPlease join all required channels/groups below, then tap <b>Check membership</b>.",
            $language
        );
        $keyboard = [];
        foreach ($missing as $requirement) {
            if ($requirement['url'] !== '') {
                $title = self::translateBotString(
                    'membership.requirement.' . md5($requirement['chat_id']),
                    $requirement['title'] !== '' ? $requirement['title'] : $requirement['chat_id'],
                    $language
                );
                $keyboard[] = [['text' => '📢 ' . $title, 'url' => $requirement['url']]];
            }
        }
        $keyboard[] = [['text' => self::botText('membership.check', '✅ بررسی عضویت', '✅ Check membership', $language), 'callback_data' => '/membership_check']];
        self::sendMessage(['text' => $text, 'keyboard' => $keyboard]);
        self::logStructured('membership_required', [
            'chat_id' => self::$chat_id,
            'missing' => array_column($missing, 'chat_id'),
        ]);
        return false;
    }
}
