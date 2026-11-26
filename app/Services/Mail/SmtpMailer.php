<?php
declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Env;

final class SmtpMailer extends Mailer
{
    private const TIMEOUT = 10;
    /** @var resource */
    private $socket;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $secure,
        private readonly string $user,
        private readonly string $pass,
        private readonly string $from,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(Env::get('SMTP_HOST'), (int) Env::get('SMTP_PORT', '587'), Env::get('SMTP_SECURE', 'tls'), Env::get('SMTP_USER'), Env::get('SMTP_PASS'), Env::get('MAIL_FROM'));
    }

    public function send(string $to, string $subject, string $text): void
    {
        $message = self::message($this->from, $to, $subject, $text);
        // Tanpa enkripsi hanya untuk server uji di komputer sendiri.
        if (!in_array($this->secure, ['tls', 'ssl'], true) && $this->host !== '127.0.0.1') throw new \RuntimeException('SMTP_SECURE harus tls atau ssl');
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $this->host]]);
        $scheme = $this->secure === 'ssl' ? 'ssl' : 'tcp';
        $socket = @stream_socket_client("$scheme://{$this->host}:{$this->port}", $code, $error, self::TIMEOUT, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) throw new \RuntimeException("SMTP tidak terhubung: $error");
        $this->socket = $socket;
        stream_set_timeout($socket, self::TIMEOUT);
        try {
            $this->expect(220);
            $this->command('EHLO ' . substr((string) strrchr($this->from, '@'), 1), 250);
            if ($this->secure === 'tls') {
                $this->command('STARTTLS', 220);
                if (@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) throw new \RuntimeException('SMTP TLS gagal');
                $this->command('EHLO ' . substr((string) strrchr($this->from, '@'), 1), 250);
            }
            if ($this->user !== '') {
                $this->command('AUTH LOGIN', 334);
                $this->command(base64_encode($this->user), 334);
                $this->command(base64_encode($this->pass), 235);
            }
            $this->command("MAIL FROM:<{$this->from}>", 250);
            $this->command("RCPT TO:<$to>", 250);
            $this->command('DATA', 354);
            $this->command($message . "\r\n.", 250);
            // Pesan sudah diterima; gagal menutup koneksi tidak berarti gagal kirim.
            @fwrite($socket, "QUIT\r\n");
        } finally {
            fclose($socket);
        }
    }

    private function command(string $line, int $want): void
    {
        $data = "$line\r\n";
        while ($data !== '') {
            $sent = fwrite($this->socket, $data);
            if (!$sent) throw new \RuntimeException('SMTP terputus');
            $data = substr($data, $sent);
        }
        $this->expect($want);
    }

    private function expect(int $want): void
    {
        do {
            $line = fgets($this->socket, 1024);
            if ($line === false) throw new \RuntimeException('SMTP tidak menjawab');
        } while (isset($line[3]) && $line[3] === '-');
        // Jawaban server tidak memuat password, jadi aman masuk log.
        if ((int) $line !== $want) throw new \RuntimeException('SMTP menolak: ' . substr(trim($line), 0, 150));
    }
}
