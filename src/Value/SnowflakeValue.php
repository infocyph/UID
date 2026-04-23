<?php

declare(strict_types=1);

namespace Infocyph\UID\Value;

use DateTimeImmutable;
use Infocyph\UID\Contracts\IdValueInterface;
use Infocyph\UID\IdComparator;
use Infocyph\UID\Snowflake;

final readonly class SnowflakeValue implements IdValueInterface
{
    /**
     * @var array{time: DateTimeImmutable, sequence: int, worker_id: int, datacenter_id: int}
     */
    private array $parsed;

    private string $value;

    public function __construct(string $value)
    {
        Snowflake::isValid($value) || throw new \InvalidArgumentException('Invalid Snowflake ID string');
        $this->value = $value;
        $this->parsed = Snowflake::parse($value);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function compare(IdValueInterface|string $other): int
    {
        $otherValue = $other instanceof IdValueInterface ? $other->toString() : $other;

        return IdComparator::compare($this->value, $otherValue);
    }

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

    public function getVersion(): ?int
    {
        return null;
    }

    public function getWorkerId(): int
    {
        return $this->parsed['worker_id'];
    }

    public function isSortable(): bool
    {
        return true;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
