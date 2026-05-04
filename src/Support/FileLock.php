<?php

declare(strict_types=1);

namespace Infocyph\UID\Support;

use Infocyph\UID\Exceptions\FileLockException;

final class FileLock
{
    /**
     * @return resource
     * @throws FileLockException
     */
    public static function acquire(
        string $path,
        int $waitTime,
        int $maxAttempts,
        string $openErrorMessage,
        string $lockErrorMessage,
    ) {
        ($handle = fopen($path, 'c+')) || throw new FileLockException($openErrorMessage);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return $handle;
            }

            usleep($waitTime);
        }

        fclose($handle);

        throw new FileLockException($lockErrorMessage);
    }
}
