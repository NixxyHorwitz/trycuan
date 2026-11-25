<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ThreadsAccount;
use App\Services\Threads\ThreadsSource;

final class AccountVerifier
{
    public const TTL = 1800;
    // Tanpa 0/O dan 1/I/L supaya tidak tertukar.
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function __construct(private readonly ThreadsSource $source)
    {
    }

    /** Membuat kode, atau memakai kode yang masih berlaku untuk username yang sama. */
    public function start(int $userId, string $username): void
    {
        if (ThreadsAccount::forUser($userId)) throw new VerificationException('Akun Threads kamu sudah terverifikasi.');
        $pending = ThreadsAccount::pending($userId);
        if ($pending && $pending['username'] === $username && (int) $pending['expires_at'] > time()) return;

        $code = 'TC-';
        for ($i = 0; $i < 8; $i++) $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        Database::pdo()->prepare('REPLACE INTO threads_verifications (user_id, username, code, expires_at) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $username, $code, time() + self::TTL]);
    }

    public function cancel(int $userId): void
    {
        Database::pdo()->prepare('DELETE FROM threads_verifications WHERE user_id = ?')->execute([$userId]);
    }

    /** @throws VerificationException|\App\Services\Threads\ThreadsException */
    public function verify(int $userId): void
    {
        $pending = ThreadsAccount::pending($userId) ?? throw new VerificationException('Buat kode verifikasi dulu.');
        if ((int) $pending['expires_at'] <= time()) throw new VerificationException('Kode sudah kedaluwarsa. Buat kode baru.');

        $profile = $this->source->profile($pending['username']);
        if ($profile->isPrivate) throw new VerificationException('Akun masih privat. Ubah menjadi publik supaya utas bisa dihitung.');
        if (!self::contains($profile->biography, $pending['code'])) {
            throw new VerificationException('Kode belum ditemukan di bio @' . $pending['username'] . '. Simpan bio, tunggu sekitar satu menit, lalu coba lagi.');
        }
        if (($owner = ThreadsAccount::forPk($profile->pk)) && (int) $owner['user_id'] !== $userId) {
            throw new VerificationException('Akun Threads ini sudah terhubung ke user lain.');
        }

        $db = Database::pdo();
        $db->beginTransaction();
        try {
            // Kode bisa diganti atau dibatalkan dari tab lain selama profil diambil.
            $st = $db->prepare('DELETE FROM threads_verifications WHERE user_id = ? AND code = ?');
            $st->execute([$userId, $pending['code']]);
            if ($st->rowCount() !== 1) throw new VerificationException('Kode sudah berubah. Muat ulang halaman lalu coba lagi.');
            $db->prepare('INSERT INTO threads_accounts (user_id, threads_pk, username) VALUES (?, ?, ?)')
                ->execute([$userId, $profile->pk, $profile->username]);
            $db->prepare("INSERT INTO audit_logs (actor_id, action, target) VALUES (?, 'threads.verified', ?)")
                ->execute([$userId, 'pk:' . $profile->pk]);
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            if ($error instanceof \PDOException && ($error->errorInfo[1] ?? 0) === 1062) {
                throw new VerificationException('Akun Threads ini sudah terhubung.');
            }
            throw $error;
        }
    }

    /** Kode harus utuh, bukan bagian dari kata yang lebih panjang. */
    public static function contains(string $biography, string $code): bool
    {
        return preg_match('/(?<![A-Za-z0-9-])' . preg_quote($code, '/') . '(?![A-Za-z0-9-])/', $biography) === 1;
    }
}
