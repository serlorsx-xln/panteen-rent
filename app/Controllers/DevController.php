<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\DB;

/**
 * Simulated mailbox: every e-mail the system sends, readable in the browser.
 * Who may open it is mailbox_open(): admins, or dev mode on this machine.
 */
final class DevController extends Controller
{
    private function guard(): void
    {
        if (!mailbox_open()) {
            abort(404);
        }
    }

    public function mailbox(): void
    {
        $this->guard();
        $to = input_str('to');
        $sql = 'SELECT id, to_email, subject, status, created_at FROM mail_outbox';
        $params = [];
        if ($to !== '') {
            $sql .= ' WHERE to_email = ?';
            $params[] = mb_strtolower($to);
        }
        $this->view('dev/mailbox', [
            'title' => 'กล่องจดหมายจำลอง',
            'mails' => DB::all($sql . ' ORDER BY id DESC LIMIT 100', $params),
            'to' => $to,
            'driver' => Config::get('mail.driver'),
        ]);
    }

    public function mail(string $id): void
    {
        $this->guard();
        $m = DB::one('SELECT * FROM mail_outbox WHERE id = ?', [$this->intId($id)]);
        if (!$m) {
            abort(404);
        }
        if (isset($_GET['raw'])) {
            header('Content-Type: text/html; charset=utf-8');
            header("Content-Security-Policy: default-src 'none'; img-src * data:; style-src 'unsafe-inline'; font-src *");
            echo $m['html'];
            return;
        }
        $this->view('dev/mail', ['title' => $m['subject'], 'm' => $m]);
    }
}
