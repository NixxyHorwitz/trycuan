<?php
declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Env;
use App\Models\Setting;

abstract class Mailer
{
    /** @throws \RuntimeException */
    abstract public function send(string $to, string $subject, string $text): void;

    public static function make(): self
    {
        if (Setting::str('smtp_host') !== '') {
            return new SmtpMailer(Setting::str('smtp_host'), Setting::int('smtp_port'), Setting::str('smtp_secure'), Setting::str('smtp_user'), Setting::secret('smtp_pass'), Setting::str('mail_from'));
        }
        return Env::get('MAIL_DRIVER') === 'smtp' ? SmtpMailer::fromEnv() : new LogMailer();
    }

    /** Pesan lengkap siap kirim; isi di-base64 sehingga aman dari baris titik. */
    protected static function message(string $from, string $to, string $subject, string $text): string
    {
        foreach ([$from, $to] as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Alamat email tidak valid');
        }
        $domain = substr((string) strrchr($from, '@'), 1);
        return implode("\r\n", [
            'Date: ' . date(DATE_RFC2822),
            'From: =?UTF-8?B?' . base64_encode(Setting::str('site_name')) . "?= <$from>",
            "To: <$to>",
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Message-ID: <' . bin2hex(random_bytes(16)) . "@$domain>",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Auto-Submitted: auto-generated',
            '',
            rtrim(chunk_split(base64_encode($text), 76, "\r\n")),
            '',
        ]);
    }
}
