<?php

declare(strict_types=1);

use Infocyph\UID\Sequence\FilesystemSequenceProvider;
use Infocyph\UID\Configuration\RandflakeConfig;
use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Configuration\SonyflakeConfig;
use Infocyph\UID\Configuration\TBSLConfig;
use Infocyph\UID\Randflake;
use Infocyph\UID\Snowflake;
use Infocyph\UID\Sonyflake;
use Infocyph\UID\TBSL;

test('filesystem sequences support namespaces and atomic local reservations', function () {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uid-v5-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700);

    try {
        $provider = new FilesystemSequenceProvider(
            baseDirectory: $directory,
            namespace: 'billing',
            reservationSize: 4,
        );

        expect([
            $provider->next('snowflake', 7, 100),
            $provider->next('snowflake', 7, 100),
            $provider->next('snowflake', 7, 100),
            $provider->next('snowflake', 7, 100),
        ])->toBe([1, 2, 3, 4]);

        $stateFile = $directory . DIRECTORY_SEPARATOR . 'uid-billing-snowflake-7.seq';
        expect(file_get_contents($stateFile))->toBe('100,4')
            ->and((new FilesystemSequenceProvider($directory, 'billing', reservationSize: 4))
                ->next('snowflake', 7, 100))->toBe(5);
    } finally {
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
});

test('filesystem sequences fail closed for oversized and malformed state', function () {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uid-v5-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700);
    $stateFile = $directory . DIRECTORY_SEPARATOR . 'uid-test-1.seq';

    try {
        file_put_contents($stateFile, str_repeat('1', 65));
        $provider = new FilesystemSequenceProvider($directory);
        expect(fn () => $provider->next('test', 1, 100))
            ->toThrow(\Infocyph\UID\Exceptions\FileLockException::class);

        file_put_contents($stateFile, '100,01');
        expect(fn () => $provider->next('test', 1, 100))
            ->toThrow(\Infocyph\UID\Exceptions\FileLockException::class);
    } finally {
        if (file_exists($stateFile)) {
            unlink($stateFile);
        }
        rmdir($directory);
    }
});

test('filesystem reservation ranges never overlap across processes', function () {
    if (!function_exists('pcntl_fork') || !function_exists('pcntl_exec')) {
        $this->markTestSkipped('The pcntl extension is required for multi-process coverage');
    }

    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uid-v5-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700);
    $children = [];

    try {
        for ($process = 0; $process < 4; ++$process) {
            $output = $directory . DIRECTORY_SEPARATOR . 'child-' . $process . '.json';
            $pid = pcntl_fork();
            expect($pid)->toBeGreaterThanOrEqual(0);
            if ($pid === 0) {
                $provider = new FilesystemSequenceProvider($directory, 'shared', reservationSize: 16);
                $allocations = [];
                for ($index = 0; $index < 100; ++$index) {
                    $allocations[] = $provider->next('sequence', 1, 123456);
                }

                file_put_contents($output, json_encode($allocations, JSON_THROW_ON_ERROR));
                pcntl_exec(PHP_BINARY, ['-r', '']);
                throw new RuntimeException('Unable to terminate fork child');
            }

            $children[$pid] = $output;
        }

        $allocations = [];
        foreach ($children as $pid => $output) {
            pcntl_waitpid($pid, $status);
            $json = file_get_contents($output);
            expect($json)->toBeString();
            $allocations = [...$allocations, ...json_decode($json, true, 512, JSON_THROW_ON_ERROR)];
        }

        expect($allocations)->toHaveCount(400)
            ->and(array_unique($allocations))->toHaveCount(400);
    } finally {
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
});

test('coordinated generators remain unique across processes', function (string $algorithm) {
    if (!function_exists('pcntl_fork') || !function_exists('pcntl_exec')) {
        $this->markTestSkipped('The pcntl extension is required for multi-process coverage');
    }

    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'uid-v5-' . bin2hex(random_bytes(6));
    mkdir($directory, 0700);
    $children = [];

    try {
        for ($process = 0; $process < 4; ++$process) {
            $output = $directory . DIRECTORY_SEPARATOR . 'child-' . $process . '.json';
            $pid = pcntl_fork();
            expect($pid)->toBeGreaterThanOrEqual(0);
            if ($pid === 0) {
                $provider = new FilesystemSequenceProvider($directory, $algorithm, reservationSize: 8);
                $ids = [];
                for ($index = 0; $index < 100; ++$index) {
                    $ids[] = match ($algorithm) {
                        'snowflake' => Snowflake::generateWithConfig(new SnowflakeConfig(sequenceProvider: $provider)),
                        'sonyflake' => Sonyflake::generateWithConfig(new SonyflakeConfig(sequenceProvider: $provider)),
                        'randflake' => Randflake::generateWithConfig(new RandflakeConfig(
                            nodeId: 1,
                            leaseStart: time() - 60,
                            leaseEnd: time() + 3600,
                            secret: '0123456789abcdef',
                            sequenceProvider: $provider,
                        )),
                        'tbsl' => TBSL::generateWithConfig(new TBSLConfig(sequenceProvider: $provider)),
                    };
                }

                file_put_contents($output, json_encode($ids, JSON_THROW_ON_ERROR));
                pcntl_exec(PHP_BINARY, ['-r', '']);
                throw new RuntimeException('Unable to terminate fork child');
            }

            $children[$pid] = $output;
        }

        $ids = [];
        foreach ($children as $pid => $output) {
            pcntl_waitpid($pid, $status);
            $json = file_get_contents($output);
            expect($json)->toBeString();
            $ids = [...$ids, ...json_decode($json, true, 512, JSON_THROW_ON_ERROR)];
        }

        expect($ids)->toHaveCount(400)
            ->and(array_unique($ids))->toHaveCount(400);
    } finally {
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
})->with(['snowflake', 'sonyflake', 'randflake', 'tbsl']);
