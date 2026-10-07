<?php

declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) {
    return;
}

$options = getopt('', [
    'baseline-url:',
    'candidate-url:',
    'baseline-output:',
    'candidate-output:',
    'route:',
    'concurrency:',
    'duration:',
    'repetitions:',
    'ids-per-response:',
    'warmup:',
]);

$baselineUrl = $options['baseline-url'] ?? null;
$candidateUrl = $options['candidate-url'] ?? null;
$baselineOutput = $options['baseline-output'] ?? null;
$candidateOutput = $options['candidate-output'] ?? null;
$route = $options['route'] ?? null;
$concurrency = filter_var($options['concurrency'] ?? null, FILTER_VALIDATE_INT);
$duration = filter_var($options['duration'] ?? null, FILTER_VALIDATE_INT);
$repetitions = filter_var($options['repetitions'] ?? null, FILTER_VALIDATE_INT);
$idsPerResponse = filter_var($options['ids-per-response'] ?? null, FILTER_VALIDATE_INT);
$warmup = filter_var($options['warmup'] ?? null, FILTER_VALIDATE_INT);

if (
    !is_string($baselineUrl)
    || $baselineUrl === ''
    || !is_string($candidateUrl)
    || $candidateUrl === ''
    || !is_string($baselineOutput)
    || $baselineOutput === ''
    || !is_string($candidateOutput)
    || $candidateOutput === ''
    || !is_string($route)
    || $route === ''
    || !is_int($concurrency)
    || $concurrency < 1
    || !is_int($duration)
    || $duration < 1
    || !is_int($repetitions)
    || $repetitions < 4
    || ($repetitions % 2) !== 0
    || !is_int($idsPerResponse)
    || $idsPerResponse < 1
    || !is_int($warmup)
    || $warmup < 1
) {
    throw new InvalidArgumentException('Invalid paired fixed-duration host benchmark configuration');
}

if (!extension_loaded('curl')) {
    throw new RuntimeException('The curl extension is required for host benchmarking');
}

const UID_MAX_LATENCY_SAMPLES = 200_000;

/**
 * @param list<float> $values
 */
function uidPercentile(array $values, float $percentile): float
{
    if ($values === []) {
        return 0.0;
    }

    sort($values, SORT_NUMERIC);
    $index = max(0, (int) ceil(count($values) * $percentile) - 1);

    return $values[$index];
}

/**
 * @param list<float> $values
 */
function uidAverage(array $values): float
{
    return $values === [] ? 0.0 : array_sum($values) / count($values);
}

function uidCreateHandle(string $url): CurlHandle
{
    $handle = curl_init($url);
    $handle instanceof CurlHandle || throw new RuntimeException('Unable to create benchmark request handle');

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT_MS => 2_000,
        CURLOPT_TIMEOUT_MS => 5_000,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    return $handle;
}

function uidValidId(string $id, string $route): bool
{
    if ($route === '/snowflake-contended') {
        return preg_match('/\A(?:0|[1-9][0-9]{0,18})\z/', $id) === 1
            && (strlen($id) < 19 || strcmp($id, (string) PHP_INT_MAX) <= 0);
    }

    return in_array($route, ['/cuid2-one', '/cuid2-batch'], true)
        && preg_match('/\A[a-z][a-z0-9]{23}\z/', $id) === 1;
}

/**
 * @param array{result:int,handle:CurlHandle} $info
 * @return array{successful:bool,timeout:bool,latency:float,duplicates:int}
 */
function uidInspectCompletion(array $info, int $idsPerResponse): array
{
    $handle = $info['handle'];
    $body = curl_multi_getcontent($handle);
    $httpCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $latency = (float) curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1_000;
    $successful = $info['result'] === CURLE_OK && $httpCode === 200 && is_string($body);
    $decoded = $successful ? json_decode($body, true) : null;
    $ids = is_array($decoded) ? ($decoded['ids'] ?? null) : null;

    if (!is_array($ids) || count($ids) !== $idsPerResponse) {
        $successful = false;
        $ids = [];
    }

    $duplicates = 0;
    $responseIds = [];
    $route = (string) parse_url((string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL), PHP_URL_PATH);

    foreach ($ids as $id) {
        if (!is_string($id) || !uidValidId($id, $route)) {
            $successful = false;

            continue;
        }

        if (isset($responseIds[$id])) {
            ++$duplicates;
        } else {
            $responseIds[$id] = true;
        }
    }

    return [
        'successful' => $successful && $duplicates === 0,
        'timeout' => $info['result'] === CURLE_OPERATION_TIMEDOUT,
        'latency' => $latency,
        'duplicates' => $duplicates,
    ];
}

/**
 * @return array{attempted:int,successful:int,failed:int,timeouts:int,duplicates:int}
 */
function uidRunOperations(
    string $url,
    int $concurrency,
    int $operations,
    int $idsPerResponse,
): array {
    $multi = curl_multi_init();
    $launched = 0;
    $active = 0;
    $attempted = 0;
    $successful = 0;
    $failed = 0;
    $timeouts = 0;
    $duplicates = 0;

    $launch = static function () use ($multi, $url, $operations, &$launched, &$active): void {
        if ($launched >= $operations) {
            return;
        }

        curl_multi_add_handle($multi, uidCreateHandle($url));
        ++$launched;
        ++$active;
    };

    for ($index = 0; $index < min($concurrency, $operations); ++$index) {
        $launch();
    }

    while ($active > 0) {
        do {
            $status = curl_multi_exec($multi, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        $status === CURLM_OK || throw new RuntimeException('Host benchmark warmup execution failed');

        while (($info = curl_multi_info_read($multi)) !== false) {
            $result = uidInspectCompletion($info, $idsPerResponse);
            ++$attempted;

            if ($result['successful']) {
                ++$successful;
            } else {
                ++$failed;
            }

            if ($result['timeout']) {
                ++$timeouts;
            }

            $duplicates += $result['duplicates'];
            curl_multi_remove_handle($multi, $info['handle']);
            --$active;
            $launch();
        }

        if ($running > 0) {
            $selected = curl_multi_select($multi, 0.5);
            if ($selected === -1) {
                usleep(1_000);
            }
        }
    }

    unset($multi);

    return [
        'attempted' => $attempted,
        'successful' => $successful,
        'failed' => $failed,
        'timeouts' => $timeouts,
        'duplicates' => $duplicates,
    ];
}

/**
 * @return array{
 *   attempted:int,
 *   successful:int,
 *   failed:int,
 *   timeouts:int,
 *   rpm:float,
 *   latencies:list<float>,
 *   duplicates:int,
 *   elapsed_seconds:float
 * }
 */
function uidRunDuration(
    string $url,
    int $concurrency,
    int $durationSeconds,
    int $idsPerResponse,
): array {
    $multi = curl_multi_init();
    $active = 0;
    $attempted = 0;
    $successful = 0;
    $failed = 0;
    $timeouts = 0;
    $duplicates = 0;
    $latencies = [];
    $started = hrtime(true);
    $stopAt = $started + ($durationSeconds * 1_000_000_000);

    for ($index = 0; $index < $concurrency; ++$index) {
        curl_multi_add_handle($multi, uidCreateHandle($url));
        ++$active;
    }

    while ($active > 0) {
        do {
            $status = curl_multi_exec($multi, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        $status === CURLM_OK || throw new RuntimeException('Host benchmark curl multi execution failed');

        while (($info = curl_multi_info_read($multi)) !== false) {
            $result = uidInspectCompletion($info, $idsPerResponse);
            ++$attempted;

            if ($result['successful']) {
                ++$successful;
                if (count($latencies) < UID_MAX_LATENCY_SAMPLES) {
                    $latencies[] = $result['latency'];
                }
            } else {
                ++$failed;
            }

            if ($result['timeout']) {
                ++$timeouts;
            }

            $duplicates += $result['duplicates'];
            curl_multi_remove_handle($multi, $info['handle']);
            --$active;

            if (hrtime(true) < $stopAt) {
                curl_multi_add_handle($multi, uidCreateHandle($url));
                ++$active;
            }
        }

        if ($running > 0) {
            $selected = curl_multi_select($multi, 0.5);
            if ($selected === -1) {
                usleep(1_000);
            }
        }
    }

    unset($multi);

    $elapsed = max((hrtime(true) - $started) / 1_000_000_000, 0.000001);

    return [
        'attempted' => $attempted,
        'successful' => $successful,
        'failed' => $failed,
        'timeouts' => $timeouts,
        'rpm' => ($successful / $elapsed) * 60,
        'latencies' => $latencies,
        'duplicates' => $duplicates,
        'elapsed_seconds' => $elapsed,
    ];
}

/**
 * @param array<string, mixed> $serverRuntime
 * @return array<string, mixed>
 */
function uidEnvironment(string $release, array $serverRuntime): array
{
    $cpuModel = 'unknown';
    $cpuInfo = is_readable('/proc/cpuinfo') ? file_get_contents('/proc/cpuinfo') : false;
    if (is_string($cpuInfo) && preg_match('/^model name\s*:\s*(.+)$/m', $cpuInfo, $matches) === 1) {
        $cpuModel = trim($matches[1]);
    }

    $extensions = get_loaded_extensions();
    sort($extensions, SORT_STRING);

    $environment = [
        'stable' => true,
        'php_version' => PHP_VERSION,
        'php_sapi' => PHP_SAPI,
        'operating_system' => PHP_OS_FAMILY . ' ' . php_uname('r'),
        'cpu_model' => $cpuModel,
        'memory_limit' => (string) ini_get('memory_limit'),
        'opcache' => (string) ini_get('opcache.enable_cli'),
        'jit' => (string) ini_get('opcache.jit'),
        'xdebug' => extension_loaded('xdebug'),
        'extensions' => $extensions,
        'runner' => (string) (getenv('RUNNER_NAME') ?: 'github-actions'),
        'server_runtime' => $serverRuntime,
    ];

    $fingerprintSource = $environment;
    $environment['fingerprint'] = hash(
        'sha256',
        json_encode($fingerprintSource, JSON_THROW_ON_ERROR),
    );
    $environment['release'] = $release;

    return $environment;
}

/**
 * @return array{
 *   rpms:list<float>,
 *   latencies:list<float>,
 *   attempted:int,
 *   successful:int,
 *   failed:int,
 *   timeouts:int,
 *   duplicates:int,
 *   elapsed:float
 * }
 */
function uidEmptyAggregate(): array
{
    return [
        'rpms' => [],
        'latencies' => [],
        'attempted' => 0,
        'successful' => 0,
        'failed' => 0,
        'timeouts' => 0,
        'duplicates' => 0,
        'elapsed' => 0.0,
    ];
}

/**
 * @param array{
 *   rpms:list<float>,
 *   latencies:list<float>,
 *   attempted:int,
 *   successful:int,
 *   failed:int,
 *   timeouts:int,
 *   duplicates:int,
 *   elapsed:float
 * } $aggregate
 * @param array{
 *   attempted:int,
 *   successful:int,
 *   failed:int,
 *   timeouts:int,
 *   rpm:float,
 *   latencies:list<float>,
 *   duplicates:int,
 *   elapsed_seconds:float
 * } $result
 */
function uidAccumulate(array &$aggregate, array $result): void
{
    $aggregate['rpms'][] = $result['rpm'];
    $aggregate['attempted'] += $result['attempted'];
    $aggregate['successful'] += $result['successful'];
    $aggregate['failed'] += $result['failed'];
    $aggregate['timeouts'] += $result['timeouts'];
    $aggregate['duplicates'] += $result['duplicates'];
    $aggregate['elapsed'] += $result['elapsed_seconds'];

    $remaining = UID_MAX_LATENCY_SAMPLES - count($aggregate['latencies']);
    if ($remaining > 0) {
        $aggregate['latencies'] = [
            ...$aggregate['latencies'],
            ...array_slice($result['latencies'], 0, $remaining),
        ];
    }
}

/**
 * @param array{
 *   rpms:list<float>,
 *   latencies:list<float>,
 *   attempted:int,
 *   successful:int,
 *   failed:int,
 *   timeouts:int,
 *   duplicates:int,
 *   elapsed:float
 * } $aggregate
 * @param array<string, mixed> $serverRuntime
 * @return array<string, mixed>
 */
function uidBuildDocument(
    string $release,
    string $route,
    int $concurrency,
    int $duration,
    int $repetitions,
    int $idsPerResponse,
    int $warmup,
    array $aggregate,
    array $serverRuntime,
): array {
    // Balanced AB/BA trials always have an even sample count.
    $sortedRpms = $aggregate['rpms'];
    sort($sortedRpms, SORT_NUMERIC);
    $middle = intdiv(count($sortedRpms), 2);
    $medianRpm = ($sortedRpms[$middle - 1] + $sortedRpms[$middle]) / 2;
    $spread = $medianRpm > 0
        ? ((uidPercentile($aggregate['rpms'], 0.75) - uidPercentile($aggregate['rpms'], 0.25)) / $medianRpm) * 100
        : 100.0;
    $stable = $spread <= 15.0
        && $aggregate['failed'] === 0
        && $aggregate['duplicates'] === 0
        && $aggregate['timeouts'] === 0;
    $latencies = $aggregate['latencies'];

    return [
        'schema_version' => 1,
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'environment' => uidEnvironment($release, $serverRuntime),
        'workloads' => [[
            'name' => $route . '-c' . $concurrency,
            'type' => 'http',
            'metadata' => [
                'route' => '/' . ltrim($route, '/'),
                'trial_duration_seconds' => $duration,
                'ids_per_response' => $idsPerResponse,
                'paired_trial_order' => 'AB/BA',
            ],
            'repetitions' => $repetitions,
            'warmup_operations' => $warmup,
            'duration_seconds' => $duration * $repetitions,
            'concurrency' => $concurrency,
            'result' => [
                'trial_successful_rpm' => $aggregate['rpms'],
                'attempted_operations' => $aggregate['attempted'],
                'successful_operations' => $aggregate['successful'],
                'failed_operations' => $aggregate['failed'],
                'timeouts' => $aggregate['timeouts'],
                'duplicate_ids' => $aggregate['duplicates'],
                'successful_rpm' => round($medianRpm, 5),
                'error_rate' => $aggregate['attempted'] === 0
                    ? 0.0
                    : $aggregate['failed'] / $aggregate['attempted'],
                'measured_elapsed_seconds' => round($aggregate['elapsed'], 5),
                'latency_ms' => [
                    'minimum' => $latencies === [] ? null : round(min($latencies), 5),
                    'average' => $latencies === [] ? null : round(uidAverage($latencies), 5),
                    'p50' => $latencies === [] ? null : round(uidPercentile($latencies, 0.50), 5),
                    'p95' => $latencies === [] ? null : round(uidPercentile($latencies, 0.95), 5),
                    'p99' => $latencies === [] ? null : round(uidPercentile($latencies, 0.99), 5),
                    'maximum' => $latencies === [] ? null : round(max($latencies), 5),
                ],
                'cpu' => [
                    'average_percent' => null,
                    'peak_percent' => null,
                ],
                'memory' => [
                    'average_mb' => null,
                    'peak_mb' => null,
                    'growth_mb' => null,
                ],
                'stability' => [
                    'status' => $stable ? 'stable' : 'unstable',
                    'spread_percent' => round($spread, 5),
                ],
            ],
        ]],
    ];
}

$urls = [
    'baseline' => rtrim($baselineUrl, '/') . '/' . ltrim($route, '/'),
    'candidate' => rtrim($candidateUrl, '/') . '/' . ltrim($route, '/'),
];

/** @return array<string, mixed> */
function uidServerRuntime(string $url): array
{
    $handle = uidCreateHandle(rtrim($url, '/') . '/health');
    $body = curl_exec($handle);
    $httpCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    if (!is_string($body) || $httpCode !== 200) {
        throw new RuntimeException('Unable to read benchmark server runtime');
    }

    $health = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    $runtime = is_array($health) ? ($health['runtime'] ?? null) : null;
    if (!is_array($runtime)) {
        throw new RuntimeException('Benchmark server runtime is missing');
    }

    return $runtime;
}

$serverRuntime = uidServerRuntime($baselineUrl);
if ($serverRuntime !== uidServerRuntime($candidateUrl)) {
    throw new RuntimeException('Benchmark server runtimes do not match');
}
if (($serverRuntime['opcache'] ?? false) !== true) {
    throw new RuntimeException('Warm host benchmark requires OPcache on both servers');
}

foreach ($urls as $url) {
    $warmupResult = uidRunOperations($url, $concurrency, $warmup, $idsPerResponse);
    if (
        $warmupResult['failed'] !== 0
        || $warmupResult['timeouts'] !== 0
        || $warmupResult['duplicates'] !== 0
    ) {
        throw new RuntimeException('Host benchmark warmup produced invalid responses');
    }
}

$aggregates = [
    'baseline' => uidEmptyAggregate(),
    'candidate' => uidEmptyAggregate(),
];

for ($repetition = 0; $repetition < $repetitions; ++$repetition) {
    $order = ($repetition % 2) === 0
        ? ['baseline', 'candidate']
        : ['candidate', 'baseline'];

    foreach ($order as $target) {
        $result = uidRunDuration(
            $urls[$target],
            $concurrency,
            $duration,
            $idsPerResponse,
        );
        uidAccumulate($aggregates[$target], $result);
    }
}

$baselineDocument = uidBuildDocument(
    '5.0',
    $route,
    $concurrency,
    $duration,
    $repetitions,
    $idsPerResponse,
    $warmup,
    $aggregates['baseline'],
    $serverRuntime,
);
$candidateDocument = uidBuildDocument(
    'candidate',
    $route,
    $concurrency,
    $duration,
    $repetitions,
    $idsPerResponse,
    $warmup,
    $aggregates['candidate'],
    $serverRuntime,
);

foreach ([
    $baselineOutput => $baselineDocument,
    $candidateOutput => $candidateDocument,
] as $path => $document) {
    file_put_contents(
        $path,
        json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
    );
}

foreach ([$baselineDocument, $candidateDocument] as $document) {
    $result = $document['workloads'][0]['result'];
    if (($result['stability']['status'] ?? null) !== 'stable') {
        throw new RuntimeException('Host benchmark did not reach a stable valid state');
    }
}
