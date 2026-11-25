<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ShortLink;
use App\Services\Threads\ThreadsSource;

/** Mencocokkan shortlink dengan link profil Threads. */
final class LinkVerifier
{
    public function __construct(private readonly ThreadsSource $source)
    {
    }

    /** Gagal mengambil profil melempar ThreadsException dan tidak mengubah status link. */
    public function check(array $account): void
    {
        $link = ShortLink::forAccount((int) $account['id']);
        $profile = $this->source->profile($account['username']);
        if ($profile->pk !== $account['threads_pk']) {
            throw new VerificationException('Username @' . $account['username'] . ' sekarang dipakai akun lain. Hubungi admin.');
        }
        if ($profile->isPrivate) throw new VerificationException('Akun masih privat. Ubah menjadi publik.');
        foreach ($profile->links as $url) {
            if (self::matches($url, $link['code'])) {
                ShortLink::markSeen((int) $link['id']);
                return;
            }
        }
        ShortLink::markLost((int) $link['id']);
        throw new VerificationException('Link ' . ShortLink::url($link) . ' belum ditemukan di kolom link profil @' . $account['username'] . '.');
    }

    /** Host dan kode harus persis sama; huruf besar dan kecil pada kode dibedakan. */
    public static function matches(string $url, string $code): bool
    {
        $parts = parse_url($url);
        if (!$parts || isset($parts['user']) || isset($parts['port']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) return false;
        $host = (string) preg_replace('/^www\./', '', strtolower($parts['host'] ?? ''));
        return $host === ShortLink::host() && rtrim($parts['path'] ?? '', '/') === "/$code";
    }
}
