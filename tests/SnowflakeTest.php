<?php

use Infocyph\UID\Snowflake;

test('Basic', function () {
    $sf = Snowflake::generate();
    $parsed = Snowflake::parse($sf);
    expect($parsed['time']->getTimestamp())->toBeBetween(time() - 1, time())
        ->and($parsed['worker_id'])->toBe(0)
        ->and($parsed['datacenter_id'])->toBe(0);
});

