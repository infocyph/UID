<?php

declare(strict_types=1);

namespace Infocyph\UID\Configuration;

use DateTimeInterface;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Sequence\SequenceProviderInterface;

final readonly class SonyflakeConfig
{
    use ResolvesCustomEpoch;
    use ResolvesMachineId;

    /**
     * @param callable():mixed|null $machineIdResolver
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
}
