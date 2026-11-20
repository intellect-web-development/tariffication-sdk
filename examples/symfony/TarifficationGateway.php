<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk\Examples\Symfony;

use IWD\TarifficationSdk\TarifficationApiException;
use IWD\TarifficationSdk\TarifficationClient;
use IWD\TarifficationSdk\UserTariff;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

/**
 * Пример адаптера продукта к сервису Tariffication.
 * В продукте копируйте в App\PlatformProjection\Tariffication (или свой BC); домен SDK не импортирует.
 * Ошибки сервиса переводятся в собственные исключения продукта.
 */
final readonly class TarifficationGateway
{
    public function __construct(
        private TarifficationClient $client,
    ) {
    }

    public function userTariff(int $userId): UserTariff
    {
        try {
            return $this->client->userTariff($userId);
        } catch (TarifficationApiException|HttpClientException $exception) {
            // Сервис недоступен или отказал: тариф неизвестен, платных возможностей не выдаём «на веру»
            throw new \RuntimeException('Сервис тарифов недоступен', 0, $exception);
        }
    }
}
