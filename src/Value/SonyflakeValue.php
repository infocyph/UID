<?php

declare(strict_types=1);

namespace Infocyph\UID\Value;

use DateTimeImmutable;
use Infocyph\UID\Contracts\IdValueInterface;
use Infocyph\UID\IdComparator;
use Infocyph\UID\Sonyflake;

final readonly class SonyflakeValue implements IdValueInterface
{
    /**
     * @var array{time: DateTimeImmutable, sequence: int, machine_id: int}
     */
    private array $parsed;

    private string $value;

    public function __construct(string $value)
    {
        Sonyflake::isValid($value) || throw new \InvalidArgumentException('Invalid Sonyflake ID string');
        $this->value = $value;
        $this->parsed = Sonyflake::parse($value);
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

    public function getMachineId(): int
    {
        return $this->parsed['machine_id'];
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->parsed['time'];
    }

    public function getVersion(): ?int
    {
        return null;
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
