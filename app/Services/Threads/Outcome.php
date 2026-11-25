<?php
declare(strict_types=1);

namespace App\Services\Threads;

enum Outcome: string
{
    case Ok = 'ok';
    case NotFound = 'not_found';
    case LoginRequired = 'login_required';
    case Blocked = 'blocked';
    case Network = 'network';
    case ParseError = 'parse_error';
    case Paused = 'paused';

    /** Pesan aman untuk user. */
    public function message(): string
    {
        return match ($this) {
            self::Ok => 'Berhasil diperbarui.',
            self::NotFound => 'Utas tidak ditemukan atau sudah dihapus.',
            self::LoginRequired => 'Profil atau utas tidak bisa dibaca publik. Pastikan username benar dan akun tidak privat.',
            self::Paused => 'Pengambilan data sedang dijeda. Coba lagi beberapa menit lagi.',
            default => 'Data Threads belum bisa diambil. Coba lagi nanti.',
        };
    }
}
