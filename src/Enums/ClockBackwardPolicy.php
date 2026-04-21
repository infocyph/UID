<?php

declare(strict_types=1);

namespace Infocyph\UID\Enums;

enum ClockBackwardPolicy: string
{
    case THROW = 'throw';

    case WAIT = 'wait';
}
