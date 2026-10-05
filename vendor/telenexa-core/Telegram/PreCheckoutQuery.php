<?php
namespace TeleNexa\Telegram;
class PreCheckoutQuery extends Entity {
    public function getFrom() { return new User((array) ($this->data['from'] ?? [])); }
}
