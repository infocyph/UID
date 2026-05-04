<?php

declare(strict_types=1);

namespace Infocyph\UID\Value;

use Infocyph\UID\Contracts\IdValueInterface;
use Infocyph\UID\IdComparator;

trait ComparableIdValue
{
    private string $value;

    public function __toString(): string
    {
        return $this->toString();
    }

    public function compare(IdValueInterface|string $other): int
    {
        $otherValue = $other instanceof IdValueInterface ? $other->toString() : $other;

        return IdComparator::compare($this->value, $otherValue);
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

    /**
     * @template T of array
     * @param callable(string):bool $validator
     * @param callable(string):T $parser
     * @return T
     */
    protected function initializeComparableValue(
        string $value,
        callable $validator,
        callable $parser,
        string $invalidMessage,
    ): array {
        $validator($value) || throw new \InvalidArgumentException($invalidMessage);
        $this->value = $value;

        return $parser($value);
    }
}
