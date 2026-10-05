<?php
/**
 * TeleNexa PSR-4 Autoloader
 *
 * Provides PSR-4 compliant autoloading for TeleNexa classes and traits.
 * Also provides backwards compatibility aliases for legacy WooGram namespace.
 */

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

spl_autoload_register(function ($class) {
    // 1. TeleNexa namespace
    $prefix = 'TeleNexa\\';
    $base_dir = __DIR__ . '/telenexa-core/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) === 0) {
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }

    // 2. Legacy WooGram backwards-compatibility alias
    $legacy_prefix = 'WooGram\\';
    $legacy_len = strlen($legacy_prefix);

    if (strncmp($legacy_prefix, $class, $legacy_len) === 0) {
        $relative_class = substr($class, $legacy_len);
        $telenexa_class = 'TeleNexa\\' . $relative_class;

        // Ensure TeleNexa class is loaded
        if (!class_exists($telenexa_class, false) && !trait_exists($telenexa_class, false) && !interface_exists($telenexa_class, false)) {
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }

        if ((class_exists($telenexa_class, false) || trait_exists($telenexa_class, false) || interface_exists($telenexa_class, false))
            && !class_exists($class, false) && !trait_exists($class, false) && !interface_exists($class, false)) {
            class_alias($telenexa_class, $class);
            return true;
        }
    }

    return false;
});
