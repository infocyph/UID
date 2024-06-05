<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src'
    ]);
    $rectorConfig->sets([
        constant("Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_82")
    ]);
    $rectorConfig->skip([
        StringClassNameToClassConstantRector::class
    ]);
};
