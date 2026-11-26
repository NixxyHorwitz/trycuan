<?php
declare(strict_types=1);

namespace App\Services\Clicks;

use App\Models\Setting;

final class Turnstile implements Challenge
{
    /** Kunci uji Cloudflare selalu lolos, jadi hanya berlaku di server lokal. */
    public static function siteKey(): string
    {
        return self::usable() ? Setting::orEnv('turnstile_site', 'TURNSTILE_SITE') : '';
    }

    public function verify(string $response, string $ip): string
    {
        if (!self::usable()) return 'unavailable';
        if ($response === '' || strlen($response) > 2048) return 'missing';
        $curl = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        $fields = ['secret' => Setting::orEnv('turnstile_secret', 'TURNSTILE_SECRET'), 'response' => $response];
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) $fields['remoteip'] = $ip;
        curl_setopt_array($curl, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 3, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($curl);
        $data = is_string($body) ? json_decode($body, true) : null;
        if (!is_array($data) || !is_bool($data['success'] ?? null)) return 'unavailable';
        if ($data['success']) return 'pass';
        $codes = (array) ($data['error-codes'] ?? []);
        return array_intersect($codes, ['internal-error', 'missing-input-secret', 'invalid-input-secret']) ? 'unavailable' : 'fail';
    }

    private static function usable(): bool
    {
        $secret = Setting::orEnv('turnstile_secret', 'TURNSTILE_SECRET');
        if ($secret === '' || Setting::orEnv('turnstile_site', 'TURNSTILE_SITE') === '') return false;
        $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
        return !preg_match('/^[123]x0000/', $secret) || $host === 'localhost' || str_ends_with($host, '.test');
    }
}
