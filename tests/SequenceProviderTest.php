<?php

declare(strict_types=1);

use Infocyph\UID\Sequence\FilesystemSequenceProvider;
use Infocyph\UID\Sequence\InMemorySequenceProvider;
use Infocyph\UID\Sequence\PsrSimpleCacheSequenceProvider;
use Infocyph\UID\Sequence\SequenceProviderInterface;
use Infocyph\UID\Snowflake;
use Psr\SimpleCache\CacheInterface;

final class SequenceTestCache implements CacheInterface
{
    public bool $failWrites = false;

    public null|int|DateInterval $lastTtl = null;

    /** @var array<string, mixed> */
    private array $store = [];

    public function clear(): bool
    {
        $this->store = [];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    public function has(string $key): bool
    {
        return isset($this->store[$key]);
    }

    public function seed(string $key, mixed $value): void
    {
        $this->store[$key] = $value;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->lastTtl = $ttl;
        if ($this->failWrites) {
            return false;
        }

        $this->store[$key] = $value;

        return true;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set((string) $key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }
}

final class FutureOnceSequenceProvider implements SequenceProviderInterface
{
    public int $calls = 0;

    public function next(string $type, int $machineId, int $timestamp): int
    {
        unset($type, $machineId);
        ++$this->calls;

        if ($this->calls === 1) {
            throw new \Infocyph\UID\Exceptions\SequenceTimestampException($timestamp + 1, $timestamp);
        }

        return 1;
    }
}

beforeEach(function () {
    Snowflake::resetSequenceProvider();
});

afterEach(function () {
    Snowflake::resetSequenceProvider();
});

test('in-memory sequence provider works', function () {
    Snowflake::useInMemorySequenceProvider();

    $id1 = Snowflake::generate(1, 1);
    $id2 = Snowflake::generate(1, 1);

    expect((int)$id2)->toBeGreaterThan((int)$id1);
});

test('in-memory sequence provider rejects a timestamp rollback', function () {
    $provider = new InMemorySequenceProvider();
    $provider->next('audit', 1, 2);

    expect(fn () => $provider->next('audit', 1, 1))
        ->toThrow(\Infocyph\UID\Exceptions\SequenceTimestampException::class);
});

test('Snowflake retries a timestamp sampled before another writer advances state', function () {
    $provider = new FutureOnceSequenceProvider();
    Snowflake::setSequenceProvider($provider);

    expect(Snowflake::isValid(Snowflake::generate()))->toBeTrue()
        ->and($provider->calls)->toBe(2);
});

test('custom callback sequence provider works', function () {
    $counter = 0;
    Snowflake::useSequenceCallback(function () use (&$counter): int {
        $counter++;
        return $counter;
    });

    $id1 = Snowflake::generate(0, 0);
    $id2 = Snowflake::generate(0, 0);

    expect($id1)->not()->toBe($id2);
});

test('psr-16 sequence provider works', function () {
    $cache = new SequenceTestCache();

    Snowflake::setSequenceProvider(new PsrSimpleCacheSequenceProvider($cache));

    $id1 = Snowflake::generate(2, 3);
    $id2 = Snowflake::generate(2, 3);

    expect((int)$id2)->toBeGreaterThan((int)$id1);
});

test('psr-16 sequence provider supports custom synchronizer callback', function () {
    $cache = new SequenceTestCache();

    $synchronizerCalls = 0;
    $synchronizer = function (string $key, callable $criticalSection) use (&$synchronizerCalls): int {
        expect($key)->toContain('uid.seq.');
        $synchronizerCalls++;

        return $criticalSection();
    };

    Snowflake::setSequenceProvider(new PsrSimpleCacheSequenceProvider($cache, synchronizer: $synchronizer));

    $id1 = Snowflake::generate(2, 3);
    $id2 = Snowflake::generate(2, 3);

    expect((int) $id2)->toBeGreaterThan((int) $id1)
        ->and($synchronizerCalls)->toBe(2);
});

test('psr-16 sequence provider fails safely when state cannot be persisted', function () {
    $cache = new SequenceTestCache();
    $cache->failWrites = true;
    $provider = new PsrSimpleCacheSequenceProvider($cache);

    expect(fn() => $provider->next('snowflake', 1, 1))
        ->toThrow(\Infocyph\UID\Exceptions\FileLockException::class);
});

test('psr-16 sequence provider rejects malformed and future state', function () {
    $cache = new SequenceTestCache();
    $provider = new PsrSimpleCacheSequenceProvider($cache);
    $cache->seed('uid.seq.snowflake.1', 'invalid');

    expect(fn() => $provider->next('snowflake', 1, 1))
        ->toThrow(\Infocyph\UID\Exceptions\FileLockException::class);

    $cache->seed('uid.seq.snowflake.1', ['timestamp' => 2, 'sequence' => 1]);

    expect(fn () => $provider->next('snowflake', 1, 1))
        ->toThrow(\Infocyph\UID\Exceptions\SequenceTimestampException::class);
});

test('filesystem sequence provider rejects unsafe keys and corrupted state', function () {
    $type = 'audit' . bin2hex(random_bytes(4));
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "uid-$type-1.seq";
    file_put_contents($path, 'corrupted');
    $provider = new FilesystemSequenceProvider();

    try {
        expect(fn () => $provider->next('../escape', 1, 1))
            ->toThrow(\InvalidArgumentException::class)
            ->and(fn () => $provider->next($type, 1, 1))
            ->toThrow(\Infocyph\UID\Exceptions\FileLockException::class);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});
