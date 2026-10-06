<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use Infocyph\UID\Exceptions\FileLockException;
use Infocyph\UID\Runtime\GenerationContext;

final class FileLock
{
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

        if ($timeoutMicros === null && $runtime === null) {
            if (flock($handle, LOCK_EX)) {
                return $handle;
            }

            fclose($handle);

            throw new FileLockException($lockErrorMessage);
        }

        $effectiveTimeout = $timeoutMicros ?? $runtime?->waitTimeoutMicros ?? 1_000_000;
        $deadline = hrtime(true) + ($effectiveTimeout * 1000);
        $runtimeDeadline = $runtime?->runwire?->deadlineNanoseconds();
        if ($runtimeDeadline !== null) {
            $deadline = min($deadline, $runtimeDeadline);
        }

        do {
            $wouldBlock = 0;
            if (flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
                return $handle;
            }

            if ($wouldBlock !== 1) {
                fclose($handle);

                throw new FileLockException($lockErrorMessage);
            }

            if ($runtime !== null) {
                $runtime->sleepMicroseconds(1_000);
            } else {
                usleep(1_000);
            }
        } while (hrtime(true) < $deadline);

        fclose($handle);

        throw new FileLockException($lockErrorMessage);
    }

    /**
     * @return resource
     * @throws FileLockException
     */
    private static function openVerified(string $path, string $errorMessage)
    {
        $before = @lstat($path);
        $created = false;
        if ($before === false) {
            $handle = @fopen($path, 'x+b');
            if (is_resource($handle)) {
                $created = true;
                @chmod($path, 0600);
            } else {
                $before = @lstat($path);
                $handle = $before === false ? false : @fopen($path, 'r+b');
            }
        } else {
            self::assertSafeMetadata($before, $errorMessage);
            $handle = @fopen($path, 'r+b');
        }

        if (!is_resource($handle)) {
            throw new FileLockException($errorMessage);
        }

        try {
            $after = fstat($handle);
            $pathState = @lstat($path);
            if ($after === false || $pathState === false) {
                throw new FileLockException($errorMessage);
            }

            self::assertSafeMetadata($after, $errorMessage);
            self::assertSafeMetadata($pathState, $errorMessage);
            if (
                isset($after['dev'], $after['ino'], $pathState['dev'], $pathState['ino'])
                && ($after['dev'] !== $pathState['dev'] || $after['ino'] !== $pathState['ino'])
            ) {
                throw new FileLockException($errorMessage);
            }

            if (!$created && $before !== false && isset($before['dev'], $before['ino'], $after['dev'], $after['ino'])) {
                if ($before['dev'] !== $after['dev'] || $before['ino'] !== $after['ino']) {
                    throw new FileLockException($errorMessage);
                }
            }

            return $handle;
        } catch (\Throwable $exception) {
            fclose($handle);
            throw $exception;
        }
    }

    /**
     * @param array<string|int, mixed> $metadata
     * @throws FileLockException
     */
    private static function assertSafeMetadata(array $metadata, string $errorMessage): void
    {
        $mode = $metadata['mode'] ?? null;
        if (!is_int($mode) || ($mode & 0170000) !== 0100000) {
            throw new FileLockException($errorMessage);
        }

        if (function_exists('posix_geteuid')) {
            $uid = $metadata['uid'] ?? null;
            if (!is_int($uid) || $uid !== posix_geteuid()) {
                throw new FileLockException($errorMessage);
            }
        }
    }
}
