<?php

declare(strict_types=1);

namespace Infocyph\UID\Exceptions;

final class SequenceTimestampException extends FileLockException
{
    public function __construct(
        public readonly int $lastTimestamp,
        public readonly int $requestedTimestamp,
        string $message = 'Sequence timestamp moved backwards',
    ) {
        parent::__construct($message);
    }
}
