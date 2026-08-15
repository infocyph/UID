<?php

declare(strict_types=1);

namespace Infocyph\UID;

use DateTimeImmutable;
use DateTimeInterface;
use Infocyph\UID\Exceptions\ObjectIDException;
use Infocyph\UID\Support\BinaryUnpack;

final class ObjectID
{
    private static int $counter;

    private static string $processRandom;

    private static ?int $sourcePid = null;

    public static function fromBytes(string $bytes): string
    {
        if (strlen($bytes) !== 12) {
            throw new ObjectIDException('ObjectID binary data must be exactly 12 bytes');
        }

        return bin2hex($bytes);
    }

    public static function generate(?DateTimeInterface $dateTime = null): string
    {
        self::ensureProcessState();
        $timestamp = $dateTime === null ? time() : (int) $dateTime->format('U');
        if ($timestamp < 0 || $timestamp > 0xffffffff) {
            throw new ObjectIDException('ObjectID timestamp must fit in an unsigned 32-bit integer');
        }

        $counter = self::$counter;
        self::$counter = (self::$counter + 1) & 0xffffff;

        return bin2hex(pack('N', $timestamp) . self::$processRandom . substr(pack('N', $counter), 1));
    }

    public static function isValid(string $id): bool
    {
        return strlen($id) === 24 && ctype_xdigit($id) && strtolower($id) === $id;
    }

    /**
     * @return array{time:DateTimeImmutable, process_random:string, counter:int}
     */
    public static function parse(string $id): array
    {
        $bytes = self::toBytes($id);
        $timestamp = BinaryUnpack::u32(substr($bytes, 0, 4), 'Unable to parse ObjectID timestamp');

        return [
            'time' => new DateTimeImmutable('@' . $timestamp),
            'process_random' => bin2hex(substr($bytes, 4, 5)),
            'counter' => BinaryUnpack::u24(substr($bytes, 9, 3), 'Unable to parse ObjectID counter'),
        ];
    }

    public static function toBytes(string $id): string
    {
        if (!self::isValid($id)) {
            throw new ObjectIDException('Invalid ObjectID string');
        }

        $bytes = hex2bin($id);
        $bytes !== false || throw new ObjectIDException('Unable to decode ObjectID');

        return $bytes;
    }

    private static function ensureProcessState(): void
    {
        $pid = (int) getmypid();
        if (self::$sourcePid === $pid) {
            return;
        }

        self::$sourcePid = $pid;
        self::$processRandom = random_bytes(5);
        self::$counter = random_int(0, 0xffffff);
    }
}
