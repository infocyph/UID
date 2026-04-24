<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use Infocyph\UID\Contracts\IdAlgorithmInterface;
use Infocyph\UID\Support\BaseEncoder;

final class XID implements IdAlgorithmInterface
{
    private static ?int $counter = null;

    private static ?string $machine = null;

    private static ?string $pid = null;

    /**
     * @throws Exception
     */
    public static function fromBytes(string $bytes): string
    {
        if (strlen($bytes) !== 12) {
            throw new Exception('XID binary data must be exactly 12 bytes');
        }

        return str_pad(BaseEncoder::encodeBytes($bytes, 32), 20, '0', STR_PAD_LEFT);
    }

    /**
     * Generates an XID string (20 chars, base32 lowercase).
     *
     * @throws Exception
     */
    public static function generate(): string
    {
        $time = pack('N', time());
        $machine = self::machine();
        $pid = self::pidBytes();
        $counter = self::counterBytes();

        $bytes = $time . $machine . $pid . $counter; // 12 bytes

        return str_pad(BaseEncoder::encodeBytes($bytes, 32), 20, '0', STR_PAD_LEFT);
    }

    public static function isValid(string $xid): bool
    {
        return preg_match('/^[0-9a-v]{20}$/', $xid) === 1;
    }

    /**
     * @return array{isValid: bool, time: DateTimeImmutable|null, machine: string|null, pid: int|null, counter: int|null}
     * @throws Exception
     */
    public static function parse(string $xid): array
    {
        $data = ['isValid' => self::isValid($xid), 'time' => null, 'machine' => null, 'pid' => null, 'counter' => null];
        if (!$data['isValid']) {
            return $data;
        }

        $bytes = self::toBytes($xid);
        $unpackedTimestamp = unpack('N', substr($bytes, 0, 4));
        ($unpackedTimestamp !== false) || throw new Exception('Unable to parse XID timestamp');
        $timestamp = $unpackedTimestamp[1] ?? null;
        is_int($timestamp) || throw new Exception('Unable to parse XID timestamp');
        $data['time'] = new DateTimeImmutable('@' . $timestamp);
        $data['machine'] = bin2hex(substr($bytes, 4, 3));
        $unpackedPid = unpack('n', substr($bytes, 7, 2));
        ($unpackedPid !== false) || throw new Exception('Unable to parse XID pid');
        $pid = $unpackedPid[1] ?? null;
        is_int($pid) || throw new Exception('Unable to parse XID pid');
        $data['pid'] = $pid;
        $counterBytes = substr($bytes, 9, 3);
        $unpackedCounter = unpack('N', chr(0) . $counterBytes);
        ($unpackedCounter !== false) || throw new Exception('Unable to parse XID counter');
        $counter = $unpackedCounter[1] ?? null;
        is_int($counter) || throw new Exception('Unable to parse XID counter');
        $data['counter'] = $counter;

        return $data;
    }

    /**
     * @throws Exception
     */
    public static function toBytes(string $xid): string
    {
        if (!self::isValid($xid)) {
            throw new Exception('Invalid XID string');
        }

        return BaseEncoder::decodeToBytes($xid, 32, 12);
    }

    private static function counterBytes(): string
    {
        self::$counter ??= random_int(0, 0xFFFFFF);
        self::$counter = (self::$counter + 1) & 0xFFFFFF;

        return substr(pack('N', self::$counter), 1, 3);
    }

    private static function machine(): string
    {
        return self::$machine ??= substr(hash('sha1', gethostname() ?: 'localhost', true), 0, 3);
    }

    private static function pidBytes(): string
    {
        if (self::$pid !== null) {
            return self::$pid;
        }

        return self::$pid = pack('n', getmypid() % 0x10000);
    }
}
