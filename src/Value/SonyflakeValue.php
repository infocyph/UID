<?php

declare(strict_types=1);

namespace Infocyph\UID\Value;

use DateTimeImmutable;
use Infocyph\UID\Enums\SonyflakeFormat;
use Infocyph\UID\Sonyflake;

/**
 * @extends AbstractParsedIdValue<array{time: DateTimeImmutable, sequence: int, machine_id: int}>
 */
final readonly class SonyflakeValue extends AbstractParsedIdValue
{
    public function __construct(
        string $value,
        private ?int $customEpoch = null,
        private SonyflakeFormat $format = SonyflakeFormat::UID,
    ) {
        parent::__construct($value);
    }

    public function getFormat(): SonyflakeFormat
    {
        return $this->format;
    }

    public function getMachineId(): int
    {
        return $this->parsed['machine_id'];
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->parsed['time'];
    }

    protected function invalidMessage(): string
    {
        return 'Invalid Sonyflake ID string';
    }

    protected function parser(): callable
    {
        return $this->customEpoch === null
            ? fn(string $id): array => Sonyflake::parse($id, $this->format)
            : fn(string $id): array => Sonyflake::parseWithEpoch($id, $this->customEpoch, $this->format);
    }

    protected function validator(): callable
    {
        return Sonyflake::isValid(...);
    }
}
