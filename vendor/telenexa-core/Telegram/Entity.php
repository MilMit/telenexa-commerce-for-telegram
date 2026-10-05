<?php

namespace TeleNexa\Telegram;

/**
 * Small immutable-ish Telegram entity backed by the Bot API JSON payload.
 * Unknown fields remain available through get<Field>() without coupling the
 * application to a third-party entity class.
 */
class Entity {
    protected $data = [];

    public function __construct(array $data = []) {
        $this->data = $data;
    }

    public function getRawData() {
        return $this->data;
    }

    public function __get($field) {
        return array_key_exists($field, $this->data) ? self::wrap($this->data[$field]) : null;
    }

    public function __isset($field) {
        if (array_key_exists($field, $this->data)) {
            return true;
        }
        $snake_field = strtolower((string) preg_replace('/[A-Z]/', '_$0', (string) $field));
        return array_key_exists($snake_field, $this->data);
    }

    public function __call($method, $arguments) {
        if (strpos($method, 'get') !== 0) {
            return null;
        }

        $field = lcfirst(substr($method, 3));
        if (!array_key_exists($field, $this->data)) {
            // Telegram uses snake_case keys while the bot code uses the
            // natural PHP getter form, e.g. getMessageId() -> message_id.
            $snake_field = strtolower((string) preg_replace('/[A-Z]/', '_$0', $field));
            if (!array_key_exists($snake_field, $this->data)) {
                return null;
            }
            $field = $snake_field;
        }

        return self::wrap($this->data[$field]);
    }

    protected static function wrap($value) {
        if (is_array($value)) {
            if (self::isList($value)) {
                return array_map([self::class, 'wrap'], $value);
            }
            return new self($value);
        }
        return $value;
    }

    private static function isList(array $value) {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }
}
