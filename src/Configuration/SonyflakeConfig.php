<?php

declare(strict_types=1);

namespace Infocyph\UID\Configuration;

use Closure;
use DateTimeInterface;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Sequence\SequenceProviderInterface;

final readonly class SonyflakeConfig
{
    private ?Closure $machineIdResolver;

    /**
     * @param callable():int|null $machineIdResolver
     */
    public function __construct(
        public int $machineId = 0,
        ?callable $machineIdResolver = null,
        public DateTimeInterface|int|string|null $customEpoch = null,
        public ?SequenceProviderInterface $sequenceProvider = null,
        public ClockBackwardPolicy $clockBackwardPolicy = ClockBackwardPolicy::WAIT,
        public IdOutputType $outputType = IdOutputType::STRING,
    ) {
        $this->machineIdResolver = $machineIdResolver ? $machineIdResolver(...) : null;
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

    public function resolveMachineId(): int
    {
        if ($this->machineIdResolver === null) {
            return $this->machineId;
        }

        return (int) ($this->machineIdResolver)();
    }
}
