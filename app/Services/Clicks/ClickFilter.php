<?php
declare(strict_types=1);

namespace App\Services\Clicks;

use App\Core\ClientIp;
use App\Core\Database;

/** Lapis pemeriksaan klik. Lapis token dan duplikat ditangani ClickTracker karena mengubah database. */
final class ClickFilter
{
    private const BOTS = '~bot\b|bot/|crawl|spider|slurp|preview|facebookexternalhit|meta-external|whatsapp|telegram|discord|curl|wget|python|java/|go-http|okhttp|axios|node-fetch|headless|phantom|puppeteer|playwright|selenium|lighthouse~i';
    public const BURST_PER_MINUTE = 10;
    public const IP_PER_DAY = 20;
    public const SUBNET_PER_DAY = 60;
    public const CLUSTER_LINKS = 3;

    public function __construct(private readonly string $hostingList = ROOT . '/storage/ip/hosting.txt')
    {
    }

    /** Lapis 1 */
    public function request(ClickRequest $r, Verdict $v): void
    {
        if ($r->userAgent === '' || preg_match(self::BOTS, $r->userAgent)) $v->invalid('bot');
        if (preg_match('/prefetch|preview|prerender/i', $r->purpose)) $v->invalid('prefetch');
        if ($r->fetchSite !== '' && $r->fetchSite !== 'same-origin') $v->invalid('lintas_situs');
        if ($r->acceptLanguage === '') $v->risk(30, 'tanpa_bahasa');
    }

    /** Lapis 3 */
    public function challenge(string $result, Verdict $v): void
    {
        if ($result === 'fail') $v->invalid('tantangan_gagal');
        elseif ($result === 'missing') $v->hold('tantangan_kosong');
        elseif ($result !== 'pass') $v->hold('tantangan_mati');
    }

    /** Lapis 4 */
    public function browser(ClickRequest $r, Verdict $v): void
    {
        if (($r->signals['webdriver'] ?? false) === true) $v->invalid('webdriver');
        $strong = [];
        if (!$r->signals) $strong[] = 'tanpa_sinyal';
        if ($r->isMobile() && $r->number('touch') === 0) $strong[] = 'hp_tanpa_sentuh';
        if ($r->number('w') === 0 || $r->number('h') === 0) $strong[] = 'layar_kosong';
        if ($r->text('lang') === '') $strong[] = 'bahasa_kosong';
        $platform = $r->text('platform');
        $expected = ['ios' => 'iPhone|iPad|iPod|MacIntel', 'android' => 'Linux|Android|arm', 'windows' => 'Win', 'mac' => 'Mac', 'linux' => 'Linux', 'chromeos' => 'Linux|CrOS'][$r->os()] ?? '';
        if ($platform !== '' && $expected !== '' && !preg_match("/$expected/i", $platform)) $strong[] = 'os_tidak_cocok';

        foreach ($strong as $reason) count($strong) >= 2 ? $v->invalid($reason) : $v->hold($reason);

        $tz = $r->text('tz');
        if ($tz === '' || $tz === 'UTC') $v->risk(20, 'zona_waktu_kosong');
        $lang = strtolower(explode('-', $r->text('lang'))[0]);
        if ($lang !== '' && $r->acceptLanguage !== '' && !preg_match('/(^|[,\s])' . preg_quote($lang, '/') . '\b/i', $r->acceptLanguage)) {
            $v->risk(20, 'bahasa_tidak_cocok');
        }
    }

    /** Lapis 6 */
    public function network(ClickRequest $r, array $countries, Verdict $v): void
    {
        if (ClientIp::behindCloudflare()) {
            if ($r->country === 'T1') $v->hold('tor');
            elseif ($r->country === null || $r->country === 'XX') $v->hold('negara_tidak_terbaca');
            elseif ($countries && !in_array($r->country, $countries, true)) $v->hold('negara_di_luar_target');
        }
        if (!is_file($this->hostingList)) return;
        foreach (file($this->hostingList, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $cidr = trim(explode('#', $line)[0]);
            if ($cidr !== '' && ClientIp::inCidr($r->ip, $cidr)) {
                $v->hold('ip_hosting');
                return;
            }
        }
    }

    /** Lapis 7 */
    public function owner(int $ownerId, int $deviceId, string $ipHash, Verdict $v): void
    {
        $st = Database::pdo()->prepare('SELECT user_id FROM user_devices WHERE device_id = ?');
        $st->execute([$deviceId]);
        $users = array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
        if (in_array($ownerId, $users, true)) $v->invalid('perangkat_pemilik');
        elseif ($users) $v->hold('perangkat_freelancer_lain');
        $st = Database::pdo()->prepare('SELECT 1 FROM user_devices WHERE user_id = ? AND ip_hash = ? AND last_login_at > NOW() - INTERVAL 7 DAY LIMIT 1');
        $st->execute([$ownerId, $ipHash]);
        if ($st->fetchColumn()) $v->risk(30, 'ip_pemilik');
    }

    /** Lapis 5 (sinyal) dan 8. Dipanggil selagi baris link terkunci. */
    public function pattern(int $linkId, int $campaignId, int $deviceId, string $ipHash, string $subnetHash, string $traitHash, Verdict $v): void
    {
        $db = Database::pdo();
        // Ciri dan IP sama tetapi cookie baru: kemungkinan mode penyamaran, atau dua HP bertipe sama di satu rumah.
        $st = $db->prepare("SELECT 1 FROM clicks WHERE short_link_id = ? AND campaign_id = ? AND trait_hash = ? AND ip_hash = ? AND device_id <> ? AND status <> 'invalid' LIMIT 1");
        $st->execute([$linkId, $campaignId, $traitHash, $ipHash, $deviceId]);
        if ($st->fetchColumn()) $v->hold('ciri_dan_ip_sama');

        $st = $db->prepare("SELECT COALESCE(SUM(created_at > NOW() - INTERVAL 1 MINUTE), 0) AS minute, COALESCE(SUM(ip_hash = ?), 0) AS ip, COALESCE(SUM(subnet_hash = ?), 0) AS subnet
            FROM clicks WHERE short_link_id = ? AND status <> 'invalid' AND created_at > NOW() - INTERVAL 1 DAY");
        $st->execute([$ipHash, $subnetHash, $linkId]);
        $row = $st->fetch();
        if ($row['minute'] >= self::BURST_PER_MINUTE) $v->hold('lonjakan');
        if ($row['ip'] >= self::IP_PER_DAY) $v->hold('volume_ip');
        if ($row['subnet'] >= self::SUBNET_PER_DAY) $v->hold('volume_subnet');

        $st = $db->prepare("SELECT UNIX_TIMESTAMP(created_at) AS t, trait_hash FROM clicks WHERE short_link_id = ? AND status <> 'invalid' ORDER BY id DESC LIMIT 30");
        $st->execute([$linkId]);
        $rows = $st->fetchAll();
        if (self::tooRegular([microtime(true), ...array_map(static fn (array $x): float => (float) $x['t'], array_slice($rows, 0, 7))])) $v->hold('jarak_teratur');
        $same = count(array_filter($rows, static fn (array $x): bool => $x['trait_hash'] === $traitHash));
        if (count($rows) >= 20 && $same / count($rows) > 0.6) $v->hold('perangkat_seragam');
    }

    /** Lapis 9 */
    public function cluster(int $linkId, int $deviceId, Verdict $v): bool
    {
        $st = Database::pdo()->prepare("SELECT COUNT(DISTINCT short_link_id) FROM clicks WHERE device_id = ? AND short_link_id <> ? AND status <> 'invalid' AND created_at > NOW() - INTERVAL 7 DAY");
        $st->execute([$deviceId, $linkId]);
        if ((int) $st->fetchColumn() + 1 < self::CLUSTER_LINKS) return false;
        $v->hold('lintas_akun');
        return true;
    }

    /** @param float[] $times terbaru lebih dulu */
    public static function tooRegular(array $times): bool
    {
        if (count($times) < 8) return false;
        $gaps = [];
        for ($i = 1; $i < count($times); $i++) $gaps[] = $times[$i - 1] - $times[$i];
        $mean = array_sum($gaps) / count($gaps);
        if ($mean < 1 || $mean > 3600) return false;
        $variance = array_sum(array_map(static fn (float $gap): float => ($gap - $mean) ** 2, $gaps)) / count($gaps);
        return sqrt($variance) / $mean < 0.1;
    }
}
