<?php
declare(strict_types=1);

namespace App\Services\Clicks;

use App\Core\ClientIp;
use App\Models\Device;

/** Data mentah satu klik, dipisah dari superglobal supaya bisa dites. */
final readonly class ClickRequest
{
    public function __construct(
        public string $ip,
        public ?string $cookie,
        public string $userAgent,
        public string $acceptLanguage,
        public string $fetchSite,
        public string $purpose,
        public ?string $country,
        public string $token,
        public string $challenge,
        public array $signals,
    ) {
    }

    public static function fromHttp(array $body): self
    {
        $header = static fn (string $key): string => is_string($_SERVER["HTTP_$key"] ?? null) ? $_SERVER["HTTP_$key"] : '';
        $country = ClientIp::behindCloudflare() ? strtoupper($header('CF_IPCOUNTRY')) : '';
        return new self(
            ClientIp::current(),
            Device::cookie(),
            substr($header('USER_AGENT'), 0, 500),
            substr($header('ACCEPT_LANGUAGE'), 0, 200),
            $header('SEC_FETCH_SITE'),
            $header('SEC_PURPOSE') . $header('PURPOSE'),
            preg_match('/^[A-Z][A-Z0-9]$/D', $country) ? $country : null,
            is_string($body['token'] ?? null) ? $body['token'] : '',
            is_string($body['challenge'] ?? null) ? $body['challenge'] : '',
            is_array($body['signals'] ?? null) ? $body['signals'] : [],
        );
    }

    public function text(string $key): string
    {
        $value = $this->signals[$key] ?? '';
        return is_string($value) && mb_check_encoding($value, 'UTF-8') ? mb_substr($value, 0, 100) : '';
    }

    public function number(string $key): int
    {
        $value = $this->signals[$key] ?? 0;
        return is_int($value) || is_float($value) ? (int) max(0, min(100000, $value)) : 0;
    }

    public function isMobile(): bool
    {
        return preg_match('/Android|iPhone|iPod/i', $this->userAgent) === 1;
    }

    public function os(): string
    {
        foreach (['iPhone|iPad|iPod' => 'ios', 'Android' => 'android', 'Windows' => 'windows', 'CrOS' => 'chromeos', 'Mac OS X|Macintosh' => 'mac', 'Linux' => 'linux'] as $pattern => $os) {
            if (preg_match("/$pattern/i", $this->userAgent)) return $os;
        }
        return 'other';
    }

    /** Ciri kasar: tidak unik, hanya dipakai sebagai sinyal. */
    public function traits(): string
    {
        preg_match('~(Edg|OPR|SamsungBrowser|Firefox|CriOS|Chrome|Version)/(\d+)~', $this->userAgent, $m);
        return implode('|', [
            $this->os(), $m[0] ?? '', $this->number('w'), $this->number('h'), $this->text('tz'), $this->text('lang'),
            $this->number('cores'), $this->number('touch'), $this->text('platform'),
        ]);
    }
}
