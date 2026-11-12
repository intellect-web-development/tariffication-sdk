<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

/** Возврат за неиспользованную часть оплаченного периода, в копейках */
final readonly class Refunds
{
    public function __construct(
        public int $tariffPrice,
        public int $commissionPercent,
        public int $commission,
        public int $refundsAmountBeforeCommission,
        public int $refundsAmountAfterCommission,
    ) {
    }
}
