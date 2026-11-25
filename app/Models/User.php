<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    public static function find(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([$email]);
        return $st->fetch() ?: null;
    }

    public static function create(string $name, string $email, string $password): int
    {
        Database::pdo()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)')
            ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function touchLogin(int $id): void
    {
        Database::pdo()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$id]);
    }

    public static function updateName(int $id, string $name): void
    {
        Database::pdo()->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $id]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        Database::pdo()->prepare('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function counts(): array
    {
        return Database::pdo()->query("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'active'), 0) AS active,
            COALESCE(SUM(status = 'blocked'), 0) AS blocked FROM users WHERE role = 'freelancer'")->fetch();
    }
}
