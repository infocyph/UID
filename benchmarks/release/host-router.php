<?php

declare(strict_types=1);

use Infocyph\UID\Configuration\SnowflakeConfig;
use Infocyph\UID\CUID2;
use Infocyph\UID\Sequence\FilesystemSequenceProvider;
use Infocyph\UID\Snowflake;

$root = getenv('UID_TARGET_ROOT');
$stateDirectory = getenv('UID_STATE_DIR');

if (!is_string($root) || $root === '' || !is_string($stateDirectory) || $stateDirectory === '') {
    http_response_code(500);
    file_put_contents('php://output', json_encode(['error' => 'release benchmark environment is incomplete'], JSON_THROW_ON_ERROR));

    return;
}

require_once $root . '/vendor/autoload.php';

header('Content-Type: application/json');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

try {
    if ($path === '/health') {
        file_put_contents('php://output', json_encode(['ok' => true], JSON_THROW_ON_ERROR));

        return;
    }

    if ($path === '/cuid2-one') {
        file_put_contents('php://output', json_encode(['ids' => [CUID2::generate()]], JSON_THROW_ON_ERROR));

        return;
    }

    if ($path === '/cuid2-batch') {
        $ids = [];
        for ($index = 0; $index < 100; ++$index) {
            $ids[] = CUID2::generate();
        }

        file_put_contents('php://output', json_encode(['ids' => $ids], JSON_THROW_ON_ERROR));

        return;
    }

    if ($path === '/snowflake-contended') {
        $provider = new FilesystemSequenceProvider(
            $stateDirectory,
            'release-host',
            2_000_000,
            1,
        );
        $id = Snowflake::generateWithConfig(new SnowflakeConfig(
            datacenterId: 1,
            workerId: 1,
            sequenceProvider: $provider,
        ));

        file_put_contents('php://output', json_encode(['ids' => [$id]], JSON_THROW_ON_ERROR));

        return;
    }

    http_response_code(404);
    file_put_contents('php://output', json_encode(['error' => 'unknown benchmark route'], JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    http_response_code(500);
    file_put_contents('php://output', json_encode([
        'error' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR));
}
