<?php

namespace Infocyph\UID;


use DateTimeImmutable;
use Exception;

class TBSL
{
    /**
     * Generates a unique identifier using the TBSL algorithm.
     *
     * @param int $machineId 2-digit (0-99) machine identifier. Default is 0.
     * @return string The generated unique identifier.
     * @throws Exception
     */
    public static function generate(int $machineId = 0): string
    {
        $timestamp = microtime();
        $storeData = base_convert(
            substr($timestamp, 11) .
            substr($timestamp, 2, 6) .
            sprintf('%02d', $machineId),
            10,
            16
        );

        return strtoupper(sprintf('%015s%05s', $storeData, substr(bin2hex(random_bytes(3)), 0, 5)));
    }

    /**
     * Parses a TBSL string and returns an array with information about the TBSL.
     *
     * @param string $tbsl The TBSL string to parse.
     * @return array ['isValid', 'time', 'machineId']
     * @throws Exception
     */
    public static function parse(string $tbsl): array
    {
        $data = [
            'isValid' => preg_match('/^[0-9A-F]{20}$/', $tbsl),
            'time' => null,
            'machineId' => null
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
