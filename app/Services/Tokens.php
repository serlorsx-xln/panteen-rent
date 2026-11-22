<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** One-time e-mail tokens. Only a SHA-256 hash is stored, so a database leak cannot be replayed. */
final class Tokens
{
    public const TTL = ['verify' => 48 * 3600, 'reset' => 3600];

    public static function issue(int $userId, string $type): string
    {
        // Older unused tokens of the same type stop working.
        DB::run('UPDATE email_tokens SET used_at = NOW() WHERE user_id = ? AND type = ? AND used_at IS NULL', [$userId, $type]);
        $token = bin2hex(random_bytes(32));
        DB::insert('email_tokens', [
            'user_id' => $userId,
            'type' => $type,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL[$type]),
        ]);
        return $token;
    }

    /**
     * Returns the token row when valid (and marks it used), null otherwise.
     * @return array<string, mixed>|null
     */
    public static function consume(string $token, string $type, bool $markUsed = true): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $row = DB::one(
            'SELECT * FROM email_tokens WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > NOW()',
            [hash('sha256', $token), $type]
        );
        if ($row && $markUsed) {
            DB::update('email_tokens', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);
        }
        return $row;
    }

    /** @param array<string, mixed> $user */
    public static function sendVerification(array $user): void
    {
        $token = self::issue((int) $user['id'], 'verify');
        \App\Core\Mailer::send($user['email'], 'ยืนยันอีเมลของคุณ · ' . Settings::get('shop_name'), 'notice', [
            'user' => $user,
            'heading' => 'ยืนยันอีเมลก่อนเริ่มจองชุด',
            'lines' => [
                'สวัสดีคุณ' . ($user['nickname'] ?: $user['first_name']) . ' ขอบคุณที่สมัครสมาชิกกับร้านเรา',
                'กดปุ่มด้านล่างเพื่อยืนยันว่าอีเมลนี้เป็นของคุณ ลิงก์ใช้ได้ภายใน 48 ชั่วโมง',
            ],
            'button' => ['ยืนยันอีเมล', absolute_url('/verify/' . $token)],
            'note' => 'ถ้าคุณไม่ได้สมัครสมาชิก ไม่ต้องทำอะไร บัญชีจะไม่ถูกเปิดใช้งาน',
        ]);
    }

    /** @param array<string, mixed> $user */
    public static function sendReset(array $user): void
    {
        $token = self::issue((int) $user['id'], 'reset');
        \App\Core\Mailer::send($user['email'], 'ตั้งรหัสผ่านใหม่ · ' . Settings::get('shop_name'), 'notice', [
            'user' => $user,
            'heading' => 'ตั้งรหัสผ่านใหม่',
            'lines' => [
                'มีคำขอตั้งรหัสผ่านใหม่สำหรับบัญชี ' . $user['email'],
                'กดปุ่มด้านล่างภายใน 1 ชั่วโมงเพื่อตั้งรหัสผ่านใหม่',
            ],
            'button' => ['ตั้งรหัสผ่านใหม่', absolute_url('/reset/' . $token)],
            'note' => 'ถ้าคุณไม่ได้ขอ ไม่ต้องทำอะไร รหัสผ่านเดิมยังใช้ได้ตามปกติ',
        ]);
    }
}
