<?php
namespace TeleNexa\Telegram;
class CallbackQuery extends Entity {
    public function getFrom() { return new User((array) ($this->data['from'] ?? [])); }
    public function getMessage() { return new Message((array) ($this->data['message'] ?? [])); }
}
