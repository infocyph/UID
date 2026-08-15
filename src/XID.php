<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use Exception;
use Infocyph\UID\Exceptions\UIDException;
use Infocyph\UID\Support\BinaryUnpack;

final class XID
{
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuv';

    private static ?int $counter = null;

    private static ?string $machine = null;

    private static ?string $pid = null;

    private static ?int $sourcePid = null;

    /**
     * @throws Exception
     */
    public static function fromBytes(string $bytes): string
    {
        if (strlen($bytes) !== 12) {
            throw new UIDException('XID binary data must be exactly 12 bytes');
        }

        return self::encodeBytes($bytes);
    }

    /**
     * Generates an XID string (20 chars, base32 lowercase).
     *
     * @throws Exception
     */
    public static function generate(): string
    {
        self::ensureProcessState();
        $time = pack('N', time());
        $machine = self::machine();
        $pid = self::pidBytes();
        $counter = self::counterBytes();

        $bytes = $time . $machine . $pid . $counter; // 12 bytes

        return self::encodeBytes($bytes);
    }

    public static function isValid(string $xid): bool
    {
        return preg_match('/^[0-9a-v]{19}[0g]$/D', $xid) === 1;
    }

    /**
     * @return array{time: DateTimeImmutable, machine: string, pid: int, counter: int}
     * @throws Exception
     */
    public static function parse(string $xid): array
    {
        if (!self::isValid($xid)) {
            throw new UIDException('Invalid XID string');
        }

        $bytes = self::toBytes($xid);
        $timestamp = BinaryUnpack::u32(substr($bytes, 0, 4), 'Unable to parse XID timestamp');

        return [
            'time' => new DateTimeImmutable('@' . $timestamp),
            'machine' => bin2hex(substr($bytes, 4, 3)),
            'pid' => BinaryUnpack::u16(substr($bytes, 7, 2), 'Unable to parse XID pid'),
            'counter' => BinaryUnpack::u24(substr($bytes, 9, 3), 'Unable to parse XID counter'),
        ];
    }

    /**
     * @throws Exception
     */
    public static function toBytes(string $xid): string
    {
        if (!self::isValid($xid)) {
            throw new UIDException('Invalid XID string');
        }

        return self::decodeText($xid);
    }

    private static function counterBytes(): string
    {
        self::$counter ??= random_int(0, 0xFFFFFF);
        self::$counter = (self::$counter + 1) & 0xFFFFFF;

        return substr(pack('N', self::$counter), 1, 3);
    }

    private static function decodeText(string $xid): string
    {
        $buffer = 0;
        $bits = 0;
        $bytes = '';

        for ($index = 0; $index < 20; ++$index) {
            $digit = strpos(self::ALPHABET, $xid[$index]);
            $digit !== false || throw new \LogicException('Validated XID contains an invalid character');
            $buffer = ($buffer << 5) | $digit;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $bytes .= chr(($buffer >> $bits) & 0xff);
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $bytes;
    }

    private static function encodeBytes(string $bytes): string
    {
        $buffer = 0;
        $bits = 0;
        $encoded = '';

        for ($index = 0; $index < 12; ++$index) {
            $buffer = ($buffer << 8) | ord($bytes[$index]);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ALPHABET[($buffer >> $bits) & 0x1f];
                $buffer &= (1 << $bits) - 1;
            }
        }

        if ($bits > 0) {
            $encoded .= self::ALPHABET[($buffer << (5 - $bits)) & 0x1f];
        }

        return $encoded;
    }

    private static function ensureProcessState(): void
    {
        $pid = (int) getmypid();
        if (self::$sourcePid === $pid) {
            return;
        }

        self::$sourcePid = $pid;
        self::$pid = pack('n', $pid % 0x10000);
        self::$counter = random_int(0, 0xffffff);
    }

    private static function machine(): string
    {
        return self::$machine ??= substr(hash('sha256', gethostname() ?: 'localhost', true), 0, 3);
    }

    private static function pidBytes(): string
    {
        if (self::$pid !== null) {
            return self::$pid;
        }

        return self::$pid = pack('n', (int) getmypid() % 0x10000);
    }
}
