<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Infocyph\UID\Contracts\IdAlgorithmInterface;
use Infocyph\UID\Support\BaseEncoder;
use Infocyph\UID\Support\BinaryUnpack;

final class KSUID implements IdAlgorithmInterface
{
    private const EPOCH = 1_400_000_000;

    private const MAX_ENCODED = 'aWgEPTl1tmebfsQzFP4bxwgy80V';

    private const MAX_TIMESTAMP_OFFSET = 0xffffffff;

    /**
     * @throws Exception
     */
    public static function fromBytes(string $bytes): string
    {
        if (strlen($bytes) !== 20) {
            throw new Exception('KSUID binary data must be exactly 20 bytes');
        }

        return str_pad(BaseEncoder::encodeBytes($bytes, 62), 27, '0', STR_PAD_LEFT);
    }

    /**
     * Generates a KSUID (27 chars, base62).
     *
     * @throws Exception
     */
    public static function generate(?DateTimeInterface $dateTime = null): string
    {
        $timestamp = ($dateTime?->getTimestamp() ?? time()) - self::EPOCH;
        if ($timestamp < 0 || $timestamp > self::MAX_TIMESTAMP_OFFSET) {
            throw new \InvalidArgumentException('KSUID timestamp is outside its unsigned 32-bit lifetime');
        }

        $timeBytes = pack('N', $timestamp);
        $payload = random_bytes(16);
        $bytes = $timeBytes . $payload;

        return str_pad(BaseEncoder::encodeBytes($bytes, 62), 27, '0', STR_PAD_LEFT);
    }

    public static function isValid(string $ksuid): bool
    {
        return preg_match('/^[0-9A-Za-z]{27}$/D', $ksuid) === 1
            && strcmp($ksuid, self::MAX_ENCODED) <= 0;
    }

    /**
     * @return array{isValid: bool, time: DateTimeImmutable|null, payload: string|null}
     * @throws Exception
     */
    public static function parse(string $ksuid): array
    {
        $data = ['isValid' => self::isValid($ksuid), 'time' => null, 'payload' => null];
        if (!$data['isValid']) {
            return $data;
        }

        $bytes = self::toBytes($ksuid);
        $timestamp = BinaryUnpack::u32(substr($bytes, 0, 4), 'Unable to parse KSUID timestamp') + self::EPOCH;
        $data['time'] = new DateTimeImmutable('@' . $timestamp);
        $data['payload'] = bin2hex(substr($bytes, 4));

        return $data;
    }

    /**
     * @throws Exception
     */
    public static function toBytes(string $ksuid): string
    {
        if (!self::isValid($ksuid)) {
            throw new Exception('Invalid KSUID string');
        }

        return BaseEncoder::decodeToBytes($ksuid, 62, 20);
    }
}
