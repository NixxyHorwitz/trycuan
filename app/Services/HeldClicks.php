<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Audit;

/** Keputusan admin atas semua klik yang ditahan di satu partisipasi. */
final class HeldClicks
{
    public static function decide(array $part, bool $accept, int $adminId, string $reason): int
    {
        $db = Database::pdo();
        $db->beginTransaction();
        try {
            $st = $db->prepare('SELECT id FROM short_links WHERE account_id = ? FOR UPDATE');
            $st->execute([$part['account_id']]);
            $link = (int) $st->fetchColumn();
            $st = $db->prepare("SELECT id, device_id FROM clicks WHERE short_link_id = ? AND campaign_id = ? AND status = 'held' ORDER BY id FOR UPDATE");
            $st->execute([$link, $part['campaign_id']]);
            $rows = $st->fetchAll();
            $key = $db->prepare('INSERT IGNORE INTO valid_click_keys (short_link_id, campaign_id, device_id) VALUES (?, ?, ?)');
            $has = $db->prepare("SELECT 1 FROM clicks WHERE short_link_id = ? AND campaign_id = ? AND device_id = ? AND status = 'valid' LIMIT 1");
            $update = $db->prepare("UPDATE clicks SET status = ?, reasons_json = JSON_ARRAY_APPEND(reasons_json, '$', ?) WHERE id = ?");
            $valid = 0;
            foreach ($rows as $row) {
                $ok = false;
                if ($accept && $row['device_id'] !== null) {
                    $key->execute([$link, $part['campaign_id'], $row['device_id']]);
                    $has->execute([$link, $part['campaign_id'], $row['device_id']]);
                    // Tetap satu klik valid per perangkat.
                    $ok = !$has->fetchColumn();
                }
                $update->execute([$ok ? 'valid' : 'invalid', $accept ? 'diterima_admin' : 'dibuang_admin', $row['id']]);
                $valid += (int) $ok;
            }
            Audit::add($adminId, $accept ? 'clicks.accepted' : 'clicks.discarded', "participation:{$part['id']}", ['total' => count($rows), 'valid' => $valid, 'reason' => $reason]);
            $db->commit();
            return $valid;
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
