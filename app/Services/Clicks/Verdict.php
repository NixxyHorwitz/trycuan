<?php
declare(strict_types=1);

namespace App\Services\Clicks;

/** Hasil gabungan semua lapis. Temuan terberat menentukan status. */
final class Verdict
{
    private const RISK_LIMIT = 50;
    private bool $invalid = false;
    private bool $held = false;
    public int $risk = 0;
    /** @var string[] */
    public array $reasons = [];

    public function invalid(string $reason): void
    {
        $this->invalid = true;
        $this->reasons[] = $reason;
    }

    public function hold(string $reason): void
    {
        $this->held = true;
        $this->reasons[] = $reason;
    }

    public function risk(int $points, string $reason): void
    {
        $this->risk += $points;
        $this->reasons[] = $reason;
    }

    /** @return 'valid'|'held'|'invalid' */
    public function status(): string
    {
        if ($this->invalid) return 'invalid';
        return $this->held || $this->risk >= self::RISK_LIMIT ? 'held' : 'valid';
    }
}
