<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/benchmarks/release/HostBenchmark.php';

test('host acceptance rejects malformed IDs instead of counting nonempty output as throughput', function (): void {
    expect(uidValidId('a' . str_repeat('0', 23), '/cuid2-one'))->toBeTrue()
        ->and(uidValidId('x', '/cuid2-one'))->toBeFalse()
        ->and(uidValidId(str_repeat('0', 24), '/cuid2-batch'))->toBeFalse()
        ->and(uidValidId('a' . str_repeat('0', 23) . "\n", '/cuid2-one'))->toBeFalse()
        ->and(uidValidId((string) PHP_INT_MAX, '/snowflake-contended'))->toBeTrue()
        ->and(uidValidId('9223372036854775808', '/snowflake-contended'))->toBeFalse()
        ->and(uidValidId('-1', '/snowflake-contended'))->toBeFalse()
        ->and(uidValidId('1', '/unrecognized'))->toBeFalse();
});

test('host report computes an even-sample median and retains trial order outside workload metadata', function (): void {
    $aggregate = uidEmptyAggregate();
    $aggregate['rpms'] = [400.0, 100.0, 300.0, 200.0];
    $document = uidBuildDocument('candidate', 'cuid2-one', 1, 60, 4, 1, 130, $aggregate, []);
    $workload = $document['workloads'][0];
    expect($workload['result']['successful_rpm'])->toBe(250.0)
        ->and($workload['result']['trial_successful_rpm'])->toBe([400.0, 100.0, 300.0, 200.0])
        ->and(array_key_exists('trial_successful_rpm', $workload['metadata']))->toBeFalse();
});
