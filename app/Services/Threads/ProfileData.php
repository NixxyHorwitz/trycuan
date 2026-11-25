<?php
declare(strict_types=1);

namespace App\Services\Threads;

final readonly class ProfileData
{
    /** @param list<string> $links */
    public function __construct(
        public string $pk,
        public string $username,
        public string $biography,
        public array $links,
        public bool $isPrivate,
    ) {
    }
}
