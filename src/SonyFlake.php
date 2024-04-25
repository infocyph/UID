<?php

namespace Infocyph\UID;

use Infocyph\UID\Exceptions\SonyflakeException;
use Infocyph\UID\Snowflake;

class SonyFlake
{
    public const MAX_TIMESTAMP_LENGTH = 39;
    public const MAX_MACHINEID_LENGTH = 16;
    public const MAX_SEQUENCE_LENGTH = 8;
    public const MAX_SEQUENCE_SIZE = (-1 ^ (-1 << self::MAX_SEQUENCE_LENGTH));
    public static function generate(string $machineId): string
    {
        $maxMachineID = -1 ^ (-1 << self::MAX_MACHINEID_LENGTH);
        if ($machineId < 0 || $machineId > $maxMachineID) {
            throw new SonyflakeException("Invalid machine ID, must be between 0 ~ {$maxMachineID}.");
        }


    }
    public static function setStartTimeStamp(string $timeString): void
    {
        $time = strtotime($timeString);
        $current = time();

        if ($time > $current) {
            throw new SonyflakeException('The start time cannot be in the future');
        }

        if (($current - $time) > (-1 ^ (-1 << self::$maxTimestampLength))) {
            throw new SnowflakeException(
                sprintf(
                    'The current microtime - start_time is not allowed to exceed -1 ^ (-1 << %d),
                    You can reset the start time to fix this',
                    self::$maxTimestampLength
                )
            );
        }

        self::$startTime = $time * 1000;
    }

    public function setStartTimeStamp(int $millisecond): self
    {
        $elapsedTime = floor(($this->getCurrentMillisecond() - $millisecond) / 10) | 0;
        if ($elapsedTime < 0) {
            throw new SnowflakeException('The start time cannot be greater than the current time');
        }

        $this->ensureEffectiveRuntime($elapsedTime);

        $this->startTime = $millisecond;

        return $this;
    }
}
