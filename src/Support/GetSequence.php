<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use Infocyph\UID\Sequence\CallbackSequenceProvider;
use Infocyph\UID\Sequence\FilesystemSequenceProvider;
use Infocyph\UID\Sequence\InMemorySequenceProvider;
use Infocyph\UID\Sequence\PsrSimpleCacheSequenceProvider;
use Infocyph\UID\Sequence\SequenceProviderInterface;
use Psr\SimpleCache\CacheInterface;

trait GetSequence
{
    private static ?SequenceProviderInterface $sequenceProvider = null;

    /**
     * Reset to the default filesystem sequence provider.
     */
    public static function resetSequenceProvider(): void
    {
        self::$sequenceProvider = null;
    }

    /**
     * Set a custom sequence provider.
     */
    public static function setSequenceProvider(SequenceProviderInterface $provider): void
    {
        self::$sequenceProvider = $provider;
    }

    /**
     * Use the default filesystem-backed sequence provider.
     */
    public static function useFilesystemSequenceProvider(
        ?string $baseDirectory = null,
        int $waitTime = 100,
        int $maxAttempts = 10,
    ): void {
        self::$sequenceProvider = new FilesystemSequenceProvider($baseDirectory, $waitTime, $maxAttempts);
    }

    /**
     * Use in-memory sequence provider (process-local).
     */
    public static function useInMemorySequenceProvider(): void
    {
        self::$sequenceProvider = new InMemorySequenceProvider();
    }

    /**
     * Use a user-supplied callback to resolve sequences.
     *
     * @param callable(string, int, int):int $callback
     */
    public static function useSequenceCallback(callable $callback): void
    {
        self::$sequenceProvider = new CallbackSequenceProvider($callback);
    }

    /**
     * Use PSR-16 simple cache-backed sequence provider.
     */
    public static function useSimpleCacheSequenceProvider(
        CacheInterface $cache,
        string $prefix = 'uid:seq:',
        int $waitTime = 100,
        int $maxAttempts = 10,
    ): void {
        self::$sequenceProvider = new PsrSimpleCacheSequenceProvider($cache, $prefix, $waitTime, $maxAttempts);
    }

    /**
     * Generates a sequence number based on provider strategy.
     *
     * @param int $dateTime The current time.
     * @param int $machineId The machine ID.
     * @param string $type The type identifier.
     */
    private static function sequence(
        int $dateTime,
        int $machineId,
        string $type,
        ?SequenceProviderInterface $provider = null,
    ): int {
        $provider ??= self::$sequenceProvider ??= new FilesystemSequenceProvider();

        return $provider->next($type, $machineId, $dateTime);
    }
}
