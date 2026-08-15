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
    public function getMachineId(): int
    {
        return $this->parsed['machineId'];
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->parsed['time'];
    }

    protected function invalidMessage(): string
    {
        return 'Invalid TBSL string';
    }

    protected function parser(): callable
    {
        return TBSL::parse(...);
    }

    protected function validator(): callable
    {
        return TBSL::isValid(...);
    }
}
