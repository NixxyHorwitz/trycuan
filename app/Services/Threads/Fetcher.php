<?php
declare(strict_types=1);

namespace App\Services\Threads;

interface Fetcher
{
    /** @throws ThreadsException */
    public function get(string $path): Page;
}
