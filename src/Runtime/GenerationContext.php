<?php

declare(strict_types=1);

namespace Infocyph\UID\Runtime;

use InvalidArgumentException;
use Psr\Clock\ClockInterface;

final readonly class GenerationContext
{
    private const int DEFAULT_WAIT_TIMEOUT_MICROS = 1_000_000;

    public function __construct(
        public ?ClockInterface $clock = null,
        public ?RunwireBinding $runwire = null,
        public int $waitTimeoutMicros = self::DEFAULT_WAIT_TIMEOUT_MICROS,
    ) {
        if ($waitTimeoutMicros < 1) {
            throw new InvalidArgumentException('Generation wait timeout must be a positive number of microseconds');
        }
    }

    public function assertActive(): void
    {
        $this->runwire?->assertActive();
    }

    public function nowMicroseconds(): int
    {
        $this->assertActive();

        if ($this->clock === null) {
            return (int) floor(microtime(true) * 1_000_000);
        }

        return (int) $this->clock->now()->format('Uu');
    }

    public function nowMilliseconds(): int
    {
        return intdiv($this->nowMicroseconds(), 1_000);
    }

    public function nowSeconds(): int
    {
        return intdiv($this->nowMicroseconds(), 1_000_000);
    }

    public function waitDeadlineNanoseconds(): int
    {
        $deadline = hrtime(true) + ($this->waitTimeoutMicros * 1_000);
        $runwireDeadline = $this->runwire?->deadlineNanoseconds();

        return $runwireDeadline === null ? $deadline : min($deadline, $runwireDeadline);
    }

    public function sleepMicroseconds(int $microseconds): void
    {
        $this->assertActive();
        if ($this->runwire !== null) {
            $this->runwire->sleep($microseconds / 1_000_000);
            $this->assertActive();

            return;
        }

        usleep($microseconds);
    }
}
