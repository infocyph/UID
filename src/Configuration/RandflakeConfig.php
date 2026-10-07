<?php

declare(strict_types=1);

namespace Infocyph\UID\Configuration;

use Infocyph\UID\Enums\RandflakeFormat;
use Infocyph\UID\Exceptions\RandflakeException;
use Infocyph\UID\Runtime\GenerationContext;
use Infocyph\UID\Sequence\SequenceProviderInterface;

final readonly class RandflakeConfig
{
    public function __construct(
        public int $nodeId,
        public int $leaseStart,
        public int $leaseEnd,
        #[\SensitiveParameter]
        public string $secret,
        public ?SequenceProviderInterface $sequenceProvider = null,
        public ?GenerationContext $runtime = null,
        public RandflakeFormat $format = RandflakeFormat::UID,
        public ?int $leaseEndExclusive = null,
    ) {}

    public function resolveLeaseEndExclusive(): int
    {
        if ($this->leaseEndExclusive !== null) {
            return $this->leaseEndExclusive;
        }
        if ($this->leaseEnd === PHP_INT_MAX) {
            throw new RandflakeException('randflake: inclusive lease end cannot be converted to an exclusive boundary');
        }

        return $this->leaseEnd + 1;
    }
}
