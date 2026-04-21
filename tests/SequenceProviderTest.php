<?php

use Infocyph\UID\Sequence\PsrSimpleCacheSequenceProvider;
use Infocyph\UID\Snowflake;
use Psr\SimpleCache\CacheInterface;

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
    $cache = new class implements CacheInterface {
        /** @var array<string, mixed> */
        private array $store = [];

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->store[$key] ?? $default;
        }

        public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
        {
            unset($ttl);
            $this->store[$key] = $value;
            return true;
        }

        public function delete(string $key): bool
        {
            unset($this->store[$key]);
            return true;
        }

        public function clear(): bool
        {
            $this->store = [];
            return true;
        }

        public function getMultiple(iterable $keys, mixed $default = null): iterable
        {
            $values = [];
            foreach ($keys as $key) {
                $values[$key] = $this->get($key, $default);
            }

            return $values;
        }

        public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
        {
            foreach ($values as $key => $value) {
                $this->set((string)$key, $value, $ttl);
            }

            return true;
        }

        public function deleteMultiple(iterable $keys): bool
        {
            foreach ($keys as $key) {
                $this->delete((string)$key);
            }

            return true;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->store);
        }
    };

    Snowflake::setSequenceProvider(new PsrSimpleCacheSequenceProvider($cache));

    $id1 = Snowflake::generate(2, 3);
    $id2 = Snowflake::generate(2, 3);

    expect((int)$id2)->toBeGreaterThan((int)$id1);
});
