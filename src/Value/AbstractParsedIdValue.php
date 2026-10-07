<?php

declare(strict_types=1);

namespace Infocyph\UID\Value;

use Infocyph\UID\Contracts\IdValueInterface;
use Infocyph\UID\IdComparator;

/**
 * @template TParsed of array
 */
abstract readonly class AbstractParsedIdValue implements IdValueInterface
{
    /** @var TParsed */
    protected array $parsed;

    private string $value;

    public function __construct(string $value)
    {
        $validator = $this->validator();
        $validator($value) || throw new \InvalidArgumentException($this->invalidMessage());

        $parser = $this->parser();
        $this->value = $value;
        $this->parsed = $parser($value);
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

    abstract protected function invalidMessage(): string;

    /**
     * @return callable(string):TParsed
     */
    abstract protected function parser(): callable;

    /**
     * @return callable(string):bool
     */
    abstract protected function validator(): callable;
}
