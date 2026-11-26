<?php
declare(strict_types=1);

namespace App\Core;

/** Enkripsi kolom sensitif dengan kunci turunan APP_KEY. */
final class Crypto
{
    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key('field'), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new \RuntimeException('Enkripsi gagal');
        return $iv . $tag . $cipher;
    }

    public static function decrypt(string $data): string
    {
        $plain = openssl_decrypt(substr($data, 28), 'aes-256-gcm', self::key('field'), OPENSSL_RAW_DATA, substr($data, 0, 12), substr($data, 12, 16));
        return $plain === false ? throw new \RuntimeException('Dekripsi gagal. APP_KEY mungkin berubah.') : $plain;
    }

    public static function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, self::key('fingerprint'));
    }

    private static function key(string $use): string
    {
        $secret = Env::get('APP_KEY');
        if (strlen($secret) < 32) throw new \RuntimeException('APP_KEY belum diisi');
        return hash_hkdf('sha256', $secret, 32, "trycuan-$use");
    }
}
