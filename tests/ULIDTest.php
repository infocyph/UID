<?php

use Infocyph\UID\ULID;

test('Basic', function () {
    $ulid = ULID::generate();
    expect($ulid)->toBeString()
        ->and(ULID::isValid($ulid))->toBeTrue()
        ->and(ULID::getTime($ulid)->getTimestamp())->toBeBetween(time() - 1, time());
});
