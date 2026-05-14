<?php

declare(strict_types=1);

namespace Infocyph\UID\Benchmarks;

final class BenchBootstrap
{
    public static function load(): void
    {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
    }

    /**
     * @return array{0:int,1:int,2:string}
     */
    public static function randflakeContext(int $ttlSeconds = 3600): array
    {
        $now = time();

        return [
            $now - 5,
            $now + $ttlSeconds,
            self::randflakeSecret(),
        ];
    }

    public static function randflakeSecret(): string
    {
        return 'super-secret-key';
    }
}
