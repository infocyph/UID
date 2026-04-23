<?php

declare(strict_types=1);

namespace Infocyph\UID\Value;

use DateTimeImmutable;
use Infocyph\UID\Contracts\IdValueInterface;
use Infocyph\UID\TBSL;

final readonly class TbslValue implements IdValueInterface
{
    /**
     * @var array{isValid: bool, time: DateTimeImmutable|null, machineId: int|null}
     */
    private array $parsed;

    private string $value;

    public function __construct(string $value)
    {
        TBSL::isValid($value) || throw new \InvalidArgumentException('Invalid TBSL string');
        $this->value = $value;
        $this->parsed = TBSL::parse($value);
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

    public function getMachineId(): ?int
    {
        return $this->parsed['machineId'];
    }

    public function getTimestamp(): ?DateTimeImmutable
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
