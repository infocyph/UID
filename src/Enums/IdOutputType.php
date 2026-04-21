<?php

declare(strict_types=1);

namespace Infocyph\UID\Enums;

enum IdOutputType: string
{
    case BINARY = 'binary';

    case INT = 'int';

    case STRING = 'string';
}
