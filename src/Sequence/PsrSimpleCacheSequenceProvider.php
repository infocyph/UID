<?php

declare(strict_types=1);

namespace Infocyph\UID\Sequence;

use Infocyph\UID\Exceptions\FileLockException;
use Psr\SimpleCache\CacheInterface;
use Throwable;

final readonly class PsrSimpleCacheSequenceProvider implements SequenceProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
        private string $prefix = 'uid:seq:',
        private int $waitTime = 100,
        private int $maxAttempts = 10,
    ) {}

    /**
     * @throws FileLockException
     */
    public function next(string $type, int $machineId, int $timestamp): int
    {
        $key = $this->key($type, $machineId);
        $lock = $this->acquireLock($key);

        try {
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
        ($handle = fopen($lockFile, 'c+')) || throw new FileLockException(
            'Unable to open sequence cache lock file: ' . $lockFile,
        );

        for ($attempt = 0; $attempt < $this->maxAttempts; $attempt++) {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return $handle;
            }

            usleep($this->waitTime);
        }

        fclose($handle);

        throw new FileLockException('Unable to acquire sequence cache lock for key: ' . $key);
    }

    private function key(string $type, int $machineId): string
    {
        return $this->prefix . $type . ':' . $machineId;
    }
}
