<?php
declare(strict_types=1);

namespace App\Services\Threads;

/** Sumber data Threads. Scraper bisa diganti API resmi tanpa mengubah pemakainya. */
interface ThreadsSource
{
    /** @throws ThreadsException */
    public function post(string $code): PostData;

    /** @throws ThreadsException */
    public function profile(string $username): ProfileData;
}
