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
        ?int $timeoutMicros,
        string $openErrorMessage,
        string $lockErrorMessage,
    ) {
        ($handle = fopen($path, 'c+')) || throw new FileLockException($openErrorMessage);

        if ($timeoutMicros === null) {
            if (flock($handle, LOCK_EX)) {
                return $handle;
            }

            fclose($handle);

            throw new FileLockException($lockErrorMessage);
        }

        $deadline = hrtime(true) + ($timeoutMicros * 1000);
        do {
            $wouldBlock = 0;
            if (flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
                return $handle;
            }

            if ($wouldBlock !== 1) {
                fclose($handle);

                throw new FileLockException($lockErrorMessage);
            }

            usleep(1000);
        } while (hrtime(true) < $deadline);

        fclose($handle);

        throw new FileLockException($lockErrorMessage);
    }
}
