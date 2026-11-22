<?php
// What public/index.php sets up before any class loads, so PHPStan knows the constants and the helpers.
define('BASE_PATH', dirname(__DIR__, 2));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('PUBLIC_PATH', BASE_PATH . '/public');
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
require_once APP_PATH . '/helpers.php';
