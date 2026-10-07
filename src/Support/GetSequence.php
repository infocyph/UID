<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use Infocyph\UID\Runtime\GenerationContext;
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
        string $namespace = '',
        ?int $lockTimeoutMicros = null,
        int $reservationSize = 1,
        ?GenerationContext $runtime = null,
    ): void {
        self::$sequenceProvider = new FilesystemSequenceProvider(
            $baseDirectory,
            $namespace,
            $lockTimeoutMicros,
            $reservationSize,
            $runtime,
        );
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
        string $prefix = 'uid.seq.',
        ?callable $synchronizer = null,
        int $waitTime = 1_000,
        int $maxAttempts = 1_000,
        ?GenerationContext $runtime = null,
    ): void {
        self::$sequenceProvider = new PsrSimpleCacheSequenceProvider(
            $cache,
            $prefix,
            $waitTime,
            $maxAttempts,
            $synchronizer,
            $runtime,
        );
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
        ?GenerationContext $runtime = null,
    ): int {
        $provider ??= self::$sequenceProvider ??= new FilesystemSequenceProvider();

        if ($runtime !== null && ($provider instanceof FilesystemSequenceProvider || $provider instanceof PsrSimpleCacheSequenceProvider)) {
            return $provider->next($type, $machineId, $dateTime, $runtime);
        }

        return $provider->next($type, $machineId, $dateTime);
    }
}
