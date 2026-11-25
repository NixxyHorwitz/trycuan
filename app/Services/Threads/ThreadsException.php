<?php
declare(strict_types=1);

namespace App\Services\Threads;

final class ThreadsException extends \RuntimeException
{
    public function __construct(public readonly Outcome $outcome, string $detail = '', public readonly ?int $httpCode = null)
    {
        parent::__construct($detail ?: $outcome->value);
    }
}
