<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }
        return $token;
    }

    public static function verifyRequest(): void
    {
        $sent = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || !hash_equals(self::token(), $sent)) {
            http_response_code(419);
            if (wants_json()) {
                json_response(['ok' => false, 'error' => 'หมดเวลาของหน้านี้ กรุณารีเฟรชแล้วลองอีกครั้ง'], 419);
            }
            View::render('errors/error', [
                'code' => 419,
                'title' => 'หน้านี้หมดอายุแล้ว',
                'message' => 'เพื่อความปลอดภัย ฟอร์มนี้หมดเวลาแล้ว กดย้อนกลับ รีเฟรชหน้า แล้วส่งใหม่อีกครั้ง',
            ], 'layouts/shop');
            exit;
        }
    }
}
