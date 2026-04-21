<?php

declare(strict_types=1);

namespace Infocyph\UID\Contracts;

use DateTimeImmutable;
use Stringable;

interface IdValueInterface extends Stringable
{
    public function compare(IdValueInterface|string $other): int;

    public function getMachineId(): int|string|null;

    public function getTimestamp(): ?DateTimeImmutable;

    public function getVersion(): ?int;

    public function isSortable(): bool;

    public function toString(): string;
}
