<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

final class EpochGuard
{
    /**
     * @return array{time:int,current:int}
     * @throws \InvalidArgumentException
     */
    public static function resolveStartTime(
        string $timeString,
        string $invalidFormatMessage,
        string $futureMessage,
    ): array {
        $time = strtotime($timeString);
        if ($time === false) {
            throw new \InvalidArgumentException($invalidFormatMessage);
        }

        $current = time();
        if ($time > $current) {
            throw new \InvalidArgumentException($futureMessage);
        }

        return ['time' => $time, 'current' => $current];
    }
}
