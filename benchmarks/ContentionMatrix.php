<?php

declare(strict_types=1);

namespace Infocyph\UID\Benchmarks;

use Infocyph\UID\Sequence\FilesystemSequenceProvider;

final class ContentionMatrix
{
    /**
     * @param list<int> $processCounts
     * @param list<int> $reservationSizes
     */
    public static function run(
        array $processCounts = [1, 2, 4, 8, 16],
        array $reservationSizes = [1, 4, 8, 16, 32, 64],
        int $idsPerProcess = 200,
    ): void {
        self::assertPcntl();
        fwrite(STDOUT, "processes,reservation,ids_per_second,median_us,p95_us,p99_us,duplicates,lock_errors,cpu_ms\n");

        foreach ($processCounts as $processes) {
            foreach ($reservationSizes as $reservationSize) {
                fwrite(STDOUT, implode(',', self::measure($processes, $reservationSize, $idsPerProcess)) . "\n");
            }
        }
    }

    private static function assertPcntl(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_exec')) {
            throw new \RuntimeException('The pcntl extension is required for contention benchmarks');
        }
    }

    /**
     * @param array<int, string> $children
     * @return array{list<int>,list<float>,int,int}
     */
    private static function collect(array $children): array
    {
        $ids = [];
        $latencies = [];
        $errors = 0;
        $cpuMicros = 0;

        foreach ($children as $pid => $output) {
            pcntl_waitpid($pid, $status);
            $json = file_get_contents($output);
            if (!is_string($json)) {
                ++$errors;

                continue;
            }

            $result = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $ids = [...$ids, ...$result['ids']];
            $latencies = [...$latencies, ...$result['latencies']];
            $errors += $result['errors'];
            $cpuMicros += $result['cpu_micros'];
        }

        return [$ids, $latencies, $errors, $cpuMicros];
    }

    private static function cpuMicros(array $usage): int
    {
        return (($usage['ru_utime.tv_sec'] + $usage['ru_stime.tv_sec']) * 1_000_000)
            + $usage['ru_utime.tv_usec']
            + $usage['ru_stime.tv_usec'];
    }

    /**
     * @return array{int,int,string,string,string,string,int,int,string}
     */
    private static function measure(int $processes, int $reservationSize, int $idsPerProcess): array
    {
        $directory = sys_get_temp_dir() . '/uid-contention-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $children = [];
        $started = hrtime(true);

        try {
            for ($process = 0; $process < $processes; ++$process) {
                $output = $directory . '/child-' . $process . '.json';
                $pid = pcntl_fork();
                if ($pid === -1) {
                    throw new \RuntimeException('Unable to fork benchmark process');
                }

                if ($pid === 0) {
                    self::runChild($directory, $output, $reservationSize, $idsPerProcess);
                }

                $children[$pid] = $output;
            }

            [$ids, $latencies, $errors, $cpuMicros] = self::collect($children);
            $elapsedSeconds = (hrtime(true) - $started) / 1_000_000_000;
            sort($latencies, SORT_NUMERIC);

            return [
                $processes,
                $reservationSize,
                number_format(count($ids) / $elapsedSeconds, 0, '.', ''),
                number_format(self::percentile($latencies, 0.50), 2, '.', ''),
                number_format(self::percentile($latencies, 0.95), 2, '.', ''),
                number_format(self::percentile($latencies, 0.99), 2, '.', ''),
                count($ids) - count(array_unique($ids)),
                $errors,
                number_format($cpuMicros / 1000, 2, '.', ''),
            ];
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    private static function percentile(array $sorted, float $percentile): float
    {
        if ($sorted === []) {
            return 0.0;
        }

        $index = (int) ceil(count($sorted) * $percentile) - 1;

        return $sorted[max(0, $index)];
    }

    private static function runChild(
        string $directory,
        string $output,
        int $reservationSize,
        int $idsPerProcess,
    ): never {
        $provider = new FilesystemSequenceProvider($directory, 'matrix', reservationSize: $reservationSize);
        $usageBefore = getrusage();
        $ids = [];
        $latencies = [];
        $errors = 0;

        for ($index = 0; $index < $idsPerProcess; ++$index) {
            $started = hrtime(true);

            try {
                $ids[] = $provider->next('sequence', 1, 123456789);
            } catch (\Throwable) {
                ++$errors;
            }
            $latencies[] = (hrtime(true) - $started) / 1000;
        }

        $usageAfter = getrusage();
        file_put_contents($output, json_encode([
            'ids' => $ids,
            'latencies' => $latencies,
            'errors' => $errors,
            'cpu_micros' => self::cpuMicros($usageAfter) - self::cpuMicros($usageBefore),
        ], JSON_THROW_ON_ERROR));
        pcntl_exec(PHP_BINARY, ['-r', '']);

        throw new \RuntimeException('Unable to terminate benchmark child');
    }
}
