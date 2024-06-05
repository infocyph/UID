<?php

use Infocyph\UID\TBSL;

test('Basic', function () {
    $sf = TBSL::generate();
    $parsed = TBSL::parse($sf);
    expect($parsed['time']->getTimestamp())->toBeBetween(time() - 1, time())
        ->and($parsed['machineId'])->toBe('00');
});

