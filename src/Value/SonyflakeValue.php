<?php

declare(strict_types=1);

namespace Infocyph\UID\Value;

use DateTimeImmutable;
use Infocyph\UID\Sonyflake;

/**
 * @extends AbstractParsedIdValue<array{time: DateTimeImmutable, sequence: int, machine_id: int}>
 */
final readonly class SonyflakeValue extends AbstractParsedIdValue
{
    public function getMachineId(): int
    {
        return $this->parsed['machine_id'];
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->parsed['time'];
    }

    protected function invalidMessage(): string
    {
        return 'Invalid Sonyflake ID string';
    }

    protected function parser(): callable
    {
        return Sonyflake::parse(...);
    }

    protected function validator(): callable
    {
        return Sonyflake::isValid(...);
    }
}
