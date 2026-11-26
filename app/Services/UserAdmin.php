<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Audit;
use App\Models\ThreadsAccount;
use App\Models\User;
use App\Services\Threads\ThreadsSource;

/** Tindakan admin terhadap user. */
final class UserAdmin
{
    public static function setStatus(int $userId, string $status, int $adminId, string $reason): void
    {
        $user = User::find($userId) ?? throw new VerificationException('User tidak ditemukan.');
        if ($user['role'] === 'admin') throw new VerificationException('Admin tidak bisa diblokir dari sini.');
        self::transaction(static function () use ($userId, $status, $adminId, $reason): void {
            Database::pdo()->prepare('UPDATE users SET status = ?, session_version = session_version + 1 WHERE id = ?')->execute([$status, $userId]);
            Audit::add($adminId, "user.$status", "user:$userId", ['reason' => $reason]);
        });
    }

    /** Username berganti: profil baru harus punya ID Threads yang sama. */
    public static function rename(int $userId, string $username, int $adminId, ThreadsSource $source): void
    {
        $account = ThreadsAccount::forUser($userId) ?? throw new VerificationException('User belum punya akun Threads.');
        $profile = $source->profile($username);
        if ($profile->pk !== $account['threads_pk']) throw new VerificationException("@$username adalah akun Threads yang berbeda.");
        self::transaction(static function () use ($account, $profile, $adminId, $userId): void {
            Database::pdo()->prepare('UPDATE threads_accounts SET username = ? WHERE id = ?')->execute([$profile->username, $account['id']]);
            Audit::add($adminId, 'threads.renamed', "user:$userId", ['before' => $account['username'], 'after' => $profile->username]);
        });
    }

    /** Hanya selama belum pernah ikut campaign, supaya riwayat fee tidak terputus. */
    public static function detach(int $userId, int $adminId, string $reason): void
    {
        $account = ThreadsAccount::forUser($userId) ?? throw new VerificationException('User belum punya akun Threads.');
        self::transaction(static function () use ($account, $userId, $adminId, $reason): void {
            $db = Database::pdo();
            $db->prepare('SELECT id FROM threads_accounts WHERE id = ? FOR UPDATE')->execute([$account['id']]);
            $st = $db->prepare('SELECT 1 FROM participations WHERE account_id = ? LIMIT 1');
            $st->execute([$account['id']]);
            if ($st->fetchColumn()) throw new VerificationException('Akun ini sudah ikut campaign, jadi tidak bisa dilepas.');
            $st = $db->prepare('SELECT 1 FROM clicks k JOIN short_links l ON l.id = k.short_link_id WHERE l.account_id = ? LIMIT 1');
            $st->execute([$account['id']]);
            if ($st->fetchColumn()) throw new VerificationException('Link akun ini sudah punya riwayat klik, jadi tidak bisa dilepas.');
            $db->prepare('DELETE FROM short_links WHERE account_id = ?')->execute([$account['id']]);
            $db->prepare('DELETE FROM threads_accounts WHERE id = ?')->execute([$account['id']]);
            Audit::add($adminId, 'threads.detached', "user:$userId", ['username' => $account['username'], 'pk' => $account['threads_pk'], 'reason' => $reason]);
        });
    }

    private static function transaction(callable $run): void
    {
        $db = Database::pdo();
        $db->beginTransaction();
        try {
            $run();
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
