<?php
if (!defined('ABSPATH')) exit;

\TeleNexa\WooBot::init('in');

$woogram_key = (string) get_option('woogram_webhook_key');
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public Telegram webhook endpoint verified via webhook key and secret token.
if (empty($woogram_key) || !isset($_GET[$woogram_key])) {
    woogram_log('WebHook parse', 'Key empty', '');
    status_header(403);
    die('Forbidden: Key empty');
}

// Security: Verify Telegram Webhook Secret Token if configured
if (!\TeleNexa\WooBot::validateWebhookSecretToken()) {
    status_header(403);
    die('Forbidden: Invalid Webhook Secret Token');
}

\TeleNexa\WooBot::handle();
die('END PARSE');