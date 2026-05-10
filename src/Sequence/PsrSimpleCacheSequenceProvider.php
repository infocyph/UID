<?php

declare(strict_types=1);

namespace Infocyph\UID\Sequence;

use Closure;
use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Support\FileLock;
use Psr\SimpleCache\CacheInterface;
use Throwable;

final readonly class PsrSimpleCacheSequenceProvider implements SequenceProviderInterface
{
    private ?Closure $synchronizer;

    /**
     * @param callable(string, callable():int):mixed|null $synchronizer
     */
    public function __construct(
        private CacheInterface $cache,
        private string $prefix = 'uid:seq:',
        private int $waitTime = 1_000,
        private int $maxAttempts = 1_000,
        ?callable $synchronizer = null,
    ) {
        $this->synchronizer = $synchronizer ? $synchronizer(...) : null;
    }

    /**
     * @throws FileLockException
     */
    public function next(string $type, int $machineId, int $timestamp): int
    {
        $key = $this->key($type, $machineId);

        if ($this->synchronizer !== null) {
            try {
                $sequence = ($this->synchronizer)(
                    $key,
                    fn(): int => $this->nextFromCacheState($key, $timestamp),
                );
            } catch (FileLockException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw new FileLockException(
                    'Failed to read/write sequence state from PSR cache for key: ' . $key,
                    0,
                    $exception,
                );
            }

            if (!is_int($sequence) || $sequence < 1) {
                throw new FileLockException('Sequence synchronizer must return a positive integer');
            }

            return $sequence;
        }

        $lock = $this->acquireLock($key);

        try {
            return $this->nextFromCacheState($key, $timestamp);
        } catch (Throwable $exception) {
            throw new FileLockException(
                'Failed to read/write sequence state from PSR cache for key: ' . $key,
                0,
                $exception,
            );
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return resource
     * @throws FileLockException
     */
    private function acquireLock(string $key)
    {
        $lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uid-cache-lock-' . md5($key) . '.lck';

        return FileLock::acquire(
            $lockFile,
            $this->waitTime,
            $this->maxAttempts,
            'Unable to open sequence cache lock file: ' . $lockFile,
            'Unable to acquire sequence cache lock for key: ' . $key,
        );
    }

    private function key(string $type, int $machineId): string
    {
        return $this->prefix . $type . ':' . $machineId;
    }

    private function nextFromCacheState(string $key, int $timestamp): int
    {
        $state = $this->cache->get($key);
        $sequence = 1;
        if (
            is_array($state)
            && ($state['timestamp'] ?? null) === $timestamp
            && isset($state['sequence'])
            && is_int($state['sequence'])
        ) {
            $sequence = $state['sequence'] + 1;
        }

        $this->cache->set($key, ['timestamp' => $timestamp, 'sequence' => $sequence]);

        return $sequence;
    }
}
