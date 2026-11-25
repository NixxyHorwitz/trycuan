<?php
declare(strict_types=1);

namespace App\Services\Threads;

final class Identifier
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

    /** Mengambil kode utas dari URL user. Null bila bukan URL utas Threads. */
    public static function codeFromUrl(string $url): ?string
    {
        $pattern = '~^https://(?:www\.)?threads\.(?:com|net)/@[A-Za-z0-9._]{1,30}/post/([A-Za-z0-9_-]{5,11})/?(?:[?#][^\s]*)?$~D';
        return preg_match($pattern, trim($url), $m) && self::postId($m[1]) !== null ? $m[1] : null;
    }

    /** Merapikan username. Null bila formatnya tidak valid. */
    public static function username(string $value): ?string
    {
        $value = strtolower(ltrim(trim($value), '@'));
        return preg_match('/^[a-z0-9._]{1,30}$/D', $value) ? $value : null;
    }

    /** Kode pendek adalah ID utas dalam basis 64. */
    public static function postId(string $code): ?string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{5,11}$/D', $code)) return null;
        $id = 0;
        foreach (str_split($code) as $char) {
            $digit = strpos(self::ALPHABET, $char);
            if ($id > intdiv(PHP_INT_MAX - $digit, 64)) return null;
            $id = $id * 64 + $digit;
        }
        return (string) $id;
    }
}
