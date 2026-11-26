<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    /** Gaya avatar DiceBear, semuanya CC0 1.0. */
    public const AVATARS = ['thumbs' => 'Blob', 'blobs' => 'Gelombang', 'lorelei-neutral' => 'Wajah', 'notionists-neutral' => 'Sketsa', 'pixel-art-neutral' => 'Piksel', 'open-peeps' => 'Orang', 'shapes' => 'Bentuk'];

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
        Database::pdo()->prepare('INSERT INTO users (name, email, password_hash, avatar_seed) VALUES (?, ?, ?, ?)')
            ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), bin2hex(random_bytes(8))]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Password acak yang tidak diketahui siapa pun; has_password 0 sampai user membuatnya sendiri. */
    public static function createGoogle(string $name, string $email, string $sub): int
    {
        Database::pdo()->prepare('INSERT INTO users (name, email, password_hash, has_password, google_sub, avatar_seed) VALUES (?, ?, ?, 0, ?, ?)')
            ->execute([$name, $email, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), $sub, bin2hex(random_bytes(8))]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function updateAvatar(int $id, string $style, string $seed): void
    {
        Database::pdo()->prepare('UPDATE users SET avatar_style = ?, avatar_seed = ? WHERE id = ?')->execute([$style, $seed, $id]);
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
        Database::pdo()->prepare('UPDATE users SET password_hash = ?, has_password = 1, session_version = session_version + 1 WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function counts(): array
    {
        return Database::pdo()->query("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'active'), 0) AS active,
            COALESCE(SUM(status = 'blocked'), 0) AS blocked FROM users WHERE role = 'freelancer'")->fetch();
    }

    public const PAGE = 25;

    /**
     * Freelancer menurut kata kunci dan status.
     *
     * @return array{0: array, 1: int} baris dan jumlah seluruhnya
     */
    public static function search(string $query, string $status = '', string $sort = '', int $page = 1, int $size = self::PAGE): array
    {
        $like = '%' . addcslashes($query, '%_\\') . '%';
        $order = ['saldo' => 'balance DESC, u.id', 'views' => 'views DESC, u.id', 'lama' => 'u.id'][$sort] ?? 'u.id DESC';
        $where = "FROM users u LEFT JOIN threads_accounts a ON a.user_id = u.id
            WHERE u.role = 'freelancer' AND (u.name LIKE ? OR u.email LIKE ? OR a.username LIKE ?) AND (? = '' OR u.status = ?)";
        $args = [$like, $like, $like, $status, $status];
        $st = Database::pdo()->prepare("SELECT COUNT(*) $where");
        $st->execute($args);
        $total = (int) $st->fetchColumn();
        $offset = max(0, $page - 1) * $size;
        $st = Database::pdo()->prepare("SELECT u.id, u.name, u.email, u.status, u.created_at, u.last_login_at, a.username,
            (SELECT COALESCE(SUM(l.amount), 0) FROM ledger l WHERE l.user_id = u.id) AS balance,
            (SELECT COALESCE(SUM(p.total_views), 0) FROM participations p WHERE p.account_id = a.id) AS views
            $where ORDER BY $order LIMIT $size OFFSET $offset");
        $st->execute($args);
        return [$st->fetchAll(), $total];
    }
}
