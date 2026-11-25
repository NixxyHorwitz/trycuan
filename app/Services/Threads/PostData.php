<?php
declare(strict_types=1);

namespace App\Services\Threads;

final readonly class PostData
{
    /** $views null berarti Threads tidak menampilkan angkanya. Jangan dianggap 0. */
    public function __construct(
        public string $code,
        public string $ownerPk,
        public string $ownerUsername,
        public int $postedAt,
        public string $text,
        public ?int $views,
        public ?int $likes,
        public bool $isReply,
        public string $topic = '',
    ) {
    }
}
