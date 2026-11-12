<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

enum BillingMethod: string
{
    /** Услуга включена в тариф */
    case Included = 'included';
    /** Устаревший вариант: включена с ограничением */
    case Limited = 'limited';
    /** Оплачивается отдельно по стоимости единицы */
    case Separate = 'separate';
    /** На тарифе не предоставляется */
    case NotProvided = 'not_provided';
}
