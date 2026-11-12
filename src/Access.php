<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

use DateTimeImmutable;

/** Доступ пользователя к тарифу на период. `reference` — основание выдачи (например `payment:42`) */
final readonly class Access
{
    public function __construct(
        public string $id,
        public string $reference,
        public AccessStatus $status,
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $expirationDate,
        public ?DateTimeImmutable $revokedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public int $userId,
        public ?string $tariffId,
        public string $tariffPriceId,
        public string $subscriptionId,
        public bool $tariffAutoRenewal,
        public int $daysPeriod,
    ) {
    }
}
