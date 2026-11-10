<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';

/**
 * Autoloader otomatis untuk seluruh class/interface di dalam folder class/.
 * Nama file sama persis dengan nama class/interface.
 */
spl_autoload_register(static function (string $className): void {
    $safeName = basename(str_replace('\\', '/', $className));
    $filePath = dirname(__DIR__) . '/class/' . $safeName . '.php';

    if (is_file($filePath)) {
        require_once $filePath;
    }
});
