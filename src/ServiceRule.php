<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

/** Правило предоставления услуги на тарифе. Деньги — десятичные строки в копейках */
final readonly class ServiceRule
{
    public function __construct(
        public string $id,
        public string $serviceId,
        public string $serviceCode,
        public string $serviceName,
        public string $serviceDescription,
        public BillingMethod $billingMethod,
        public ?string $costPerUnitInKopeks,
        public ?string $billingDescription,
        public ?string $minPaymentSumInKopeks,
        public bool $visible,
    ) {
    }
}
