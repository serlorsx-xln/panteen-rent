<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public const MAX_ATTEMPTS = 5;
    public const LOCK_MINUTES = 15;

    /** Checked against when the e-mail is unknown, so a wrong address takes as long to answer as a wrong password */
    private const DUMMY_HASH = '$2y$12$AUfnW4mrM18wHUKIrZnL6.AdSzDKjQL5fHAFehvh5/bZOVEAf2VNa';

    /** @var array<string, mixed>|null */
    private static ?array $user = null;
    private static bool $loaded = false;

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('user_id');
            if ($id) {
                $u = DB::one('SELECT * FROM users WHERE id = ?', [(int) $id]);
                // A suspended account is signed out on its next request, and so is every session
                // that signed in before the password last changed (a reset locks out whoever had the old one).
                if ($u && $u['status'] === 'active' && hash_equals(self::passwordMark($u['password_hash']), (string) Session::get('pw'))) {
                    self::$user = $u;
                } else {
                    Session::forget('user_id');
                    Session::forget('pw');
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::user() ? (int) self::user()['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): string
    {
        return self::user()['role'] ?? 'guest';
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isStaff(): bool
    {
        return in_array(self::role(), ['admin', 'staff'], true);
    }

    /** Forget the cached user (after profile changes). */
    public static function refresh(): void
    {
        self::$loaded = false;
        self::$user = null;
    }

    public static function isLockedOut(string $email, string $ip): bool
    {
        $fails = (int) DB::value(
            'SELECT COUNT(*) FROM login_attempts
              WHERE success = 0 AND (email = ? OR ip = ?) AND created_at > (NOW() - INTERVAL ? MINUTE)',
            [mb_strtolower($email), $ip, self::LOCK_MINUTES]
        );
        return $fails >= self::MAX_ATTEMPTS * 2 || (int) DB::value(
            'SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND email = ? AND created_at > (NOW() - INTERVAL ? MINUTE)',
            [mb_strtolower($email), self::LOCK_MINUTES]
        ) >= self::MAX_ATTEMPTS;
    }

    /**
     * @return array{ok:bool, error?:string, user?:array<string, mixed>}
     */
    public static function attempt(string $email, string $password, string $ip): array
    {
        $email = mb_strtolower(trim($email));
        if (self::isLockedOut($email, $ip)) {
            return ['ok' => false, 'error' => 'ลองรหัสผ่านผิดหลายครั้งเกินไป กรุณารอ ' . self::LOCK_MINUTES . ' นาทีแล้วลองใหม่ หรือกด “ลืมรหัสผ่าน”'];
        }
        $user = DB::one('SELECT * FROM users WHERE email = ?', [$email]);
        $ok = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH) && $user;
        DB::insert('login_attempts', ['email' => $email, 'ip' => $ip, 'success' => $ok ? 1 : 0]);
        if (!$ok) {
            return ['ok' => false, 'error' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'];
        }
        if ($user['status'] !== 'active') {
            return ['ok' => false, 'error' => 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อร้านเพื่อสอบถาม'];
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
        }
        self::login((int) $user['id']);
        return ['ok' => true, 'user' => $user];
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::put('user_id', $userId);
        Session::put('pw', self::passwordMark((string) DB::value('SELECT password_hash FROM users WHERE id = ?', [$userId])));
        DB::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $userId]);
        self::refresh();
    }

    public static function logout(): void
    {
        Session::forget('user_id');
        Session::forget('pw');
        Session::regenerate();
        self::refresh();
    }

    /** After the signed-in user changes their own password: keep this session, while every other one is signed out. */
    public static function keepAfterPasswordChange(string $newHash): void
    {
        Session::regenerate();
        Session::put('pw', self::passwordMark($newHash));
        self::refresh();
    }

    private static function passwordMark(string $hash): string
    {
        return hash('sha256', $hash);
    }
}
