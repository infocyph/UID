<?php

declare(strict_types=1);

namespace Infocyph\UID\Enums;

enum UlidGenerationMode: string
{
    case MONOTONIC = 'monotonic';

    case RANDOM = 'random';
}
