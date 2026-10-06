<?php

declare(strict_types=1);

namespace Infocyph\UID\Sequence;

use Closure;
use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Exceptions\SequenceTimestampException;
use Infocyph\UID\Runtime\GenerationContext;
use Infocyph\UID\Support\FileLock;
use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use Throwable;

final class PsrSimpleCacheSequenceProvider implements SequenceProviderInterface
{
    private readonly ?Closure $synchronizer;

    /** @var array<string, array{timestamp:int,sequence:int}> */
    private array $observedState = [];

    /**
     * @param callable(string, callable():int):mixed|null $synchronizer
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly string $prefix = 'uid.seq.',
        private readonly int $waitTime = 1_000,
        private readonly int $maxAttempts = 1_000,
        ?callable $synchronizer = null,
        private readonly ?GenerationContext $runtime = null,
    ) {
        if (preg_match('/^[A-Za-z0-9_.]*$/D', $this->prefix) !== 1) {
            throw new InvalidArgumentException('Cache key prefix contains characters not guaranteed by PSR-16');
        }
        if (
            $waitTime < 1
            || $maxAttempts < 1
            || $waitTime > intdiv(PHP_INT_MAX, $maxAttempts)
        ) {
            throw new InvalidArgumentException('Cache sequence wait policy must contain positive bounded values');
        }

        $this->synchronizer = $synchronizer ? $synchronizer(...) : null;
    }

    /**
     * @throws FileLockException
     */
    public function next(string $type, int $machineId, int $timestamp): int
    {
        $key = $this->key($type, $machineId);

        if ($this->synchronizer !== null) {
            return $this->nextSynchronized($this->synchronizer, $key, $timestamp);
        }

        $lock = $this->acquireLock($key);
        try {
            return $this->nextSafely($key, $timestamp);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function nextSynchronized(Closure $synchronizer, string $key, int $timestamp): int
    {
        try {
            $sequence = $synchronizer(
                $key,
                fn(): int => $this->nextFromCacheState($key, $timestamp),
            );
        } catch (FileLockException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->storageFailure($key, $exception);
        }

        if (!is_int($sequence) || $sequence < 1) {
            throw new FileLockException('Sequence synchronizer must return a positive integer');
        }

        return $sequence;
    }

    private function nextSafely(string $key, int $timestamp): int
    {
        try {
            return $this->nextFromCacheState($key, $timestamp);
        } catch (FileLockException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->storageFailure($key, $exception);
        }
    }

    private function storageFailure(string $key, Throwable $exception): FileLockException
    {
        return new FileLockException(
            'Failed to read/write sequence state from PSR cache for key: ' . $key,
            0,
            $exception,
        );
    }

    /**
     * @return resource
     * @throws FileLockException
     */
    private function acquireLock(string $key)
    {
        $lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uid-cache-lock-' . hash('sha256', $key) . '.lck';

        return FileLock::acquire(
            $lockFile,
            $this->waitTime * $this->maxAttempts,
            'Unable to open sequence cache lock file: ' . $lockFile,
            'Unable to acquire sequence cache lock for key: ' . $key,
            $this->runtime,
        );
    }

    private function key(string $type, int $machineId): string
    {
        if (preg_match('/^[A-Za-z0-9_.]+$/D', $type) !== 1) {
            throw new InvalidArgumentException('Sequence type contains characters not guaranteed by PSR-16');
        }

        $key = $this->prefix . $type . '.' . $machineId;
        if (strlen($key) > 64) {
            throw new InvalidArgumentException('Sequence cache key must not exceed 64 characters');
        }

        return $key;
    }

    private function nextFromCacheState(string $key, int $timestamp): int
    {
        $state = $this->normalizeState($this->cache->get($key), $key);
        $observed = $this->observedState[$key] ?? null;
        if ($state === null && $observed !== null) {
            throw new FileLockException('Cached sequence state was lost for key: ' . $key);
        }

        self::assertNotRegressed($state, $observed, $key);
        $sequence = self::nextSequence($state, $timestamp, $key);
        $nextState = ['timestamp' => $timestamp, 'sequence' => $sequence];
        if (!$this->cache->set($key, $nextState)) {
            throw new FileLockException('Failed to persist sequence state for key: ' . $key);
        }

        $this->observedState[$key] = $nextState;

        return $sequence;
    }

    /**
     * @return array{timestamp:int,sequence:int}|null
     */
    private function normalizeState(mixed $state, string $key): ?array
    {
        if ($state === null) {
            return null;
        }
        if (!is_array($state)) {
            throw new FileLockException('Cached sequence state is malformed for key: ' . $key);
        }

        $stateTimestamp = $state['timestamp'] ?? null;
        $stateSequence = $state['sequence'] ?? null;
        if (
            !is_int($stateTimestamp)
            || !is_int($stateSequence)
            || $stateTimestamp < 0
            || $stateSequence < 1
        ) {
            throw new FileLockException('Cached sequence state is malformed for key: ' . $key);
        }

        return ['timestamp' => $stateTimestamp, 'sequence' => $stateSequence];
    }

    /**
     * @param array{timestamp:int,sequence:int}|null $state
     * @param array{timestamp:int,sequence:int}|null $observed
     */
    private static function assertNotRegressed(?array $state, ?array $observed, string $key): void
    {
        if ($state === null || $observed === null) {
            return;
        }
        if ($state['timestamp'] < $observed['timestamp']) {
            throw new FileLockException('Cached sequence state regressed for key: ' . $key);
        }
        if ($state['timestamp'] === $observed['timestamp'] && $state['sequence'] < $observed['sequence']) {
            throw new FileLockException('Cached sequence state regressed for key: ' . $key);
        }
    }

    /**
     * @param array{timestamp:int,sequence:int}|null $state
     */
    private static function nextSequence(?array $state, int $timestamp, string $key): int
    {
        if ($state === null) {
            return 1;
        }
        if ($state['timestamp'] > $timestamp) {
            throw new SequenceTimestampException(
                $state['timestamp'],
                $timestamp,
                'Sequence timestamp moved backwards for key: ' . $key,
            );
        }
        if ($state['timestamp'] !== $timestamp) {
            return 1;
        }
        if ($state['sequence'] === PHP_INT_MAX) {
            throw new FileLockException('Sequence value exhausted for key: ' . $key);
        }

        return $state['sequence'] + 1;
    }
}
