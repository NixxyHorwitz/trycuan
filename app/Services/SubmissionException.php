<?php
declare(strict_types=1);

namespace App\Services;

final class SubmissionException extends \RuntimeException
{
    /** @param string $field kolom form yang salah */
    public function __construct(string $message, public readonly string $field = 'url')
    {
        parent::__construct($message);
    }
}
