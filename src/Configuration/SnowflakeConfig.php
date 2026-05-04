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
    use ResolvesCustomEpoch;

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
