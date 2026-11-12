<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

final readonly class Privilege
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public bool $visible,
    ) {
    }
}
