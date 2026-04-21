<?php

declare(strict_types=1);

namespace Infocyph\UID\Configuration;

use Closure;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Sequence\SequenceProviderInterface;

final readonly class TBSLConfig
{
    private ?Closure $machineIdResolver;

    /**
     * @param callable():int|null $machineIdResolver
     */
    public function __construct(
        public int $machineId = 0,
        public bool $sequenced = false,
        ?callable $machineIdResolver = null,
        public ?SequenceProviderInterface $sequenceProvider = null,
        public ClockBackwardPolicy $clockBackwardPolicy = ClockBackwardPolicy::WAIT,
        public IdOutputType $outputType = IdOutputType::STRING,
    ) {
        $this->machineIdResolver = $machineIdResolver ? $machineIdResolver(...) : null;
    }

    public function resolveMachineId(): int
    {
        if ($this->machineIdResolver === null) {
            return $this->machineId;
        }

        return (int) ($this->machineIdResolver)();
    }
}
