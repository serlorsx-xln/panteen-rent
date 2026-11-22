<?php
declare(strict_types=1);

/*
 * Front controller. Every request that is not a real file under public/ arrives here.
 */

// PHP built-in server: let it serve real files (CSS, JS, images) directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Session;
use App\Core\View;

set_exception_handler(static function (Throwable $e): void {
    error_log('[uncaught] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $isDb = $e instanceof PDOException;
    $message = $isDb
        ? 'เชื่อมต่อฐานข้อมูลไม่ได้ ตรวจว่าเปิด MySQL ใน XAMPP แล้ว และนำเข้าไฟล์ database/panteen_rent.sql เรียบร้อย'
        : 'ขออภัย ลองใหม่อีกครั้งในอีกสักครู่';
    // the technical detail only for someone at this machine in dev mode: SQL errors and file paths are not for visitors
    if (App\Core\Config::get('app.env') === 'dev' && is_local_request()) {
        $message .= ' · ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
    }
    if (wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        return;
    }
    try {
        View::render('errors/error', ['code' => 500, 'title' => 'ระบบขัดข้องชั่วคราว', 'message' => $message], 'layouts/bare');
    } catch (Throwable) {
        echo '<!doctype html><meta charset="utf-8"><title>Error</title><p>' . htmlspecialchars($message) . '</p>';
    }
});

header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// container health check: answers before sessions and reminders, and fails while the database is down
if (request_path() === '/healthz') {
    header('Content-Type: text/plain');
    header('Cache-Control: no-store');
    try {
        App\Core\DB::value('SELECT 1');
        echo 'ok';
    } catch (Throwable) {
        http_response_code(503);
        echo 'db down';
    }
    return;
}

Session::start();
App\Services\Reminders::lazyRun();

$router = require APP_PATH . '/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', request_path());
