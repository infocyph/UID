<?php

declare(strict_types=1);

use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Sequence\InMemorySequenceProvider;
use Infocyph\UID\Sequence\PsrSimpleCacheSequenceProvider;
use Psr\SimpleCache\CacheInterface;

final class DomainLimitCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        unset($ttl);
        $this->values[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }
}

test('in-memory sequence state fails closed at the live-domain bound', function (): void {
    $provider = new InMemorySequenceProvider();
    for ($domain = 0; $domain < 1024; ++$domain) {
        expect($provider->next('domain-' . $domain, 0, 1))->toBe(1);
    }

    expect(fn(): int => $provider->next('domain-overflow', 0, 1))
        ->toThrow(FileLockException::class, 'domain limit exceeded');
});

test('PSR-16 observed safety state fails closed at the live-domain bound', function (): void {
    $provider = new PsrSimpleCacheSequenceProvider(new DomainLimitCache());
    for ($domain = 0; $domain < 1024; ++$domain) {
        expect($provider->next('d' . $domain, 0, 1))->toBe(1);
    }

    expect(fn(): int => $provider->next('overflow', 0, 1))
        ->toThrow(FileLockException::class, 'domain limit exceeded');
});
