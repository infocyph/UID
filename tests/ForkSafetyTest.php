<?php

declare(strict_types=1);

use Infocyph\UID\CUID2;
use Infocyph\UID\ObjectID;
use Infocyph\UID\ULID;
use Infocyph\UID\UUID;
use Infocyph\UID\XID;

test('process-local generator state is reseeded after a fork', function (Closure $generator) {
    if (!function_exists('pcntl_fork') || !function_exists('pcntl_exec')) {
        $this->markTestSkipped('The pcntl extension is required for fork-safety coverage');
    }

    $generator();
    $resultFile = sys_get_temp_dir() . '/uid-fork-' . bin2hex(random_bytes(12));

    $pid = pcntl_fork();
    expect($pid)->toBeGreaterThanOrEqual(0);
    if ($pid === 0) {
        file_put_contents($resultFile, $generator());
        pcntl_exec(PHP_BINARY, ['-r', '']);
        throw new RuntimeException('Unable to terminate fork child');
    }

    $parentId = $generator();
    pcntl_waitpid($pid, $status);
    $childId = file_get_contents($resultFile);
    unlink($resultFile);

    expect($childId)->toBeString()->not()->toBe('')
        ->and($parentId)->not()->toBe($childId);
})->with([
    'uuid-v1' => [fn(): string => UUID::v1()],
    'uuid-v7' => [fn(): string => UUID::v7(new DateTimeImmutable('@1700000000.123'))],
    'ulid' => [fn(): string => ULID::generateMonotonic(new DateTimeImmutable('@1700000000.123'))],
    'cuid2' => [fn(): string => CUID2::generate()],
    'xid' => [fn(): string => XID::generate()],
    'object-id' => [fn(): string => ObjectID::generate(new DateTimeImmutable('@1700000000'))],
]);
