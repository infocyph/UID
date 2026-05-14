<?php

declare(strict_types=1);

namespace Infocyph\UID\Configuration;

use Infocyph\UID\Enums\IdOutputType;
use Infocyph\UID\Sequence\SequenceProviderInterface;

final readonly class RandflakeConfig
{
    public function __construct(
        public int $nodeId,
        public int $leaseStart,
        public int $leaseEnd,
        public string $secret,
        public ?SequenceProviderInterface $sequenceProvider = null,
        public IdOutputType $outputType = IdOutputType::STRING,
    ) {}
}
