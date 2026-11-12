<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk;

/**
 * Результат выдачи или продления. `replayed` — повтор по тому же reference, ничего не изменилось.
 * `skipped` — выдача с `onlyIfNoActive` не понадобилась: у пользователя уже есть действующий доступ, он и лежит в `access`.
 */
final readonly class GrantedAccess
{
    public function __construct(
        public Access $access,
        public bool $replayed,
        public bool $skipped = false,
    ) {
    }
}
