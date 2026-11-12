<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

use RuntimeException;

/**
 * Прикладная ошибка сервиса: 4xx с телом `{error, message}`. Различайте причины по `errorCode`.
 * Ошибки транспорта, 401/403 без `error`, ошибки формата (422 без `error`) и 5xx пробрасываются исключениями Symfony HttpClient.
 */
final class TarifficationApiException extends RuntimeException
{
    public const string TARIFF_NOT_FOUND = 'tariff_not_found';
    public const string TARIFF_PRICE_NOT_FOUND = 'tariff_price_not_found';
    public const string ACCESS_NOT_FOUND = 'access_not_found';
    public const string SERVICE_NOT_FOUND = 'service_not_found';
    public const string LIMITATION_NOT_FOUND = 'limitation_not_found';
    public const string SUBSCRIBER_NOT_FOUND = 'subscriber_not_found';
    /** У пользователя нет действующего тарифа */
    public const string NO_ACTIVE_TARIFF = 'no_active_tariff';
    /** Услуга или лимит не предоставляются на тарифе пользователя */
    public const string SERVICE_NOT_PROVIDED = 'service_not_provided';
    public const string TARIFF_PRICE_NOT_ENABLED = 'tariff_price_not_enabled';
    public const string TARIFF_NOT_ACTIVE = 'tariff_not_active';
    /** Тот же reference уже использован для другого основания */
    public const string REFERENCE_CONFLICT = 'reference_conflict';

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message, $httpStatus);
    }

    public function isNotFound(): bool
    {
        return in_array($this->errorCode, [
            self::TARIFF_NOT_FOUND,
            self::TARIFF_PRICE_NOT_FOUND,
            self::ACCESS_NOT_FOUND,
            self::SERVICE_NOT_FOUND,
            self::LIMITATION_NOT_FOUND,
            self::SUBSCRIBER_NOT_FOUND,
        ], true);
    }
}
