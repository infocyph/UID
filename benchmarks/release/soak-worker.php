<?php

declare(strict_types=1);

use Infocyph\Runwire\Coroutine\CoroutineRuntime;
use Infocyph\Runwire\Coroutine\CoroutineScope;
use Infocyph\Runwire\Exception\CancelledException;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\RuntimeCapabilities;
use Infocyph\Runwire\Runtime\Enum\CancellationReason;
use Infocyph\Runwire\Runtime\Enum\RuntimeDriver;
use Infocyph\Runwire\RuntimeContext;
use Infocyph\UID\CUID2;
use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Runtime\GenerationContext;
use Infocyph\UID\Runtime\RunwireBinding;
use Infocyph\UID\Sequence\InMemorySequenceProvider;
use Infocyph\UID\Snowflake;
use Infocyph\UID\UUID;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$resultPath = getenv('UID_SOAK_RESULT');

if (!is_string($resultPath) || $resultPath === '') {
    fwrite(STDERR, "UID_SOAK_RESULT is required.\n");
    exit(2);
}

if (!function_exists('pcntl_signal') || !function_exists('pcntl_async_signals')) {
    fwrite(STDERR, "The pcntl extension is required.\n");
    exit(2);
}

$running = true;
$iterations = 0;
$errors = 0;
$duplicates = 0;
$cancellationChecks = 0;
$recentIds = [];
$recentSlots = [];
$recentLimit = 10_000;
$recentCursor = 0;
$provider = new InMemorySequenceProvider();
$capabilities = new RuntimeCapabilities(
    driver: RuntimeDriver::NATIVE,
    runwireLoopAvailable: true,
    supportsRunwireCoroutines: true,
);
$host = RuntimeContext::fromCapabilities($capabilities, 'uid-release-soak', concurrent: true);
$coroutines = new CoroutineRuntime();

pcntl_async_signals(true);
pcntl_signal(SIGTERM, static function () use (&$running): void {
    $running = false;
});
pcntl_signal(SIGINT, static function () use (&$running): void {
    $running = false;
});

$remember = static function (string $id) use (
    &$recentIds,
    &$recentSlots,
    &$recentCursor,
    &$duplicates,
    $recentLimit,
): void {
    if (isset($recentIds[$id])) {
        ++$duplicates;

        return;
    }

    $slot = $recentCursor % $recentLimit;
    $previous = $recentSlots[$slot] ?? null;
    if (is_string($previous)) {
        unset($recentIds[$previous]);
    }

    $recentSlots[$slot] = $id;
    $recentIds[$id] = true;
    ++$recentCursor;
};

while ($running) {
    try {
        $domain = $iterations % 512;
        $config = new SnowflakeConfig(
            datacenterId: intdiv($domain, 32),
            workerId: $domain % 32,
            sequenceProvider: $provider,
        );
        $remember(Snowflake::generateWithConfig($config));

        if (($iterations % 10) === 0) {
            $remember(CUID2::generate());
            $remember(UUID::v7());
        }

        if (($iterations % 100) === 0) {
            $request = RequestContext::create($host);
            $coroutines->runRequest(
                $request,
                static function (CoroutineScope $scope) use ($host, $request, $provider, $remember): void {
                    $bound = new SnowflakeConfig(
                        datacenterId: 31,
                        workerId: 31,
                        sequenceProvider: $provider,
                        runtime: new GenerationContext(
                            runwire: new RunwireBinding($host, $request, $scope),
                        ),
                    );

                    for ($index = 0; $index < 5; ++$index) {
                        $remember(Snowflake::generateWithConfig($bound));
                    }
                },
            );
            $request->complete();

            $cancelledRequest = RequestContext::create($host);
            $cancelledRequest->cancel(CancellationReason::HOST_CANCELLED);
            ++$cancellationChecks;

            try {
                Snowflake::generateWithConfig(new SnowflakeConfig(
                    sequenceProvider: $provider,
                    runtime: new GenerationContext(
                        runwire: new RunwireBinding($host, $cancelledRequest),
                    ),
                ));
                ++$errors;
            } catch (CancelledException) {
            }
        }
    } catch (Throwable) {
        ++$errors;
    }

    ++$iterations;
    usleep(500);
}

file_put_contents(
    $resultPath,
    json_encode([
        'status' => $errors === 0 && $duplicates === 0 ? 'passed' : 'failed',
        'iterations' => $iterations,
        'errors' => $errors,
        'duplicate_ids' => $duplicates,
        'cancellation_checks' => $cancellationChecks,
        'recent_id_window' => count($recentIds),
        'memory_peak_mb' => round(memory_get_peak_usage(true) / 1_048_576, 5),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
);
