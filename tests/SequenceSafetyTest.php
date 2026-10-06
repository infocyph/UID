<?php

declare(strict_types=1);

use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Sequence\FilesystemSequenceProvider;

test('filesystem sequence rejects symlink state without touching its target', function (): void {
    if (PHP_OS_FAMILY === 'Windows') {
        expect(true)->toBeTrue();

        return;
    }

    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uid-safety-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700);
    $target = $directory . DIRECTORY_SEPARATOR . 'target';
    $link = $directory . DIRECTORY_SEPARATOR . 'uid-test-1.seq';
    file_put_contents($target, 'unchanged');
    symlink($target, $link);

    try {
        $provider = new FilesystemSequenceProvider($directory);
        expect(fn(): int => $provider->next('test', 1, 100))->toThrow(FileLockException::class)
            ->and(file_get_contents($target))->toBe('unchanged');
    } finally {
        @unlink($link);
        @unlink($target);
        @rmdir($directory);
    }
});

test('filesystem sequence fails closed at integer exhaustion', function (): void {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uid-safety-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700);
    $state = $directory . DIRECTORY_SEPARATOR . 'uid-test-1.seq';
    file_put_contents($state, '100,' . PHP_INT_MAX);

    try {
        $provider = new FilesystemSequenceProvider($directory);
        expect(fn(): int => $provider->next('test', 1, 100))->toThrow(FileLockException::class)
            ->and(file_get_contents($state))->toBe('100,' . PHP_INT_MAX);
    } finally {
        @unlink($state);
        @rmdir($directory);
    }
});
