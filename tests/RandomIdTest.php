<?php

use Infocyph\UID\RandomId;

test('CUID2', function () {
    $string = RandomId::cuid2();
    expect($string)->toBeString()->not()->toBeEmpty();
});

test('nanoId', function () {
    $string = RandomId::nanoId();
    expect($string)->toBeString()->not()->toBeEmpty()->toHaveLength(21);
});
