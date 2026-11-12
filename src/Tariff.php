<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

use DateTimeImmutable;

final readonly class Tariff
{
    /**
     * @param list<TariffPrice> $prices
     * @param list<Privilege> $privileges
     * @param list<ServiceRule> $serviceRules
     * @param list<LimitationRule> $limitationRules
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $description,
        public ?string $advantagePromo,
        public TariffStatus $status,
        public bool $visible,
        public int $priority,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?DateTimeImmutable $archivationDate,
        public array $prices,
        public array $privileges,
        public array $serviceRules,
        public array $limitationRules,
    ) {
    }

    public function isActive(): bool
    {
        return TariffStatus::Active === $this->status;
    }

    public function serviceRule(string $serviceCode): ?ServiceRule
    {
        foreach ($this->serviceRules as $rule) {
            if ($rule->serviceCode === $serviceCode) {
                return $rule;
            }
        }

        return null;
    }

    public function limitationRule(string $limitationCode): ?LimitationRule
    {
        foreach ($this->limitationRules as $rule) {
            if ($rule->limitationCode === $limitationCode) {
                return $rule;
            }
        }

        return null;
    }

    public function price(int $daysPeriod): ?TariffPrice
    {
        foreach ($this->prices as $price) {
            if ($price->daysPeriod === $daysPeriod) {
                return $price;
            }
        }

        return null;
    }
}
