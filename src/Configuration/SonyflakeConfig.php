<?php

declare(strict_types=1);

namespace Infocyph\UID\Configuration;

use DateTimeInterface;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Runtime\GenerationContext;
use Infocyph\UID\Sequence\SequenceProviderInterface;

final readonly class SonyflakeConfig
{
    public ?int $customEpoch;

    use ResolvesCustomEpoch;
    use ResolvesMachineId;

    /**
     * @param callable():mixed|null $machineIdResolver
     */
    public function __construct(
        public int $machineId = 0,
        ?callable $machineIdResolver = null,
        DateTimeInterface|int|null $customEpoch = null,
        public ?SequenceProviderInterface $sequenceProvider = null,
        public ClockBackwardPolicy $clockBackwardPolicy = ClockBackwardPolicy::WAIT,
        public ?GenerationContext $runtime = null,
    ) {
        $this->machineIdResolver = $machineIdResolver ? $machineIdResolver(...) : null;
        $this->customEpoch = self::normalizeEpoch($customEpoch);
    }
}
