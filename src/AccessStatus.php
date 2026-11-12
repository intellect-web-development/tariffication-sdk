<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

enum AccessStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Revoked = 'revoked';
}
