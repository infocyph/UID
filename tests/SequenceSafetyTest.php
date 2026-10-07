<?php

declare(strict_types=1);

use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Sequence\FilesystemSequenceProvider;
use Infocyph\UID\Support\FileLock;

test('lock identity verification refreshes cached pathname metadata after external replacement', function (): void {
    expect(function_exists('pcntl_fork'))->toBeTrue();
    $directory = sys_get_temp_dir() . '/uid-lock-race-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700);
    $path = $directory . '/state';
    $target = $directory . '/target';
    file_put_contents($path, '100,1');
    file_put_contents($target, 'unchanged');
    $handle = fopen($path, 'r+b');
    $before = null;
    $verify = Closure::bind(
        static function () use ($path, $handle, &$before) {
            return FileLock::verifyHandle($path, $handle, $before, 'replaced lock', function_exists('posix_geteuid') ? posix_geteuid() : null);
        },
        null,
        FileLock::class,
    );
    expect($verify)->toBeInstanceOf(Closure::class);
    // Prime PHP's path cache after loading the verifier and assertion machinery.
    $before = lstat($path);
    $pid = pcntl_fork();
    if ($pid < 0) {
        throw new RuntimeException('Unable to fork pathname replacement fixture');
    }
    if ($pid === 0) {
        rename($path, $path . '.original');
        symlink($target, $path);
        exit(0);
    }
    pcntl_waitpid($pid, $status);

    try {
        $failure = null;
        try {
            $verify();
        } catch (Throwable $exception) {
            $failure = $exception;
        }
        expect($failure)->toBeInstanceOf(FileLockException::class)
            ->and(file_get_contents($target))->toBe('unchanged');
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }
        unlink($path);
        unlink($path . '.original');
        unlink($target);
        rmdir($directory);
    }
});

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
        if (is_link($link)) {
            unlink($link);
        }
        if (is_file($target)) {
            unlink($target);
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
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
        if (is_file($state)) {
            unlink($state);
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

test('filesystem sequence rejects noncanonical and overflowing state without rewriting it', function (string $contents): void {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uid-state-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700);
    $state = $directory . DIRECTORY_SEPARATOR . 'uid-test-1.seq';
    file_put_contents($state, $contents);

    try {
        $provider = new FilesystemSequenceProvider($directory);
        expect(fn(): int => $provider->next('test', 1, PHP_INT_MAX))->toThrow(FileLockException::class)
            ->and(file_get_contents($state))->toBe($contents);
    } finally {
        unlink($state);
        rmdir($directory);
    }
})->with([
    'timestamp overflow' => '9223372036854775808,0',
    'allocation overflow' => '0,9223372036854775808',
    'timestamp leading zero' => '01,1',
    'allocation leading zero' => '1,01',
    'signed allocation' => '1,+1',
    'trailing newline' => "1,1\n",
    'extra field' => '1,1,1',
    'empty field' => '1,',
]);
