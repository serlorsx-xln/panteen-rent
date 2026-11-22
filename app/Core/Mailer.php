<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sends e-mail. Every message is stored in mail_outbox (the simulated mailbox).
 * With driver "smtp" it is also delivered through SmtpClient.
 */
final class Mailer
{
    /**
     * @param array<string, mixed> $data
     * @return int outbox id
     */
    public static function send(string $to, string $subject, string $view, array $data = []): int
    {
        $data['subject'] = $subject;
        $html = View::make('emails/layout', $data + ['content' => View::make('emails/' . $view, $data)]);
        $text = self::toText($html);

        $id = DB::insert('mail_outbox', [
            'to_email' => $to,
            'subject'  => $subject,
            'html'     => $html,
            'text_body'=> $text,
            'status'   => 'logged',
        ]);

        if (Config::get('mail.driver') === 'smtp') {
            try {
                (new SmtpClient(Config::get('mail.smtp')))->send(
                    Config::get('mail.from_email'),
                    Config::get('mail.from_name'),
                    $to,
                    $subject,
                    $html,
                    $text
                );
                DB::update('mail_outbox', ['status' => 'sent'], 'id = :id', ['id' => $id]);
            } catch (\Throwable $e) {
                DB::update('mail_outbox', ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)], 'id = :id', ['id' => $id]);
                error_log('[mail] ' . $e->getMessage());
            }
        }
        return $id;
    }

    public static function toText(string $html): string
    {
        $html = preg_replace('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2 ($1)', $html);
        $html = preg_replace('#<(br|/p|/div|/tr|/h[1-6]|/li)[^>]*>#i', "\n", $html);
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);
        return trim(preg_replace("/\n\s*\n\s*\n+/", "\n\n", $text));
    }
}
