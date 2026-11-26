<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Dokumen panduan dan materi, beserta berkas unduhan campaign. */
final class CampaignDoc
{
    public const KINDS = ['panduan' => 'Panduan', 'materi' => 'Materi'];

    public static function find(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM campaign_docs WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function forCampaign(int $campaignId, bool $publishedOnly): array
    {
        $st = Database::pdo()->prepare('SELECT * FROM campaign_docs WHERE campaign_id = ?' . ($publishedOnly ? " AND status = 'published'" : '') . ' ORDER BY kind, sort, id');
        $st->execute([$campaignId]);
        return $st->fetchAll();
    }

    public static function save(?int $id, int $campaignId, array $data): int
    {
        $db = Database::pdo();
        if ($id === null) {
            $db->prepare('INSERT INTO campaign_docs (campaign_id, kind, title, body, sort, status) SELECT ?, ?, ?, ?, COALESCE(MAX(sort), 0) + 1, ? FROM campaign_docs WHERE campaign_id = ?')
                ->execute([$campaignId, $data['kind'], $data['title'], $data['body'], $data['status'], $campaignId]);
            return (int) $db->lastInsertId();
        }
        $db->prepare('UPDATE campaign_docs SET kind = ?, title = ?, body = ?, status = ? WHERE id = ?')->execute([$data['kind'], $data['title'], $data['body'], $data['status'], $id]);
        return $id;
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM campaign_docs WHERE id = ?')->execute([$id]);
    }

    /** Menukar urutan dengan dokumen sejenis di atas atau di bawahnya. */
    public static function move(array $doc, int $step): void
    {
        $rows = array_values(array_filter(self::forCampaign((int) $doc['campaign_id'], false), static fn (array $r): bool => $r['kind'] === $doc['kind']));
        $at = array_search((int) $doc['id'], array_map('intval', array_column($rows, 'id')), true);
        if ($at === false || !isset($rows[$at + $step])) return;
        [$rows[$at], $rows[$at + $step]] = [$rows[$at + $step], $rows[$at]];
        $st = Database::pdo()->prepare('UPDATE campaign_docs SET sort = ?, updated_at = updated_at WHERE id = ?');
        foreach ($rows as $i => $row) $st->execute([$i + 1, $row['id']]);
    }

    public static function files(int $campaignId): array
    {
        $st = Database::pdo()->prepare('SELECT * FROM campaign_files WHERE campaign_id = ? ORDER BY id');
        $st->execute([$campaignId]);
        return $st->fetchAll();
    }

    public static function file(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM campaign_files WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function addFile(int $campaignId, string $name, string $file, int $size): int
    {
        Database::pdo()->prepare('INSERT INTO campaign_files (campaign_id, name, file, size) VALUES (?, ?, ?, ?)')->execute([$campaignId, $name, $file, $size]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function deleteFile(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM campaign_files WHERE id = ?')->execute([$id]);
    }
}
