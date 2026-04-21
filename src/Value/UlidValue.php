<?php

declare(strict_types=1);

namespace Infocyph\UID\Value;

use Infocyph\UID\Contracts\IdValueInterface;
use Infocyph\UID\ULID;

final readonly class UlidValue implements IdValueInterface
{
    private string $value;

    public function __construct(string $value)
    {
        ULID::isValid($value) || throw new \InvalidArgumentException('Invalid ULID string');
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function compare(IdValueInterface|string $other): int
    {
        $otherValue = $other instanceof IdValueInterface ? $other->toString() : $other;

        return strcmp($this->value, $otherValue);
    }

    public function getMachineId(): int|string|null
    {
        return null;
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return ULID::getTime($this->value);
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
