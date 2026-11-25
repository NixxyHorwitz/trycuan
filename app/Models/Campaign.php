<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Campaign
{
    private const FIELDS = ['title', 'brief', 'destination_url', 'rules_json', 'rate_per_1k', 'cap_per_account', 'target_countries', 'budget', 'starts_at', 'ends_at', 'status'];

    public static function find(int $id, bool $lock = false): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM campaigns WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Campaign yang sedang atau akan berjalan, ditambah yang pernah diikuti akun. */
    public static function visible(?int $accountId): array
    {
        $st = Database::pdo()->prepare("SELECT c.*, p.id AS participation_id FROM campaigns c
            LEFT JOIN participations p ON p.campaign_id = c.id AND p.account_id = ?
            WHERE c.status <> 'draft' AND (p.id IS NOT NULL OR (c.status = 'active' AND c.ends_at > NOW()))
            ORDER BY (c.status IN ('active','paused') AND c.ends_at > NOW()) DESC, p.id IS NULL, c.ends_at");
        $st->execute([$accountId ?? 0]);
        return $st->fetchAll();
    }

    public static function all(): array
    {
        return Database::pdo()->query('SELECT c.*,
            (SELECT COUNT(*) FROM participations p WHERE p.campaign_id = c.id) AS accounts,
            (SELECT COUNT(*) FROM submissions s JOIN participations p ON p.id = s.participation_id WHERE p.campaign_id = c.id) AS posts
            FROM campaigns c ORDER BY c.id DESC')->fetchAll();
    }

    public static function hasAccounts(int $id): bool
    {
        $st = Database::pdo()->prepare('SELECT 1 FROM participations WHERE campaign_id = ? LIMIT 1');
        $st->execute([$id]);
        return (bool) $st->fetchColumn();
    }

    public static function create(array $data): int
    {
        $fields = implode(', ', self::FIELDS);
        $marks = implode(', ', array_fill(0, count(self::FIELDS), '?'));
        Database::pdo()->prepare("INSERT INTO campaigns ($fields) VALUES ($marks)")->execute(self::values($data));
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $set = implode(' = ?, ', self::FIELDS) . ' = ?';
        Database::pdo()->prepare("UPDATE campaigns SET $set WHERE id = ?")->execute([...self::values($data), $id]);
    }

    /** @return 'draft'|'upcoming'|'open'|'paused'|'ended' */
    public static function state(array $campaign): string
    {
        $status = $campaign['status'];
        if ($status === 'draft') return 'draft';
        if ($status === 'ended' || strtotime($campaign['ends_at']) <= time()) return 'ended';
        if ($status === 'paused') return 'paused';
        return strtotime($campaign['starts_at']) > time() ? 'upcoming' : 'open';
    }

    public static function label(string $state): string
    {
        return ['draft' => 'Draf', 'upcoming' => 'Segera mulai', 'open' => 'Berjalan', 'paused' => 'Dijeda', 'ended' => 'Selesai'][$state];
    }

    /** @return array{required: string[], banned: string[], min_length: int} */
    public static function rules(array $campaign): array
    {
        return json_decode($campaign['rules_json'], true, 8, JSON_THROW_ON_ERROR) + ['required' => [], 'banned' => [], 'min_length' => 0];
    }

    private static function values(array $data): array
    {
        return array_map(static fn (string $field) => $data[$field], self::FIELDS);
    }
}
