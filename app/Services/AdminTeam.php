<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\PasswordPolicy;
use App\Core\Validate;
use App\Models\Audit;
use App\Models\User;

/** Akun admin selalu dibuat terpisah; akun freelancer tidak bisa dinaikkan menjadi admin. */
final class AdminTeam
{
    public static function all(): array
    {
        return Database::pdo()->query("SELECT * FROM users WHERE role = 'admin' ORDER BY id")->fetchAll();
    }

    public static function password(string $password, array $personal = []): ?string
    {
        return PasswordPolicy::error($password, $personal, PasswordPolicy::ADMIN_MIN);
    }

    public static function create(string $name, string $email, string $password, ?int $actorId): int
    {
        $error = Validate::name($name) ?? Validate::email($email) ?? self::password($password, [$name, $email]);
        if ($error) throw new VerificationException($error);
        if (User::findByEmail($email)) throw new VerificationException('Email sudah dipakai akun lain. Pakai email khusus admin.');
        $db = Database::pdo();
        $db->beginTransaction();
        try {
            $id = User::create($name, $email, $password);
            $db->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$id]);
            Audit::add($actorId, 'admin.created', "user:$id", ['email' => $email]);
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
        return $id;
    }

    public static function setPassword(int $id, string $password, ?int $actorId): void
    {
        $row = self::find($id);
        if ($error = self::password($password, [$row['name'], $row['email']])) throw new VerificationException($error);
        User::updatePassword($id, $password);
        Audit::add($actorId, 'admin.password', "user:$id");
    }

    public static function setStatus(int $id, string $status, ?int $actorId): void
    {
        self::find($id);
        // Admin yang bertindak selalu tersisa, jadi panel tidak pernah tanpa admin.
        if ($id === $actorId) throw new VerificationException('Kamu tidak bisa menonaktifkan akun sendiri.');
        Database::pdo()->prepare('UPDATE users SET status = ?, session_version = session_version + 1 WHERE id = ?')->execute([$status, $id]);
        Audit::add($actorId, "admin.$status", "user:$id");
    }

    private static function find(int $id): array
    {
        $row = User::find($id);
        return $row && $row['role'] === 'admin' ? $row : throw new VerificationException('Admin tidak ditemukan.');
    }
}
