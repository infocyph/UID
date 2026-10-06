<?php

declare(strict_types=1);

namespace Infocyph\UID\Enums;

enum RandflakeFormat: string
{
    case UID = 'uid';

    case UPSTREAM = 'upstream';
}
