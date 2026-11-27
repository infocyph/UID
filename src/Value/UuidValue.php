<?php

declare(strict_types=1);

namespace Infocyph\UID\Value;

use DateTimeImmutable;
use DateTimeInterface;
use Infocyph\UID\Contracts\IdValueInterface;
use Infocyph\UID\UUID;
use Throwable;

final readonly class UuidValue implements IdValueInterface
{
    /**
     * @var array{version: int|null, variant: string|null, time: DateTimeInterface|null, node: string|null, tail: string|null, local_domain?: int, local_identifier?: int}
     */
    private array $parsed;

    private string $value;

    /**
     * @throws \Infocyph\UID\Exceptions\UUIDException
     * @throws \Exception
     */
    public function __construct(string $value)
    {
        $this->value = UUID::normalize($value);
        $this->parsed = UUID::parse($this->value);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    #[\Override]
    public function compare(IdValueInterface|string $other): int
    {
        $otherValue = $other instanceof IdValueInterface ? $other->toString() : UUID::normalize($other);

        return strcmp($this->value, $otherValue);
    }

    #[\Override]
    public function getMachineId(): ?string
    {
        return $this->parsed['node'];
    }

    #[\Override]
    public function getTimestamp(): ?DateTimeImmutable
    {
        $time = $this->parsed['time'];
        if ($time === null) {
            return null;
        }

        if ($time instanceof DateTimeImmutable) {
            return $time;
        }

        try {
            return new DateTimeImmutable($time->format(DateTimeInterface::RFC3339_EXTENDED));
        } catch (Throwable) {
            return DateTimeImmutable::createFromInterface($time);
        }
    }

    #[\Override]
    public function getVersion(): ?int
    {
        return $this->parsed['version'];
    }

    #[\Override]
    public function isSortable(): bool
    {
        return in_array($this->getVersion(), [6, 7], true);
    }

    #[\Override]
    public function toString(): string
    {
        return $this->value;
    }
}
