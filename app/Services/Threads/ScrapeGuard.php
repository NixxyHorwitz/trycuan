<?php
declare(strict_types=1);

namespace App\Services\Threads;

use App\Core\Database;
use App\Core\RateLimiter;

/** Batas laju global, circuit breaker, dan log scraping. */
final class ScrapeGuard
{
    public const THRESHOLD = 8;
    public const PAUSE = 600;
    private const PER_MINUTE = 40;
    private const LOG_DAYS = 30;

    public function check(): void
    {
        $open = (int) Database::pdo()->query('SELECT open_until FROM scrape_state WHERE id = 1')->fetchColumn();
        if ($open > time()) throw new ThreadsException(Outcome::Paused, 'Circuit breaker aktif');
        if (!RateLimiter::hit('scrape-global', self::PER_MINUTE, 60)) throw new ThreadsException(Outcome::Paused, 'Batas laju global');
    }

    public function record(string $target, Outcome $outcome, int $durationMs, ?int $httpCode, string $detail = ''): void
    {
        $db = Database::pdo();
        $db->prepare('INSERT INTO scrape_logs (target, http_code, outcome, duration_ms, detail) VALUES (?, ?, ?, ?, ?)')
            ->execute([$target, $httpCode, $outcome->value, $durationMs, mb_substr($detail, 0, 190) ?: null]);
        if (random_int(1, 200) === 1) {
            $db->exec('DELETE FROM scrape_logs WHERE created_at < NOW() - INTERVAL ' . self::LOG_DAYS . ' DAY');
        }

        $now = time();
        if ($outcome === Outcome::Ok) {
            $db->exec('UPDATE scrape_state SET failures = 0 WHERE id = 1');
        } elseif ($outcome === Outcome::Blocked) {
            $db->prepare('UPDATE scrape_state SET failures = ?, open_until = ? WHERE id = 1')->execute([self::THRESHOLD - 1, $now + self::PAUSE]);
        } elseif (in_array($outcome, [Outcome::Network, Outcome::ParseError], true)) {
            // failures ditahan di ambang - 1 setelah jeda: satu kegagalan lagi langsung menjeda ulang.
            $db->prepare('UPDATE scrape_state SET open_until = IF(failures + 1 >= ?, ?, open_until), failures = LEAST(failures + 1, ?) WHERE id = 1')
                ->execute([self::THRESHOLD, $now + self::PAUSE, self::THRESHOLD - 1]);
        }
        // LoginRequired dan NotFound tidak dihitung: salah ketik user memberi hasil yang sama.
    }

    /** Ringkasan 24 jam untuk admin. */
    public static function health(): array
    {
        $db = Database::pdo();
        $row = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(outcome = 'ok'), 0) AS ok, COALESCE(AVG(duration_ms), 0) AS avg FROM scrape_logs WHERE created_at > NOW() - INTERVAL 1 DAY")->fetch();
        $state = $db->query('SELECT failures, open_until FROM scrape_state WHERE id = 1')->fetch();
        return ['paused' => $state['open_until'] > time()] + $state + $row;
    }

    public static function recent(): array
    {
        return Database::pdo()->query('SELECT * FROM scrape_logs ORDER BY id DESC LIMIT 50')->fetchAll();
    }

    public static function resume(): void
    {
        Database::pdo()->exec('UPDATE scrape_state SET failures = 0, open_until = 0 WHERE id = 1');
    }
}
