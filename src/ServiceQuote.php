<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

/** Цена услуги за объём по правилу тарифа пользователя, в копейках */
final readonly class ServiceQuote
{
    public function __construct(
        public string $serviceCode,
        public string $tariffCode,
        public string $priceInKopeks,
        public ServiceRule $rule,
    ) {
    }
}
