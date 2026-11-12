<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Клиент универсального сервиса Tariffication (тарифы продукта).
 *
 * Токен — персональный JWT системы (выдаёт integration-registry): сервис берёт код системы из claim `sub`
 * и работает только с каталогом, подписчиками и доступами этой системы. Чужой объект неотличим от несуществующего.
 *
 * Деньги — десятичные строки в копейках, float не используем. Идентификаторы пользователей продукта — int.
 * Каждая выдача и продление требует `reference` — ключ идемпотентности в пределах системы. Повтор с теми же
 * параметрами возвращает исходный доступ (`replayed`), с другими — TarifficationApiException::REFERENCE_CONFLICT.
 *
 * Прикладные ошибки (нет тарифа, услуга не предоставляется, конфликт reference и т.д.) приходят как
 * TarifficationApiException. Транспортные ошибки, 401/403, ошибки формата и 5xx пробрасываются исключениями
 * Symfony HttpClient — обработка на стороне адаптера продукта.
 */
final readonly class TarifficationClient
{
    public const int REFERENCE_MAX_LENGTH = 128;
    public const int USER_IDS_BATCH_MAX = 500;
    public const int IDS_MAX = 500;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private string $token,
    ) {
    }

    /**
     * Идемпотентная заливка каталога: недостающее создаётся, существующее обновляется по кодам, ничего не удаляется.
     * Формат — как в INTEGRATION.md сервиса: `services`, `limitations`, `tariffs` (с `prices`, `privileges`, `serviceRules`, `limitationRules`).
     *
     * @param array<string, mixed> $catalog
     */
    public function syncCatalog(array $catalog): void
    {
        $this->request('PUT', '/catalog', ['json' => $catalog]);
    }

    /**
     * Действующие тарифы с ценами, привилегиями и правилами (витрина).
     *
     * @return list<Tariff>
     */
    public function tariffs(): array
    {
        $data = $this->request('GET', '/catalog/tariffs')['data'];

        return array_map($this->mapTariff(...), array_values($data['tariffs']));
    }

    /**
     * Названия тарифов и услуг по идентификаторам или кодам (для истории покупок). Неизвестные и чужие пропускаются.
     * Идентификаторы стабильны, пока каталог не пересоздан; коды - всегда.
     *
     * @param list<int|string> $tariffIds
     * @param list<int|string> $serviceIds
     * @param list<string>     $tariffCodes
     * @param list<string>     $serviceCodes
     */
    public function catalogItems(array $tariffIds = [], array $serviceIds = [], array $tariffCodes = [], array $serviceCodes = []): CatalogItems
    {
        if ([] === $tariffIds && [] === $serviceIds && [] === $tariffCodes && [] === $serviceCodes) {
            return new CatalogItems(tariffs: [], services: []);
        }
        foreach ([$tariffIds, $serviceIds, $tariffCodes, $serviceCodes] as $list) {
            if (count($list) > self::IDS_MAX) {
                throw new InvalidArgumentException(sprintf('Не более %d значений каждого вида за вызов.', self::IDS_MAX));
            }
        }

        $data = $this->request('GET', '/catalog/items', ['query' => array_filter([
            'tariffIds' => [] === $tariffIds ? null : implode(',', $tariffIds),
            'serviceIds' => [] === $serviceIds ? null : implode(',', $serviceIds),
            'tariffCodes' => [] === $tariffCodes ? null : implode(',', $tariffCodes),
            'serviceCodes' => [] === $serviceCodes ? null : implode(',', $serviceCodes),
        ], static fn (mixed $value): bool => null !== $value)])['data'];

        return new CatalogItems(
            tariffs: $this->mapItems($data['tariffs']),
            services: $this->mapItems($data['services']),
        );
    }

    /** Состояние пользователя. Пользователь без тарифа — `UserTariff` с `hasTariff() === false` */
    public function userTariff(int $userId): UserTariff
    {
        $data = $this->request('GET', '/users/' . $this->userId($userId) . '/tariff')['data'];

        return new UserTariff(
            userId: (int) $data['userId'],
            subscriberId: isset($data['subscriberId']) ? (string) $data['subscriberId'] : null,
            access: isset($data['access']) ? $this->mapAccess($data['access']) : null,
            tariff: isset($data['tariff']) ? $this->mapTariff($data['tariff']) : null,
            refunds: new Refunds(
                tariffPrice: (int) $data['refunds']['tariffPrice'],
                commissionPercent: (int) $data['refunds']['commissionPercent'],
                commission: (int) $data['refunds']['commission'],
                refundsAmountBeforeCommission: (int) $data['refunds']['refundsAmountBeforeCommission'],
                refundsAmountAfterCommission: (int) $data['refunds']['refundsAmountAfterCommission'],
            ),
        );
    }

    /**
     * Значение лимита на тарифе пользователя. null — лимит без числового ограничения.
     * Бросает NO_ACTIVE_TARIFF, SERVICE_NOT_PROVIDED (лимита нет на тарифе) или LIMITATION_NOT_FOUND.
     */
    public function limit(int $userId, string $limitationCode): ?string
    {
        $data = $this->request('GET', '/users/' . $this->userId($userId) . '/limits/' . rawurlencode($limitationCode))['data'];

        return isset($data['value']) ? (string) $data['value'] : null;
    }

    /**
     * Цена услуги за объём по правилу тарифа пользователя. Бросает NO_ACTIVE_TARIFF или SERVICE_NOT_PROVIDED.
     *
     * @param string $volume неотрицательное десятичное число с точкой
     */
    public function quote(int $userId, string $serviceCode, string $volume): ServiceQuote
    {
        if (1 !== preg_match('/^\d{1,13}(\.\d{1,8})?$/', $volume)) {
            throw new InvalidArgumentException('volume: неотрицательная десятичная строка с точкой, например «1500.25».');
        }

        $data = $this->request('POST', '/users/' . $this->userId($userId) . '/services/' . rawurlencode($serviceCode) . '/quote', ['json' => ['volume' => $volume]])['data'];

        return new ServiceQuote(
            serviceCode: (string) $data['serviceCode'],
            tariffCode: (string) $data['tariffCode'],
            priceInKopeks: (string) $data['priceInKopeks'],
            rule: $this->mapServiceRule($data['rule']),
        );
    }

    /**
     * Из переданных пользователей оставляет тех, чей действующий тариф предоставляет услугу. Для обходов по всем
     * пользователям: проверка идёт пакетами по 500, а не вызовом на каждого.
     *
     * @param list<int> $userIds
     *
     * @return list<int>
     */
    public function entitledUsers(string $serviceCode, array $userIds): array
    {
        $entitled = [];
        foreach (array_chunk(array_values(array_unique($userIds)), self::USER_IDS_BATCH_MAX) as $chunk) {
            $data = $this->request('POST', '/services/' . rawurlencode($serviceCode) . '/entitled-users', ['json' => ['userIds' => $chunk]])['data'];
            foreach ($data['userIds'] as $userId) {
                $entitled[] = (int) $userId;
            }
        }
        sort($entitled);

        return $entitled;
    }

    /**
     * Подключает тариф по цене. Действующий доступ пользователя отзывается, его подписка прекращается.
     * Деньги к этому моменту уже списаны продуктом.
     */
    public function grantAccess(int $userId, int|string $tariffPriceId, string $reference, bool $tariffAutoRenewal = false): GrantedAccess
    {
        $this->assertReference($reference);

        return $this->mapGranted($this->request('POST', '/users/' . $this->userId($userId) . '/accesses', ['json' => [
            'tariffPriceId' => (int) $tariffPriceId,
            'reference' => $reference,
            'tariffAutoRenewal' => $tariffAutoRenewal,
        ]]));
    }

    /** Продлевает подписку доступа на срок его цены: новый доступ начинается сейчас и заканчивается через срок после действующего */
    public function renewAccess(int|string $accessId, string $reference): GrantedAccess
    {
        $this->assertReference($reference);

        return $this->mapGranted($this->request('POST', '/accesses/' . $this->id($accessId) . '/renew', ['json' => ['reference' => $reference]]));
    }

    /** Отзыв доступа (компенсация неудавшейся оплаты). Повтор безопасен */
    public function revokeAccess(int|string $accessId): Access
    {
        return $this->mapAccess($this->request('POST', '/accesses/' . $this->id($accessId) . '/revoke', ['json' => new \stdClass()])['data']['access']);
    }

    /** Включает или выключает автопродление подписки действующего тарифа. Бросает NO_ACTIVE_TARIFF */
    public function setAutoRenewal(int $userId, bool $enabled): Access
    {
        return $this->mapAccess($this->request('PATCH', '/users/' . $this->userId($userId) . '/subscription', ['json' => ['tariffAutoRenewal' => $enabled]])['data']['access']);
    }

    /**
     * Доступы с автопродлением, которые заканчиваются в указанный день.
     *
     * @return list<Access>
     */
    public function dueForRenewal(DateTimeInterface $day): array
    {
        $data = $this->request('GET', '/accesses/due-renewal', ['query' => ['date' => $day->format('Y-m-d')]])['data'];

        return array_map($this->mapAccess(...), array_values($data['accesses']));
    }

    private function assertReference(string $reference): void
    {
        if ('' === $reference || mb_strlen($reference) > self::REFERENCE_MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf('reference: непустая строка до %d символов.', self::REFERENCE_MAX_LENGTH));
        }
    }

    private function userId(int $userId): string
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('userId: положительное целое.');
        }

        return (string) $userId;
    }

    private function id(int|string $id): string
    {
        if (1 !== preg_match('/^[1-9]\d{0,17}$/', (string) $id)) {
            throw new InvalidArgumentException('id: положительное целое.');
        }

        return (string) $id;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{status: int, data: array<string, mixed>}
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $options['headers'] = ['Authorization' => 'Bearer ' . $this->token];

        $response = $this->httpClient->request($method, rtrim($this->baseUrl, '/') . '/api/sdk' . $path, $options);
        try {
            $data = $response->toArray();
        } catch (ClientExceptionInterface $exception) {
            $body = json_decode($exception->getResponse()->getContent(false), true);
            if (is_array($body) && isset($body['error']) && is_string($body['error'])) {
                throw new TarifficationApiException(
                    errorCode: $body['error'],
                    message: (string) ($body['message'] ?? $body['error']),
                    httpStatus: $exception->getResponse()->getStatusCode(),
                );
            }

            throw $exception;
        }

        return ['status' => $response->getStatusCode(), 'data' => $data];
    }

    /**
     * @param array{status: int, data: array<string, mixed>} $response
     */
    private function mapGranted(array $response): GrantedAccess
    {
        return new GrantedAccess(access: $this->mapAccess($response['data']['access']), replayed: 200 === $response['status']);
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array{id: string, code: string, name: string}>
     */
    private function mapItems(array $items): array
    {
        return array_map(
            static fn (array $item): array => ['id' => (string) $item['id'], 'code' => (string) $item['code'], 'name' => (string) $item['name']],
            array_values($items),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapAccess(array $data): Access
    {
        return new Access(
            id: (string) $data['id'],
            reference: (string) $data['reference'],
            status: AccessStatus::from((string) $data['status']),
            startDate: new DateTimeImmutable((string) $data['startDate']),
            expirationDate: new DateTimeImmutable((string) $data['expirationDate']),
            revokedAt: isset($data['revokedAt']) ? new DateTimeImmutable((string) $data['revokedAt']) : null,
            createdAt: new DateTimeImmutable((string) $data['createdAt']),
            updatedAt: new DateTimeImmutable((string) $data['updatedAt']),
            userId: (int) $data['userId'],
            tariffId: isset($data['tariffId']) ? (string) $data['tariffId'] : null,
            tariffPriceId: (string) $data['tariffPriceId'],
            subscriptionId: (string) $data['subscriptionId'],
            tariffAutoRenewal: (bool) $data['tariffAutoRenewal'],
            daysPeriod: (int) $data['daysPeriod'],
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapTariff(array $data): Tariff
    {
        return new Tariff(
            id: (string) $data['id'],
            code: (string) $data['code'],
            name: (string) $data['name'],
            description: (string) $data['description'],
            advantagePromo: isset($data['advantagePromo']) ? (string) $data['advantagePromo'] : null,
            status: TariffStatus::from((string) $data['status']),
            visible: (bool) $data['visible'],
            priority: (int) $data['priority'],
            createdAt: new DateTimeImmutable((string) $data['createdAt']),
            updatedAt: new DateTimeImmutable((string) $data['updatedAt']),
            archivationDate: isset($data['archivationDate']) ? new DateTimeImmutable((string) $data['archivationDate']) : null,
            prices: array_map(
                fn (array $price): TariffPrice => new TariffPrice(
                    id: (string) $price['id'],
                    daysPeriod: (int) $price['daysPeriod'],
                    priceInKopeks: (string) $price['priceInKopeks'],
                    enabled: (bool) $price['enabled'],
                    visible: (bool) $price['visible'],
                    datetime: isset($price['datetime']) ? new DateTimeImmutable((string) $price['datetime']) : null,
                    createdAt: new DateTimeImmutable((string) $price['createdAt']),
                    updatedAt: new DateTimeImmutable((string) $price['updatedAt']),
                ),
                array_values($data['prices']),
            ),
            privileges: array_map(
                static fn (array $privilege): Privilege => new Privilege(
                    id: (string) $privilege['id'],
                    name: (string) $privilege['name'],
                    description: (string) $privilege['description'],
                    visible: (bool) $privilege['visible'],
                ),
                array_values($data['privileges']),
            ),
            serviceRules: array_map($this->mapServiceRule(...), array_values($data['serviceRules'])),
            limitationRules: array_map(
                static fn (array $rule): LimitationRule => new LimitationRule(
                    id: (string) $rule['id'],
                    limitationId: (string) $rule['limitation']['id'],
                    limitationCode: (string) $rule['limitation']['code'],
                    limitationName: (string) $rule['limitation']['name'],
                    limitationDescription: (string) $rule['limitation']['description'],
                    value: isset($rule['value']) ? (string) $rule['value'] : null,
                    promoTextTemplate: isset($rule['promoTextTemplate']) ? (string) $rule['promoTextTemplate'] : null,
                    visible: (bool) $rule['visible'],
                ),
                array_values($data['limitationRules']),
            ),
        );
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function mapServiceRule(array $rule): ServiceRule
    {
        return new ServiceRule(
            id: (string) $rule['id'],
            serviceId: (string) $rule['service']['id'],
            serviceCode: (string) $rule['service']['code'],
            serviceName: (string) $rule['service']['name'],
            serviceDescription: (string) $rule['service']['description'],
            billingMethod: BillingMethod::from((string) $rule['billingMethod']),
            costPerUnitInKopeks: isset($rule['costPerUnitInKopeks']) ? (string) $rule['costPerUnitInKopeks'] : null,
            billingDescription: isset($rule['billingDescription']) ? (string) $rule['billingDescription'] : null,
            minPaymentSumInKopeks: isset($rule['minPaymentSumInKopeks']) ? (string) $rule['minPaymentSumInKopeks'] : null,
            visible: (bool) $rule['visible'],
        );
    }
}
