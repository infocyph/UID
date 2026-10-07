<?php

declare(strict_types=1);

use Infocyph\Runwire\Coroutine\CoroutineRuntime;
use Infocyph\Runwire\Coroutine\CoroutineScope;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\RuntimeCapabilities;
use Infocyph\Runwire\Runtime\Enum\RuntimeDriver;
use Infocyph\Runwire\RuntimeContext;
use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\Runtime\GenerationContext;
use Infocyph\UID\Runtime\RunwireBinding;
use Infocyph\UID\Sequence\InMemorySequenceProvider;
use Infocyph\UID\Snowflake;

$output = $argv[1] ?? null;

if (!is_string($output) || $output === '') {
    fwrite(STDERR, "Usage: php RunwireProfile.php OUTPUT\n");
    exit(2);
}

/**
 * @return array{operations:int,elapsed_seconds:float,operations_per_second:float}
 */
function uidMeasureBatch(callable $operation, int $batches = 100, int $idsPerBatch = 100): array
{
    $started = hrtime(true);

    for ($batch = 0; $batch < $batches; ++$batch) {
        $operation($idsPerBatch);
    }

    $elapsed = (hrtime(true) - $started) / 1_000_000_000;
    $operations = $batches * $idsPerBatch;

    return [
        'operations' => $operations,
        'elapsed_seconds' => round($elapsed, 6),
        'operations_per_second' => round($operations / max($elapsed, 0.000001), 3),
    ];
}

$unboundProvider = new InMemorySequenceProvider();
$unboundConfig = new SnowflakeConfig(sequenceProvider: $unboundProvider);
$unbound = uidMeasureBatch(
    static function (int $count) use ($unboundConfig): void {
        for ($index = 0; $index < $count; ++$index) {
            Snowflake::generateWithConfig($unboundConfig);
        }
    },
);

$capabilities = new RuntimeCapabilities(
    driver: RuntimeDriver::NATIVE,
    runwireLoopAvailable: true,
    supportsRunwireCoroutines: true,
);
$host = RuntimeContext::fromCapabilities($capabilities, 'uid-release-profile', concurrent: true);
$coroutines = new CoroutineRuntime();
$boundProvider = new InMemorySequenceProvider();

$bound = uidMeasureBatch(
    static function (int $count) use ($host, $coroutines, $boundProvider): void {
        $request = RequestContext::create($host);

        $coroutines->runRequest(
            $request,
            static function (CoroutineScope $scope) use ($host, $request, $boundProvider, $count): void {
                $config = new SnowflakeConfig(
                    sequenceProvider: $boundProvider,
                    runtime: new GenerationContext(
                        runwire: new RunwireBinding($host, $request, $scope),
                    ),
                );

                for ($index = 0; $index < $count; ++$index) {
                    Snowflake::generateWithConfig($config);
                }
            },
        );

        $request->complete();
    },
);

file_put_contents(
    $output,
    json_encode([
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'unbound' => $unbound,
        'runwire_bound' => $bound,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
);
