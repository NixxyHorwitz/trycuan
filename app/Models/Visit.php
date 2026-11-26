<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\ClientIp;
use App\Core\Database;
use App\Core\Env;

/** Kunjungan halaman tanpa cookie. Pengenal pengunjung berganti tiap hari dan tidak bisa dikembalikan ke IP. */
final class Visit
{
    public const DAYS = 180;
    private const BOT = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget|python|java\/|headless|lighthouse|monitor|http-client|okhttp|axios|node-fetch|scan/i';
    private const SOURCES = [
        'google.' => 'Google', 'bing.com' => 'Bing', 'duckduckgo.' => 'DuckDuckGo', 'yahoo.' => 'Yahoo', 'yandex.' => 'Yandex', 'threads.' => 'Threads', 'instagram.com' => 'Instagram',
        'facebook.com' => 'Facebook', 'fb.me' => 'Facebook', 'tiktok.com' => 'TikTok', 'twitter.com' => 'X', 'x.com' => 'X', 't.co' => 'X', 'youtube.com' => 'YouTube', 'youtu.be' => 'YouTube',
        'linkedin.com' => 'LinkedIn', 'lnkd.in' => 'LinkedIn', 't.me' => 'Telegram', 'telegram.' => 'Telegram', 'wa.me' => 'WhatsApp', 'whatsapp.com' => 'WhatsApp', 'pinterest.' => 'Pinterest',
        'reddit.com' => 'Reddit', 'discord.' => 'Discord', 'chatgpt.com' => 'ChatGPT', 'claude.ai' => 'Claude', 'perplexity.ai' => 'Perplexity', 'gemini.google.com' => 'Gemini',
    ];

    public static function record(bool $member): void
    {
        $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 400);
        $purpose = ($_SERVER['HTTP_X_SEC_PURPOSE'] ?? '') . ($_SERVER['HTTP_SEC_PURPOSE'] ?? '') . ($_SERVER['HTTP_PURPOSE'] ?? '');
        if ($agent === '' || preg_match(self::BOT, $agent) || str_contains($purpose, 'prefetch')) return;
        $path = '/' . trim(explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0], '/');
        $country = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '');
        $visitor = substr(hash_hmac('sha256', date('Y-m-d') . '|' . ClientIp::hash(ClientIp::current()) . '|' . $agent, Env::get('APP_KEY')), 0, 16);
        $db = Database::pdo();
        $db->prepare('INSERT INTO visits (visitor, path, source, campaign, device, browser, os, country, member) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $visitor, mb_substr(mb_scrub($path), 0, 160), self::source($_SERVER['HTTP_REFERER'] ?? '', query('utm_source'), $_SERVER['HTTP_HOST'] ?? ''), mb_substr(query('utm_campaign'), 0, 60) ?: null,
            self::device($agent), self::browser($agent), self::os($agent),
            ClientIp::behindCloudflare() && preg_match('/^[A-Z]{2}$/D', $country) ? $country : null, (int) $member,
        ]);
        if (random_int(1, 500) === 1) $db->exec('DELETE FROM visits WHERE created_at < NOW() - INTERVAL ' . self::DAYS . ' DAY');
    }

    public static function source(string $referrer, string $utm, string $host): string
    {
        if ($utm !== '') {
            $utm = strtolower($utm);
            foreach (self::SOURCES as $name) if (strtolower($name) === $utm) return $name;
            return mb_substr((string) preg_replace('/[^\w .-]/u', '', $utm), 0, 60) ?: 'Langsung';
        }
        $from = strtolower((string) parse_url($referrer, PHP_URL_HOST));
        if ($from === '') return 'Langsung';
        if ($from === strtolower(explode(':', $host)[0])) return 'internal';
        $best = '';
        $name = '';
        // Pola terpanjang menang, misalnya gemini.google.com di atas google.
        foreach (self::SOURCES as $needle => $label) {
            if (strlen($needle) > strlen($best) && preg_match('/(^|\.)' . preg_quote($needle, '/') . (str_ends_with($needle, '.') ? '' : '$') . '/', $from)) [$best, $name] = [$needle, $label];
        }
        return $name ?: mb_substr((string) preg_replace('/[^a-z0-9.-]/', '', preg_replace('/^www\./', '', $from)), 0, 60);
    }

    public static function device(string $agent): string
    {
        if (preg_match('/ipad|tablet|android(?!.*mobile)/i', $agent)) return 'tablet';
        return preg_match('/mobile|iphone|ipod|android/i', $agent) ? 'mobile' : 'desktop';
    }

    /** @return array<string, mixed> ringkasan untuk halaman trafik */
    public static function report(int $days, bool $guestsOnly): array
    {
        $db = Database::pdo();
        $from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        $before = date('Y-m-d 00:00:00', strtotime('-' . (2 * $days - 1) . ' days'));
        $who = $guestsOnly ? ' AND member = 0' : '';
        $total = static function (string $start, string $end) use ($db, $who): array {
            $st = $db->prepare("SELECT COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors FROM visits WHERE created_at >= ? AND created_at < ?$who");
            $st->execute([$start, $end]);
            return array_map('intval', $st->fetch());
        };
        $now = $total($from, '2999-01-01');
        $st = $db->prepare("SELECT COUNT(*) FROM (SELECT visitor FROM visits WHERE created_at >= ?$who GROUP BY visitor HAVING COUNT(*) = 1) x");
        $st->execute([$from]);
        $single = (int) $st->fetchColumn();

        $labels = $views = $visitors = [];
        $st = $db->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors FROM visits WHERE created_at >= ?$who GROUP BY d");
        $st->execute([$from]);
        $rows = array_column($st->fetchAll(), null, 'd');
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('d/m', strtotime($day));
            $views[] = (int) ($rows[$day]['views'] ?? 0);
            $visitors[] = (int) ($rows[$day]['visitors'] ?? 0);
        }
        $top = static function (string $column, string $extra = '', int $limit = 8) use ($db, $from, $who): array {
            $st = $db->prepare("SELECT $column AS label, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors FROM visits WHERE created_at >= ?$who $extra GROUP BY $column ORDER BY visitors DESC, views DESC LIMIT $limit");
            $st->execute([$from]);
            return $st->fetchAll();
        };
        $hours = array_fill(0, 24, 0);
        $st = $db->prepare("SELECT HOUR(created_at) AS h, COUNT(*) AS n FROM visits WHERE created_at >= ?$who GROUP BY h");
        $st->execute([$from]);
        foreach ($st as $row) $hours[(int) $row['h']] = (int) $row['n'];
        return [
            'now' => $now, 'before' => $total($before, $from), 'bounce' => $now['visitors'] ? round($single / $now['visitors'] * 100) : null,
            'live' => (int) $db->query("SELECT COUNT(DISTINCT visitor) FROM visits WHERE created_at > NOW() - INTERVAL 5 MINUTE$who")->fetchColumn(),
            'labels' => $labels, 'views' => $views, 'visitors' => $visitors, 'hours' => $hours,
            'pages' => $top('path', '', 12), 'sources' => $top('source', "AND source <> 'internal'"), 'campaigns' => $top('campaign', 'AND campaign IS NOT NULL'),
            'devices' => $top('device'), 'browsers' => $top('browser'), 'systems' => $top('os'), 'countries' => $top('country', 'AND country IS NOT NULL'),
        ];
    }

    private static function browser(string $agent): string
    {
        foreach (['/Instagram/' => 'Instagram', '/Barcelona/' => 'Threads', '/FBAN|FBAV/' => 'Facebook', '/TikTok|musical_ly/i' => 'TikTok', '/Edg\//' => 'Edge', '/OPR\//' => 'Opera', '/SamsungBrowser/' => 'Samsung', '/Firefox|FxiOS/' => 'Firefox', '/Chrome|CriOS/' => 'Chrome', '/Safari/' => 'Safari'] as $pattern => $name) {
            if (preg_match($pattern, $agent)) return $name;
        }
        return 'Lainnya';
    }

    private static function os(string $agent): string
    {
        foreach (['/iphone|ipad|ipod/i' => 'iOS', '/android/i' => 'Android', '/windows/i' => 'Windows', '/mac os/i' => 'macOS', '/cros/i' => 'ChromeOS', '/linux/i' => 'Linux'] as $pattern => $name) {
            if (preg_match($pattern, $agent)) return $name;
        }
        return 'Lainnya';
    }
}
