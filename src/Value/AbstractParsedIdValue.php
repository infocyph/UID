<?php

declare(strict_types=1);

namespace Infocyph\UID\Value;

use Infocyph\UID\Contracts\IdValueInterface;

/**
 * @template TParsed of array
 */
abstract readonly class AbstractParsedIdValue implements IdValueInterface
{
    use ComparableIdValue;

    /** @var TParsed */
    protected array $parsed;

    final public function __construct(string $value)
    {
        $this->parsed = $this->initializeComparableValue(
            $value,
            $this->validator(),
            $this->parser(),
            $this->invalidMessage(),
        );
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
