<?php
declare(strict_types=1);

namespace App\Core;

/** IP pengunjung dan bentuk HMAC-nya. IP mentah tidak pernah disimpan. */
final class ClientIp
{
    public static function current(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        // Hanya aman bila server asal menolak koneksi selain dari Cloudflare.
        if (self::behindCloudflare() && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '', FILTER_VALIDATE_IP)) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function behindCloudflare(): bool
    {
        return Env::get('TRUST_CLOUDFLARE') === '1';
    }

    /** IPv6 rumahan berganti di dalam /64, jadi /64 dianggap satu alamat. */
    public static function hash(string $ip): string
    {
        return self::hmac('ip', str_contains($ip, ':') ? self::prefix($ip, 64) : $ip);
    }

    public static function subnetHash(string $ip): string
    {
        return self::hmac('net', self::prefix($ip, str_contains($ip, ':') ? 48 : 24));
    }

    public static function inCidr(string $ip, string $cidr): bool
    {
        [$net, $bits] = explode('/', $cidr, 2) + [1 => ''];
        $a = @inet_pton($ip);
        $b = @inet_pton($net);
        if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
        $bits = $bits === '' ? strlen($a) * 8 : (int) $bits;
        if ($bits < 1 || $bits > strlen($a) * 8) return false;
        return self::mask($a, $bits) === self::mask($b, $bits);
    }

    private static function prefix(string $ip, int $bits): string
    {
        return bin2hex(self::mask((string) inet_pton($ip), $bits));
    }

    private static function mask(string $packed, int $bits): string
    {
        $out = '';
        foreach (str_split($packed) as $i => $char) {
            $keep = max(0, min(8, $bits - $i * 8));
            $out .= chr(ord($char) & (0xFF << (8 - $keep) & 0xFF));
        }
        return $out;
    }

    private static function hmac(string $type, string $value): string
    {
        return hash_hmac('sha256', "$type:$value", Env::get('APP_KEY'));
    }
}
