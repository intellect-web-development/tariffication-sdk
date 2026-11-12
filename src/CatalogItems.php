<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

/** Названия тарифов и услуг (для истории покупок) */
final readonly class CatalogItems
{
    /**
     * @param list<array{id: string, code: string, name: string}> $tariffs
     * @param list<array{id: string, code: string, name: string}> $services
     */
    public function __construct(
        public array $tariffs,
        public array $services,
    ) {
    }

    public function tariffName(string $code): ?string
    {
        return $this->nameByCode($this->tariffs, $code);
    }

    public function serviceName(string $code): ?string
    {
        return $this->nameByCode($this->services, $code);
    }

    /** @param list<array{id: string, code: string, name: string}> $items */
    private function nameByCode(array $items, string $code): ?string
    {
        foreach ($items as $item) {
            if ($item['code'] === $code) {
                return $item['name'];
            }
        }

        return null;
    }
}
