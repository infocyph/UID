<?php

declare(strict_types=1);

namespace Infocyph\UID\Configuration;

use Closure;
use DateTimeInterface;
use Infocyph\UID\Enums\ClockBackwardPolicy;
use Infocyph\UID\Sequence\SequenceProviderInterface;

final readonly class SnowflakeConfig
{
    use ResolvesCustomEpoch;

    private ?Closure $nodeResolver;

    /**
     * @param callable():mixed|null $nodeResolver
     * @param DateTimeInterface|int|null $customEpoch Epoch in milliseconds or a date-time value.
     */
    public function __construct(
        public int $datacenterId = 0,
        public int $workerId = 0,
        ?callable $nodeResolver = null,
        public DateTimeInterface|int|null $customEpoch = null,
        public ?SequenceProviderInterface $sequenceProvider = null,
        public ClockBackwardPolicy $clockBackwardPolicy = ClockBackwardPolicy::WAIT,
    ) {
        $this->nodeResolver = $nodeResolver ? $nodeResolver(...) : null;
    }

    /**
     * @return array{0:int, 1:int}
     */
    public function resolveNode(): array
    {
        if ($this->nodeResolver === null) {
            return [$this->datacenterId, $this->workerId];
        }

        $resolved = ($this->nodeResolver)();
        if (
            !is_array($resolved)
            || !isset($resolved[0], $resolved[1])
            || !is_int($resolved[0])
            || !is_int($resolved[1])
        ) {
            throw new \UnexpectedValueException('Snowflake node resolver must return two integer IDs');
        }

        return [$resolved[0], $resolved[1]];
    }
}
