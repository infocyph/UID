<?php

declare(strict_types=1);

namespace Infocyph\UID\Value;

use Infocyph\UID\Contracts\IdValueInterface;
use Infocyph\UID\ULID;

/** @api */
final readonly class UlidValue implements IdValueInterface
{
    private string $value;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(string $value)
    {
        ULID::isValid($value) || throw new \InvalidArgumentException('Invalid ULID string');
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    #[\Override]
    public function compare(IdValueInterface|string $other): int
    {
        $otherValue = $other instanceof IdValueInterface ? $other->toString() : $other;

        return strcmp($this->value, $otherValue);
    }

    #[\Override]
    public function getMachineId(): int|string|null
    {
        return null;
    }

    /**
     * @throws \Infocyph\UID\Exceptions\ULIDException
     * @throws \Exception
     */
    #[\Override]
    public function getTimestamp(): \DateTimeImmutable
    {
        return ULID::getTime($this->value);
    }

    #[\Override]
    public function getVersion(): ?int
    {
        return null;
    }

    #[\Override]
    public function isSortable(): bool
    {
        return true;
    }

    #[\Override]
    public function toString(): string
    {
        return $this->value;
    }
}
