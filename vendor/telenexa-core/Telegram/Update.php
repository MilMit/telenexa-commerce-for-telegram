<?php

namespace TeleNexa\Telegram;

class Update extends Entity {
    public function getUpdateId() {
        return isset($this->data['update_id']) ? (int) $this->data['update_id'] : 0;
    }

    public function getUpdateType() {
        foreach (['pre_checkout_query', 'callback_query', 'message', 'edited_message', 'channel_post', 'inline_query'] as $type) {
            if (isset($this->data[$type])) {
                return $type;
            }
        }
        return '';
    }

    public function getMessage() { return new Message((array) ($this->data['message'] ?? [])); }
    public function getCallbackQuery() { return new CallbackQuery((array) ($this->data['callback_query'] ?? [])); }
    public function getPreCheckoutQuery() { return new PreCheckoutQuery((array) ($this->data['pre_checkout_query'] ?? [])); }
}
