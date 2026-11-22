<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Mailer;

/**
 * In-app notifications (bell) plus optional e-mail. A dedupe key makes reminders idempotent:
 * the same reminder is never stored or mailed twice.
 */
final class Notifier
{
    /**
     * @param array{subject: string, view?: string, data?: array<string, mixed>}|null $mail ['subject' => ..., 'view' => 'notice', 'data' => [...]]
     * @return bool false when the dedupe key already existed
     */
    public static function user(int $userId, string $type, string $title, ?string $body = null, ?string $link = null, ?string $dedupe = null, ?array $mail = null): bool
    {
        try {
            DB::insert('notifications', [
                'user_id' => $userId,
                'type' => $type,
                'title' => mb_substr($title, 0, 200),
                'body' => $body !== null ? mb_substr($body, 0, 500) : null,
                'link' => $link,
                'dedupe_key' => $dedupe,
            ]);
        } catch (\PDOException $e) {
            if ($dedupe !== null && ($e->errorInfo[1] ?? 0) === 1062) {
                return false;
            }
            throw $e;
        }
        if ($mail) {
            $user = DB::one('SELECT email, first_name, nickname FROM users WHERE id = ?', [$userId]);
            if ($user) {
                try {
                    Mailer::send($user['email'], $mail['subject'], $mail['view'] ?? 'notice', ($mail['data'] ?? []) + ['user' => $user]);
                } catch (\Throwable $e) {
                    // A mail problem must never undo the booking action that triggered it.
                    error_log('[notify-mail] ' . $e->getMessage());
                }
            }
        }
        return true;
    }

    /** Notify every active admin and staff member. True when at least one of them got it (not already sent). */
    public static function staff(string $type, string $title, ?string $body = null, ?string $link = null, ?string $dedupe = null): bool
    {
        $sent = false;
        foreach (DB::all("SELECT id FROM users WHERE role IN ('admin','staff') AND status = 'active'") as $s) {
            $sent = self::user((int) $s['id'], $type, $title, $body, $link, $dedupe ? $dedupe . ':u' . $s['id'] : null) || $sent;
        }
        return $sent;
    }

    public static function unreadCount(int $userId): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }

    /** @return list<array<string, mixed>> */
    public static function recent(int $userId, int $limit = 30): array
    {
        return DB::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit, [$userId]);
    }

    public static function markAllRead(int $userId): void
    {
        DB::run('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }
}
