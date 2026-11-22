<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            return;
        }
        session_name('panteen_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => base_path() ?: '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '86400');
        $dir = STORAGE_PATH . '/cache/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $v = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $v;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
