<?php

declare(strict_types=1);

namespace Infocyph\UID\Enums;

enum SonyflakeFormat: string
{
    case UID = 'uid';

    case UPSTREAM = 'upstream';
}
