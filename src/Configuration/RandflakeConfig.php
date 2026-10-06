<?php

declare(strict_types=1);

namespace Infocyph\UID\Configuration;

use Infocyph\UID\Runtime\GenerationContext;
use Infocyph\UID\Sequence\SequenceProviderInterface;

final readonly class RandflakeConfig
{
    public function __construct(
        public int $nodeId,
        public int $leaseStart,
        public int $leaseEnd,
        #[\SensitiveParameter] public string $secret,
        public ?SequenceProviderInterface $sequenceProvider = null,
        public ?GenerationContext $runtime = null,
    ) {}
}
