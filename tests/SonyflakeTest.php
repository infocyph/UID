<?php

use Infocyph\UID\Sonyflake;

test('Basic', function () {
    $sf = Sonyflake::generate();
    $parsed = Sonyflake::parse($sf);
    expect($parsed['time']->getTimestamp())->toBeBetween(time() - 1, time())
        ->and($parsed['machine_id'])->toBe(0);
});

