<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

/** Значение лимита на тарифе. `value` null — лимит без числового ограничения */
final readonly class LimitationRule
{
    public function __construct(
        public string $id,
        public string $limitationId,
        public string $limitationCode,
        public string $limitationName,
        public string $limitationDescription,
        public ?string $value,
        public ?string $promoTextTemplate,
        public bool $visible,
    ) {
    }
}
