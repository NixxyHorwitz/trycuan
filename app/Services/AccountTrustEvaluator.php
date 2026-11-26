<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Click;
use App\Models\Setting;
use App\Models\Submission;

/** Menilai kewajaran views dan klik satu partisipasi dibanding akun lain di campaign yang sama. */
final class AccountTrustEvaluator
{
    public const LABELS = ['kurang' => 'Data kurang', 'wajar' => 'Wajar', 'review' => 'Perlu review', 'ditolak' => 'Ditolak'];
    public const REASONS = [
        'ctr_rendah' => 'CTR jauh di bawah akun lain',
        'ctr_tinggi' => 'CTR jauh di atas akun lain',
        'banyak_ditahan' => 'Banyak klik ditahan',
        'lonjakan_tanpa_klik' => 'Views melonjak tanpa klik',
        'suka_rendah' => 'Suka sangat sedikit dibanding views',
        'link_dilepas' => 'Link tidak terpasang',
        'rekening_sama' => 'Rekening dipakai user lain',
        'perangkat_sama' => 'Perangkat login dipakai user lain',
        'views_kurang' => 'Views belum cukup',
        'pembanding_kurang' => 'Akun pembanding belum cukup',
    ];
    /** Batas skor z robust dan sebaran terkecil pada skala log. */
    private const Z = 3.5;
    private const FLOOR = 0.35;

    /** @return array{status: string, reasons: string[], ctr: ?float, median: ?float, valid: int, held: int, invalid: int} */
    public static function evaluate(array $part): array
    {
        $db = Database::pdo();
        $views = (int) $part['total_views'];
        $peers = [];
        $own = ['valid' => 0, 'held' => 0, 'invalid' => 0];
        foreach (self::campaign((int) $part['campaign_id']) as $row) {
            if ((int) $row['id'] === (int) $part['id']) $own = ['valid' => (int) $row['valid'], 'held' => (int) $row['held'], 'invalid' => (int) $row['invalid']];
            elseif ($row['total_views'] >= Setting::int('trust_min_views') && $row['trust_status'] !== 'rejected') $peers[] = self::log((int) $row['valid'], (int) $row['total_views']);
        }
        $result = $own + ['ctr' => $views ? $own['valid'] / $views * 100 : null, 'median' => $peers ? exp(self::median($peers)) * 100 : null];
        if ($part['trust_status'] === 'rejected') return $result + ['status' => 'ditolak', 'reasons' => []];

        $flags = [];
        $lack = [];
        if ($views < Setting::int('trust_min_views')) $lack[] = 'views_kurang';
        elseif (count($peers) < Setting::int('trust_min_peers')) $lack[] = 'pembanding_kurang';
        else {
            $z = self::score(self::log($own['valid'], $views), $peers);
            if ($z < -self::Z) $flags[] = 'ctr_rendah';
            if ($z > self::Z) $flags[] = 'ctr_tinggi';
        }
        $counted = $own['valid'] + $own['held'];
        if ($counted >= 10 && $own['held'] / $counted > 0.4) $flags[] = 'banyak_ditahan';

        $st = $db->prepare('SELECT l.*, a.user_id FROM threads_accounts a LEFT JOIN short_links l ON l.account_id = a.id WHERE a.id = ?');
        $st->execute([$part['account_id']]);
        $link = $st->fetch();
        if (!$link['link_verified_at']) $flags[] = 'link_dilepas';

        $days = Submission::viewsByDay((int) $part['id']);
        $clicks = $link['id'] ? array_values(Click::week((int) $link['id'], (int) $part['campaign_id'])) : array_fill(0, 7, 0);
        for ($i = 1; $i < count($days); $i++) {
            if ($days[$i] - $days[$i - 1] >= 20000 && $days[$i] - $days[$i - 1] > 0.8 * max(1, $views) && $clicks[$i] === 0) {
                $flags[] = 'lonjakan_tanpa_klik';
                break;
            }
        }
        $st = $db->prepare("SELECT COALESCE(SUM(views), 0) AS views, COALESCE(SUM(likes), 0) AS likes FROM submissions WHERE participation_id = ? AND status = 'approved' AND missing_at IS NULL AND likes IS NOT NULL");
        $st->execute([$part['id']]);
        $sum = $st->fetch();
        if ($sum['views'] >= 5000 && $sum['likes'] / $sum['views'] < 0.0005) $flags[] = 'suka_rendah';

        $st = $db->prepare('SELECT 1 FROM payout_methods m JOIN payout_methods o ON o.account_hash = m.account_hash AND o.user_id <> m.user_id WHERE m.user_id = ? LIMIT 1');
        $st->execute([$link['user_id']]);
        if ($st->fetchColumn()) $flags[] = 'rekening_sama';
        // Admin sering membantu dari perangkat yang sama, jadi hanya sesama freelancer.
        $st = $db->prepare("SELECT 1 FROM user_devices m JOIN user_devices o ON o.device_id = m.device_id AND o.user_id <> m.user_id JOIN users u ON u.id = o.user_id AND u.role = 'freelancer' WHERE m.user_id = ? LIMIT 1");
        $st->execute([$link['user_id']]);
        if ($st->fetchColumn()) $flags[] = 'perangkat_sama';

        return $result + ['status' => $flags ? 'review' : ($lack ? 'kurang' : 'wajar'), 'reasons' => [...$flags, ...$lack]];
    }

    /** Skor z robust: tidak mudah tergeser oleh satu akun curang. */
    public static function score(float $value, array $peers): float
    {
        $median = self::median($peers);
        $mad = self::median(array_map(static fn (float $x): float => abs($x - $median), $peers));
        return 0.6745 * ($value - $median) / max($mad, self::FLOOR);
    }

    /** Ditambah setengah klik supaya nol tetap bisa dibandingkan. */
    public static function log(int $clicks, int $views): float
    {
        return log(($clicks + 0.5) / max(1, $views));
    }

    public static function median(array $values): float
    {
        sort($values);
        $mid = intdiv(count($values), 2);
        return count($values) % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    private static function campaign(int $id): array
    {
        $st = Database::pdo()->prepare("SELECT p.id, p.total_views, p.trust_status, COALESCE(SUM(k.status = 'valid'), 0) AS valid, COALESCE(SUM(k.status = 'held'), 0) AS held,
            COALESCE(SUM(k.status = 'invalid'), 0) AS invalid FROM participations p LEFT JOIN short_links l ON l.account_id = p.account_id
            LEFT JOIN clicks k ON k.short_link_id = l.id AND k.campaign_id = p.campaign_id WHERE p.campaign_id = ? GROUP BY p.id, p.total_views, p.trust_status");
        $st->execute([$id]);
        return $st->fetchAll();
    }
}
