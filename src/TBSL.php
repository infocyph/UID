<?php

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;

final class TBSL
{
    use GetSequence;

    private static int $maxSequenceLength = 10;

    /**
     * Generates a unique identifier using the TBSL algorithm.
     *
     * @param int  $machineId 2-digit (0-99) machine identifier. Default is 0.
     * @param bool $sequenced Whether to use sequencing.
     * @return string The generated unique identifier.
     * @throws Exception
     */
    public static function generate(int $machineId = 0, bool $sequenced = false): string
    {
        // Get current microsecond timestamp
        [$micro, $seconds] = explode(' ', microtime());
        $timeSequence = $seconds . substr($micro, 2, 6);

        // Convert timeSequence + machineId to base16
        $storeData = base_convert($timeSequence . sprintf('%02d', $machineId), 10, 16);

        return strtoupper(sprintf(
            '%015s%05s',
            $storeData,
            substr(self::sequencedGenerate($machineId, $sequenced, (int)$timeSequence), -1, 5)
        ));
    }

    /**
     * Generates a sequence or random bytes based on the sequencing flag.
     *
     * @param int $machineId Machine identifier.
     * @param bool $enableSequence Whether to enable sequence.
     * @param int $timeSequence The timestamp sequence.
     * @return string Hexadecimal sequence.
     * @throws Exception
     */
    private static function sequencedGenerate(int $machineId, bool $enableSequence, int $timeSequence): string
    {
        return match ($enableSequence) {
            true => dechex(self::sequence($timeSequence, $machineId, 'tbsl', self::$maxSequenceLength)),
            default => bin2hex(random_bytes(3)),
        };
    }

    /**
     * Parses a TBSL string and returns an array with its components.
     *
     * @param string $tbsl The TBSL string to parse.
     * @return array ['isValid' => bool, 'time' => DateTimeImmutable|null, 'machineId' => int|null]
     * @throws Exception
     */
    public static function parse(string $tbsl): array
    {
        $data = [
            'isValid' => preg_match('/^[0-9A-F]{20}$/', $tbsl),
            'time' => null,
            'machineId' => null,
        ];

        if (!$data['isValid']) {
            return $data;
        }

        $storeData = base_convert(substr($tbsl, 0, 15), 16, 10);
        $data['time'] = new DateTimeImmutable('@' . substr($storeData, 0, 10) . '.' . substr($storeData, 10, 6));
        $data['machineId'] = substr($storeData, -2);

        return $data;
    }
}
