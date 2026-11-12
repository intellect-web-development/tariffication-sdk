<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

enum TariffStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
