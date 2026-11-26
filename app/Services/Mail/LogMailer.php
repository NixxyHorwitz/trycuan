<?php
declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Env;

/** Untuk lokal: email ditulis ke storage/logs/mail.log, tidak dikirim. */
final class LogMailer extends Mailer
{
    public function send(string $to, string $subject, string $text): void
    {
        // Link reset tidak boleh tertulis di log server publik.
        if (str_starts_with(Env::get('APP_URL'), 'https://')) throw new \RuntimeException('MAIL_DRIVER belum diatur ke smtp');
        file_put_contents(ROOT . '/storage/logs/mail.log', '[' . date('c') . "] $to\n$subject\n$text\n\n", FILE_APPEND | LOCK_EX);
    }
}
