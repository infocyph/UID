<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use ErrorException;
use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Runtime\GenerationContext;

final class FileLock
{
    private const int DEFAULT_TIMEOUT_MICROS = 1_000_000;

    /**
     * @return resource
     * @throws FileLockException
     */
    public static function acquire(
        string $path,
        ?int $timeoutMicros,
        string $openErrorMessage,
        string $lockErrorMessage,
        ?GenerationContext $runtime = null,
    ) {
        $handle = self::openVerified($path, $openErrorMessage);
        $timeout = $timeoutMicros;
        if ($timeout === null) {
            $timeout = $runtime === null
                ? self::DEFAULT_TIMEOUT_MICROS
                : $runtime->waitTimeoutMicros;
        }
        $deadline = hrtime(true) + ($timeout * 1_000);
        $runtimeDeadline = $runtime?->runwire?->deadlineNanoseconds();
        if ($runtimeDeadline !== null) {
            $deadline = min($deadline, $runtimeDeadline);
        }

        try {
            do {
                $wouldBlock = 0;
                if (flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
                    return $handle;
                }
                if ($wouldBlock !== 1) {
                    throw new FileLockException($lockErrorMessage);
                }

                if ($runtime !== null) {
                    $runtime->sleepMicroseconds(1_000);
                } else {
                    usleep(1_000);
                }
            } while (hrtime(true) < $deadline);
        } catch (\Throwable $exception) {
            fclose($handle);
            throw $exception;
        }

        fclose($handle);

        throw new FileLockException($lockErrorMessage);
    }

    /**
     * @return resource
     * @throws FileLockException
     */
    private static function openVerified(string $path, string $errorMessage)
    {
        $before = self::pathMetadata($path);
        if ($before !== false) {
            self::assertSafeMetadata($before, $errorMessage);

            return self::openExisting($path, $before, $errorMessage);
        }

        $handle = self::openStream($path, 'x+b');
        if (!is_resource($handle)) {
            $before = self::pathMetadata($path);
            if ($before === false) {
                throw new FileLockException($errorMessage);
            }

            self::assertSafeMetadata($before, $errorMessage);

            return self::openExisting($path, $before, $errorMessage);
        }

        if (!self::changePermissions($path, 0600)) {
            fclose($handle);
            throw new FileLockException($errorMessage);
        }

        return self::verifyHandle($path, $handle, null, $errorMessage);
    }

    /**
     * @param array<string|int, int> $before
     * @return resource
     */
    private static function openExisting(string $path, array $before, string $errorMessage)
    {
        $handle = self::openStream($path, 'r+b');
        if (!is_resource($handle)) {
            throw new FileLockException($errorMessage);
        }

        return self::verifyHandle($path, $handle, $before, $errorMessage);
    }

    /**
     * @param resource $handle
     * @param array<string|int, int>|null $before
     * @return resource
     */
    private static function verifyHandle(string $path, $handle, ?array $before, string $errorMessage)
    {
        try {
            $after = fstat($handle);
            $pathState = self::pathMetadata($path);
            if ($after === false || $pathState === false) {
                throw new FileLockException($errorMessage);
            }

            self::assertSafeMetadata($after, $errorMessage);
            self::assertSafeMetadata($pathState, $errorMessage);
            self::assertSameFile($after, $pathState, $errorMessage);
            if ($before !== null) {
                self::assertSameFile($before, $after, $errorMessage);
            }

            return $handle;
        } catch (\Throwable $exception) {
            fclose($handle);
            throw $exception;
        }
    }

    /**
     * @param array<string|int, int> $left
     * @param array<string|int, int> $right
     */
    private static function assertSameFile(array $left, array $right, string $errorMessage): void
    {
        if ($left['dev'] !== $right['dev'] || $left['ino'] !== $right['ino']) {
            throw new FileLockException($errorMessage);
        }
    }

    /**
     * @return array<string|int, int>|false
     */
    private static function pathMetadata(string $path): array|false
    {
        try {
            return self::invokeFilesystem(static fn(): array|false => lstat($path));
        } catch (ErrorException) {
            return false;
        }
    }

    /**
     * @return resource|false
     */
    private static function openStream(string $path, string $mode)
    {
        try {
            return self::invokeFilesystem(static fn() => fopen($path, $mode));
        } catch (ErrorException) {
            return false;
        }
    }

    private static function changePermissions(string $path, int $permissions): bool
    {
        try {
            return self::invokeFilesystem(static fn(): bool => chmod($path, $permissions));
        } catch (ErrorException) {
            return false;
        }
    }

    /**
     * @template T
     * @param callable():T $operation
     * @return T
     * @throws ErrorException
     */
    private static function invokeFilesystem(callable $operation): mixed
    {
        set_error_handler(
            static function (int $severity, string $message, string $file, int $line): never {
                throw new ErrorException($message, 0, $severity, $file, $line);
            },
        );

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param array<string|int, int> $metadata
     * @throws FileLockException
     */
    private static function assertSafeMetadata(array $metadata, string $errorMessage): void
    {
        $mode = $metadata['mode'];
        if (($mode & 0170000) !== 0100000) {
            throw new FileLockException($errorMessage);
        }

        if (function_exists('posix_geteuid') && $metadata['uid'] !== posix_geteuid()) {
            throw new FileLockException($errorMessage);
        }
    }
}
