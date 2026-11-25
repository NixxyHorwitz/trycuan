<?php
declare(strict_types=1);

namespace App\Services\Threads;

final class CurlFetcher implements Fetcher
{
    private const HOST = 'www.threads.com';
    private const MAX_BYTES = 6_000_000;
    private const MAX_REDIRECTS = 4;
    private const DEADLINE = 20;

    public function get(string $path): Page
    {
        $url = 'https://' . self::HOST . $path;
        $until = microtime(true) + self::DEADLINE;
        // Redirect diikuti manual supaya tiap tujuan diperiksa dulu.
        for ($i = 0; $i <= self::MAX_REDIRECTS; $i++) {
            $left = (int) (($until - microtime(true)) * 1000);
            if ($left < 500) throw new ThreadsException(Outcome::Network, 'Batas waktu habis');
            [$status, $body, $next] = $this->request($url, $left);
            if ($status < 300 || $status >= 400 || $next === '') return new Page($status, $url, $body);
            if (!self::allowed($next)) throw new ThreadsException(Outcome::Blocked, 'Redirect ke host lain', $status);
            $url = $next;
        }
        throw new ThreadsException(Outcome::Network, 'Terlalu banyak redirect');
    }

    public static function allowed(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && in_array(strtolower($parts['host'] ?? ''), [self::HOST, 'threads.com'], true)
            && !isset($parts['port']) && !isset($parts['user']);
    }

    /** @return array{int, string, string} */
    private function request(string $url, int $timeoutMs): array
    {
        $body = '';
        $tooLarge = false;
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_PROTOCOLS_STR => 'https',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => ['Accept: text/html', 'Accept-Language: en-US,en', 'Sec-Fetch-Mode: navigate', 'Sec-Fetch-Site: none'],
            CURLOPT_WRITEFUNCTION => static function ($c, string $chunk) use (&$body, &$tooLarge): int {
                if (strlen($body) + strlen($chunk) > self::MAX_BYTES) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        if ($tooLarge) throw new ThreadsException(Outcome::ParseError, 'Halaman terlalu besar');
        if ($ok === false) throw new ThreadsException(Outcome::Network, substr(curl_error($curl), 0, 150));
        return [curl_getinfo($curl, CURLINFO_RESPONSE_CODE), $body, (string) curl_getinfo($curl, CURLINFO_REDIRECT_URL)];
    }
}
