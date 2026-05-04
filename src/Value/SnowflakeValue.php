<?php

declare(strict_types=1);

namespace Infocyph\UID\Value;

use DateTimeImmutable;
use Infocyph\UID\Snowflake;

/**
 * @extends AbstractParsedIdValue<array{time: DateTimeImmutable, sequence: int, worker_id: int, datacenter_id: int}>
 */
final readonly class SnowflakeValue extends AbstractParsedIdValue
{
    public function getDatacenterId(): int
    {
        return $this->parsed['datacenter_id'];
    }

    public function getMachineId(): string
    {
        return $this->parsed['datacenter_id'] . ':' . $this->parsed['worker_id'];
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->parsed['time'];
    }

    public function getWorkerId(): int
    {
        return $this->parsed['worker_id'];
    }

    protected function invalidMessage(): string
    {
        return 'Invalid Snowflake ID string';
    }

    protected function parser(): callable
    {
        return Snowflake::parse(...);
    }

    protected function validator(): callable
    {
        return Snowflake::isValid(...);
    }
}
