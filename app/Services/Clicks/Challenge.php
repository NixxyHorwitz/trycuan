<?php
declare(strict_types=1);

namespace App\Services\Clicks;

interface Challenge
{
    /** @return 'pass'|'fail'|'missing'|'unavailable' */
    public function verify(string $response, string $ip): string;
}
