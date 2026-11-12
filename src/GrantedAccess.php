<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

/** Результат выдачи или продления. `replayed` — повтор по тому же reference, ничего не изменилось */
final readonly class GrantedAccess
{
    public function __construct(
        public Access $access,
        public bool $replayed,
    ) {
    }
}
