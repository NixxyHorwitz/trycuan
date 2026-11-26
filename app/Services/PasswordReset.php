<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Models\Audit;
use App\Models\User;
use App\Services\Mail\Mailer;

final class PasswordReset
{
    public const TTL = 1800;

    public function __construct(private readonly Mailer $mailer)
    {
    }

    /** Tidak memberi tahu pemanggil apakah email terdaftar. */
    public function request(string $email): void
    {
        $user = User::findByEmail($email);
        // Password admin hanya diganti lewat panel atau bin/admin.php.
        if (!$user || $user['status'] !== 'active' || $user['role'] !== 'freelancer') return;
        $token = bin2hex(random_bytes(32));
        // Permintaan baru mematikan link lama. Yang disimpan hanya hash.
        Database::pdo()->prepare('REPLACE INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
            ->execute([$user['id'], hash('sha256', $token), time() + self::TTL]);
        $link = rtrim(Env::get('APP_URL'), '/') . "/reset-password?token=$token";
        $this->mailer->send($user['email'], 'Atur ulang password ' . setting('site_name'), "Halo {$user['name']},\n\nBuka link ini untuk mengatur ulang password. Link berlaku 30 menit dan hanya bisa dipakai sekali.\n\n$link\n\nAbaikan email ini bila kamu tidak memintanya.\n");
    }

    public static function userFor(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{64}$/D', $token)) return null;
        $st = Database::pdo()->prepare("SELECT u.* FROM password_resets r JOIN users u ON u.id = r.user_id WHERE r.token_hash = ? AND r.expires_at > ? AND u.status = 'active' AND u.role = 'freelancer'");
        $st->execute([hash('sha256', $token), time()]);
        return $st->fetch() ?: null;
    }

    /** False bila token sudah dipakai atau kedaluwarsa. */
    public static function complete(string $token, string $password): bool
    {
        $user = self::userFor($token);
        if (!$user) return false;
        $db = Database::pdo();
        $db->beginTransaction();
        try {
            $st = $db->prepare('DELETE FROM password_resets WHERE token_hash = ? AND expires_at > ?');
            $st->execute([hash('sha256', $token), time()]);
            if ($st->rowCount() !== 1) {
                $db->rollBack();
                return false;
            }
            User::updatePassword((int) $user['id'], $password);
            Audit::add((int) $user['id'], 'password.reset', "user:{$user['id']}");
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
        return true;
    }
}
