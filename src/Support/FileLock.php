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
        $timeout = $timeoutMicros ?? self::runtimeTimeout($runtime);
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
     * @param array<string|int, int> $metadata
     * @throws FileLockException
     */
    private static function assertSafeMetadata(array $metadata, string $errorMessage): void
    {
        if (($metadata['mode'] & 0170000) !== 0100000) {
            throw new FileLockException($errorMessage);
        }

        if (function_exists('posix_geteuid') && $metadata['uid'] !== posix_geteuid()) {
            throw new FileLockException($errorMessage);
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
     * @return resource
     * @throws FileLockException
     */
    private static function openVerified(string $path, string $errorMessage)
    {
        set_error_handler(
            static function (int $severity, string $message, string $file, int $line): never {
                throw new ErrorException($message, 0, $severity, $file, $line);
            },
        );

        try {
            return self::openVerifiedWithHandler($path, $errorMessage);
        } catch (ErrorException $exception) {
            throw new FileLockException($errorMessage, 0, $exception);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @return resource
     * @throws FileLockException
     * @throws ErrorException
     */
    private static function openVerifiedWithHandler(string $path, string $errorMessage)
    {
        try {
            $before = lstat($path);
        } catch (ErrorException) {
            $before = false;
        }

        if ($before !== false) {
            self::assertSafeMetadata($before, $errorMessage);
            $handle = fopen($path, 'r+b');
            is_resource($handle) || throw new FileLockException($errorMessage);

            return self::verifyHandle($path, $handle, $before, $errorMessage);
        }

        try {
            $handle = fopen($path, 'x+b');
        } catch (ErrorException) {
            $before = lstat($path);
            $before !== false || throw new FileLockException($errorMessage);
            self::assertSafeMetadata($before, $errorMessage);

            $handle = fopen($path, 'r+b');
            is_resource($handle) || throw new FileLockException($errorMessage);

            return self::verifyHandle($path, $handle, $before, $errorMessage);
        }

        is_resource($handle) || throw new FileLockException($errorMessage);
        chmod($path, 0600) || throw new FileLockException($errorMessage);

        return self::verifyHandle($path, $handle, null, $errorMessage);
    }

    private static function runtimeTimeout(?GenerationContext $runtime): int
    {
        return $runtime instanceof GenerationContext
            ? $runtime->waitTimeoutMicros
            : self::DEFAULT_TIMEOUT_MICROS;
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
            $pathState = lstat($path);
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
}
