<?php

namespace TeleNexa\Telegram;

class Response extends Entity {
    public function isOk() {
        return !empty($this->data['ok']);
    }

    public function getOk() {
        return $this->isOk();
    }

    public function getResult() {
        return isset($this->data['result']) ? self::wrap($this->data['result']) : null;
    }

    public function getDescription() {
        return isset($this->data['description']) ? (string) $this->data['description'] : '';
    }

    public function getErrorCode() {
        return isset($this->data['error_code']) ? (int) $this->data['error_code'] : 0;
    }
}
