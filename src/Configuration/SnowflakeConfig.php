<?php

declare(strict_types=1);

namespace Infocyph\UID\Configuration;

use Closure;
use DateTimeInterface;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Sequence\SequenceProviderInterface;

final readonly class SnowflakeConfig
{
    private ?Closure $nodeResolver;

    /**
     * @param callable():array{0:int,1:int}|null $nodeResolver
     * @param DateTimeInterface|int|string|null $customEpoch Epoch in ms (int), parseable date string, or DateTime.
     */
    public function __construct(
        public int $datacenterId = 0,
        public int $workerId = 0,
        ?callable $nodeResolver = null,
        public DateTimeInterface|int|string|null $customEpoch = null,
        public ?SequenceProviderInterface $sequenceProvider = null,
        public ClockBackwardPolicy $clockBackwardPolicy = ClockBackwardPolicy::WAIT,
        public IdOutputType $outputType = IdOutputType::STRING,
    ) {
        $this->nodeResolver = $nodeResolver ? $nodeResolver(...) : null;
    }

    public function resolveCustomEpochMs(): ?int
    {
        if ($this->customEpoch === null) {
            return null;
        }

        if ($this->customEpoch instanceof DateTimeInterface) {
            return (int) $this->customEpoch->format('Uv');
        }

        if (is_int($this->customEpoch)) {
            return $this->customEpoch;
        }

        $epoch = strtotime($this->customEpoch);

        return $epoch === false ? null : $epoch * 1000;
    }

    /**
     * @return array{0:int,1:int}
     */
    public function resolveNode(): array
    {
        if ($this->nodeResolver === null) {
            return [$this->datacenterId, $this->workerId];
        }

        /** @var array{0:int,1:int} $resolved */
        $resolved = ($this->nodeResolver)();

        return $resolved;
    }
}
