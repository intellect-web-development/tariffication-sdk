<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

/** Состояние пользователя: действующий доступ, тариф с правилами и расчёт возврата. У пользователя без тарифа `access` и `tariff` равны null */
final readonly class UserTariff
{
    public function __construct(
        public int $userId,
        public ?string $subscriberId,
        public ?Access $access,
        public ?Tariff $tariff,
        public Refunds $refunds,
    ) {
    }

    public function hasTariff(): bool
    {
        return null !== $this->access && null !== $this->tariff;
    }
}
