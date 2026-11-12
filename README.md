# tariffication-sdk

HTTP-клиент универсального сервиса **Tariffication**: каталог тарифов продукта, лимиты, цена услуг, доступы и подписки пользователей. Контракт сервиса и чеклист подключения — `INTEGRATION.md` в репозитории сервиса.

## Установка

В `composer.json` продукта: vcs-репозиторий + версия пакета (path-репозиторий и `packages/` не используем).

```json
{
    "repositories": [
        {"type": "vcs", "url": "git@github.com:intellect-web-development/tariffication-sdk.git"}
    ],
    "require": {
        "intellect-web-development/tariffication-sdk": "^1.0"
    }
}
```

Нужен `symfony/http-client` (приходит зависимостью).

## Подключение

`config/services.yaml` — см. `examples/symfony/services.yaml`. Переменные окружения: `TARIFFICATION_BASE_URL`, `PLATFORM_SERVICE_TOKEN` (персональный JWT системы со scopes `tariffication:catalog:read|write`, `tariffication:subscribers:read|write`).

Домен продукта SDK не импортирует: между ними адаптер (пример — `examples/symfony/TarifficationGateway.php`).

## Использование

```php
$client = new TarifficationClient(httpClient: $httpClient, baseUrl: 'http://backend-tariffication', token: $token);

// Каталог заливает деплой продукта; операция идемпотентна
$client->syncCatalog($catalog);

// Состояние пользователя и лимиты
$userTariff = $client->userTariff(userId: 77);
if ($userTariff->hasTariff()) {
    $limit = $client->limit(userId: 77, limitationCode: 'max_strategies'); // '3.00' или null без числового ограничения
}

// Цена услуги за объём по правилу тарифа пользователя, копейки
$quote = $client->quote(userId: 77, serviceCode: 'trade_signals', volume: '1000000');

// Покупка: продукт списал деньги, затем фиксирует доступ. Повтор с тем же reference безопасен
$granted = $client->grantAccess(userId: 77, tariffPriceId: 5, reference: 'payment:42', tariffAutoRenewal: true);

// Стартовый (бесплатный) тариф: выдать, только если у пользователя нет действующего доступа
$free = $client->grantAccess(userId: 77, tariffPriceId: 1, reference: 'free:77', onlyIfNoActive: true);
if ($free->skipped) {
    // у пользователя уже есть действующий доступ (он в $free->access), ничего не изменилось
}
```

## Согласованность

Общей транзакции с БД продукта нет. Каждая выдача и продление несёт `reference` — ключ идемпотентности (до 128 символов): повтор с теми же параметрами возвращает исходный доступ (`replayed === true`), с другими — `TarifficationApiException::REFERENCE_CONFLICT`. `grantAccess(..., onlyIfNoActive: true)` не отзывает действующий доступ: если он есть, выдачи нет, а результат несёт `skipped === true` и действующий доступ. Рекомендуемый порядок: списать деньги → `grantAccess` → при сбое после списания откатить проводки, при сбое после выдачи — `revokeAccess`.

## Ошибки

- `TarifficationApiException` (`errorCode`, `httpStatus`) — прикладные: `no_active_tariff`, `service_not_provided`, `tariff_price_not_found`, `tariff_price_not_enabled`, `tariff_not_active`, `reference_conflict`, `*_not_found`.
- Ошибки транспорта, 401/403, ошибки формата и 5xx пробрасываются исключениями Symfony HttpClient — обработка в адаптере продукта.

## Тесты

```shell
composer install
vendor/bin/phpunit
```
