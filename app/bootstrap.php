<?php
declare(strict_types=1);

/*
 * Bootstrap: autoloader, configuration, error handling, session.
 * Shared by the web front controller, the CLI scripts and the tests.
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('PUBLIC_PATH', BASE_PATH . '/public');

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/helpers.php';

$configFile = BASE_PATH . '/config/config.php';
if (!is_file($configFile)) {
    $configFile = BASE_PATH . '/config/config.example.php';
}
$config = require $configFile;

// Tests and CLI tools may override settings (for example a separate test database).
if (getenv('PANTEEN_DB_NAME')) {
    $config['db']['name'] = getenv('PANTEEN_DB_NAME');
}
if (getenv('PANTEEN_DB_HOST')) {
    $config['db']['host'] = getenv('PANTEEN_DB_HOST');
}
if (getenv('PANTEEN_DB_PORT')) {
    $config['db']['port'] = (int) getenv('PANTEEN_DB_PORT');
}
if (getenv('PANTEEN_MAIL_DRIVER')) {
    $config['mail']['driver'] = getenv('PANTEEN_MAIL_DRIVER');
}
// Container deployments (Docker / Coolify) pass the rest the same way instead of a config.php file.
foreach ([
    'PANTEEN_DB_USER' => ['db', 'user'], 'PANTEEN_DB_PASS' => ['db', 'pass'],
    'PANTEEN_APP_ENV' => ['app', 'env'], 'PANTEEN_APP_URL' => ['app', 'url'], 'PANTEEN_APP_KEY' => ['app', 'key'],
    'PANTEEN_MAIL_FROM' => ['mail', 'from_email'], 'PANTEEN_SMTP_HOST' => ['mail', 'smtp', 'host'],
    'PANTEEN_SMTP_USER' => ['mail', 'smtp', 'username'], 'PANTEEN_SMTP_PASS' => ['mail', 'smtp', 'password'],
] as $env => $keys) {
    $v = getenv($env);
    if ($v === false || $v === '') {
        continue;
    }
    if (count($keys) === 3) {
        $config[$keys[0]][$keys[1]][$keys[2]] = $v;
    } else {
        $config[$keys[0]][$keys[1]] = $v;
    }
}

App\Core\Config::load($config);

date_default_timezone_set(App\Core\Config::get('app.timezone', 'Asia/Bangkok'));
mb_internal_encoding('UTF-8');

if (App\Core\Config::get('app.env') === 'dev') {
    error_reporting(E_ALL);
    ini_set('display_errors', PHP_SAPI === 'cli' ? '1' : '0');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
