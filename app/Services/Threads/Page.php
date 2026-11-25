<?php
declare(strict_types=1);

namespace App\Services\Threads;

final readonly class Page
{
    public function __construct(public int $status, public string $url, public string $body)
    {
    }
}
