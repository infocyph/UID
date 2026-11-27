<?php

declare(strict_types=1);

namespace Infocyph\UID\Value;

use DateTimeImmutable;
use Infocyph\UID\TBSL;

/**
 * @extends AbstractParsedIdValue<array{time: DateTimeImmutable, machineId: int}>
 */
final readonly class TbslValue extends AbstractParsedIdValue
{
    #[\Override]
    public function getMachineId(): int
    {
        return $this->parsed['machineId'];
    }

    #[\Override]
    public function getTimestamp(): DateTimeImmutable
    {
        return $this->parsed['time'];
    }

    #[\Override]
    protected function invalidMessage(): string
    {
        return 'Invalid TBSL string';
    }

    #[\Override]
    protected function parser(): callable
    {
        return TBSL::parse(...);
    }

    #[\Override]
    protected function validator(): callable
    {
        return TBSL::isValid(...);
    }
}
