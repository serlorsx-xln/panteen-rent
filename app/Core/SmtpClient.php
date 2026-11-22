<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal SMTP client (no Composer needed): STARTTLS or implicit SSL, AUTH LOGIN,
 * multipart/alternative UTF-8 messages. Enough for Gmail App Passwords or any normal SMTP host.
 */
final class SmtpClient
{
    /** @var resource|null */
    private $socket = null;

    /** @param array<string, mixed> $cfg host, port, encryption, timeout, username, password */
    public function __construct(private array $cfg)
    {
    }

    public function send(string $fromEmail, string $fromName, string $to, string $subject, string $html, string $text): void
    {
        $host = $this->cfg['host'];
        $port = (int) $this->cfg['port'];
        $enc  = $this->cfg['encryption'] ?? '';
        $timeout = (int) ($this->cfg['timeout'] ?? 15);
        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;

        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $this->socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->socket) {
            throw new \RuntimeException("SMTP connect failed: $errstr ($errno)");
        }
        stream_set_timeout($this->socket, $timeout);

        $this->expect(220);
        $ehloHost = gethostname() ?: 'localhost';
        $this->cmd("EHLO $ehloHost", 250);
        if ($enc === 'tls') {
            $this->cmd('STARTTLS', 220);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new \RuntimeException('SMTP STARTTLS failed');
            }
            $this->cmd("EHLO $ehloHost", 250);
        }
        if (!empty($this->cfg['username'])) {
            $this->cmd('AUTH LOGIN', 334);
            $this->cmd(base64_encode($this->cfg['username']), 334);
            $this->cmd(base64_encode($this->cfg['password']), 235);
        }
        $this->cmd('MAIL FROM:<' . $fromEmail . '>', 250);
        $this->cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $this->cmd('DATA', 354);

        $boundary = 'b' . bin2hex(random_bytes(12));
        $enc8 = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $enc8($fromName) . " <$fromEmail>",
            "To: <$to>",
            'Subject: ' . $enc8($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . (explode('@', $fromEmail)[1] ?? 'localhost') . '>',
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"$boundary\"",
        ];
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$boundary--\r\n";
        $data = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        // Dot-stuffing (RFC 5321 4.5.2)
        $data = preg_replace('/^\./m', '..', $data);
        fwrite($this->socket, $data . "\r\n.\r\n");
        $this->expect(250);
        $this->cmd('QUIT', 221);
        fclose($this->socket);
        $this->socket = null;
    }

    /** @param int|list<int> $expect */
    private function cmd(string $line, int|array $expect): string
    {
        fwrite($this->socket, $line . "\r\n");
        return $this->expect($expect);
    }

    /** @param int|list<int> $codes */
    private function expect(int|array $codes): string
    {
        $codes = (array) $codes;
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException('SMTP error: ' . trim($response));
        }
        return $response;
    }
}
