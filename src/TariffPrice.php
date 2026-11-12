<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

use DateTimeImmutable;

/** Цена тарифа за период. Деньги — десятичная строка в копейках */
final readonly class TariffPrice
{
    public function __construct(
        public string $id,
        public int $daysPeriod,
        public string $priceInKopeks,
        public bool $enabled,
        public bool $visible,
        public ?DateTimeImmutable $datetime,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
