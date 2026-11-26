<?php
declare(strict_types=1);

namespace App\Services\Threads;

/** Mengambil kode utas dari link yang ditempel, termasuk link bagikan dari menu titik tiga. */
final class PostLink
{
    public const HELP = 'Tempel link utas dari Threads, misalnya https://www.threads.com/@nama/post/ABC123 atau https://www.threads.com/share/ABC123';
    /** Atribut kolom link; memeriksa format sebelum dikirim. */
    public const FIELD = 'inputmode="url" maxlength="500" data-pattern="threads\.(com|net)/(share/|@[^/]+/post/)" data-pattern-text="Tempel link utas dari Threads, misalnya https://www.threads.com/share/..." placeholder="https://www.threads.com/share/..."';

    /** @param ?\Closure $fetch menerima path, mengembalikan [status HTTP, tujuan redirect] */
    public function __construct(private readonly ?\Closure $fetch = null, private readonly ScrapeGuard $guard = new ScrapeGuard())
    {
    }

    /** Link pertama di teks yang ditempel; aplikasi kadang ikut menyalin kalimat. */
    public static function clean(string $text): string
    {
        $text = trim($text);
        if (preg_match('~https?://\S+~i', $text, $m)) return $m[0];
        return preg_match('~^(?:www\.)?threads\.(?:com|net)/~i', $text) ? "https://$text" : $text;
    }

    public static function shareId(string $text): ?string
    {
        return preg_match('~^https://(?:www\.)?threads\.(?:com|net)/share/([A-Za-z0-9_-]{5,60})/?(?:[?#]\S*)?$~D', self::clean($text), $m) ? $m[1] : null;
    }

    /** Pesan untuk user bila link atau utasnya tidak terbaca. */
    public static function failure(ThreadsException $error): string
    {
        return $error->getMessage() === 'Link bagikan tidak terurai' && $error->outcome !== Outcome::Paused
            ? 'Link bagikan belum bisa dibaca. Coba lagi, atau buka link itu di browser lalu salin alamatnya.' : $error->outcome->message();
    }

    /** Null bila bukan link utas. @throws ThreadsException */
    public function code(string $text): ?string
    {
        $code = Identifier::codeFromUrl(self::clean($text));
        if ($code !== null) return $code;
        $share = self::shareId($text);
        return $share === null ? null : $this->resolve($share);
    }

    private function resolve(string $share): string
    {
        $this->guard->check();
        $start = hrtime(true);
        $status = null;
        $code = null;
        $outcome = Outcome::Ok;
        try {
            [$status, $next] = $this->fetch ? ($this->fetch)("/share/$share") : self::request("/share/$share");
            if ($status >= 300 && $status < 400) $code = Identifier::codeFromUrl($next);
            if ($code === null) $outcome = in_array($status, [401, 403, 429], true) ? Outcome::Blocked : ($status >= 500 ? Outcome::Network : Outcome::NotFound);
        } catch (ThreadsException $error) {
            $outcome = $error->outcome;
        }
        $this->guard->record("share:$share", $outcome, intdiv(hrtime(true) - $start, 1_000_000), $status);
        return $code ?? throw new ThreadsException($outcome, 'Link bagikan tidak terurai', $status);
    }

    /** @return array{int, string} redirect dibaca tetapi tidak diikuti */
    private static function request(string $path): array
    {
        $curl = curl_init("https://www.threads.com$path");
        curl_setopt_array($curl, [
            CURLOPT_PROTOCOLS_STR => 'https',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'TryCuan/1.0',
            // Hanya tujuan redirect yang dibutuhkan.
            CURLOPT_WRITEFUNCTION => static fn ($c, string $chunk): int => strlen($chunk),
        ]);
        if (curl_exec($curl) === false) throw new ThreadsException(Outcome::Network, substr(curl_error($curl), 0, 150));
        return [curl_getinfo($curl, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($curl, CURLINFO_REDIRECT_URL)];
    }
}
