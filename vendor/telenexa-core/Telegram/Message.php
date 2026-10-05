<?php
namespace TeleNexa\Telegram;
class Message extends Entity {
    public function getFrom() { return new User((array) ($this->data['from'] ?? [])); }
    public function getChat() { return new Chat((array) ($this->data['chat'] ?? [])); }
    public function getSuccessfulPayment() {
        return isset($this->data['successful_payment']) ? new SuccessfulPayment((array) $this->data['successful_payment']) : null;
    }
}
