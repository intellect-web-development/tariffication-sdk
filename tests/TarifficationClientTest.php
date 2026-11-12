<?php

declare(strict_types=1);

namespace IWD\TarifficationSdk\Tests;

use InvalidArgumentException;
use IWD\TarifficationSdk\AccessStatus;
use IWD\TarifficationSdk\BillingMethod;
use IWD\TarifficationSdk\TarifficationApiException;
use IWD\TarifficationSdk\TarifficationClient;
use IWD\TarifficationSdk\TariffStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

final class TarifficationClientTest extends TestCase
{
    private const string ACCESS = '{"id":"15","reference":"payment:42","status":"active","startDate":"2026-11-12T10:00:00+00:00","expirationDate":"2026-12-12T23:59:59+00:00","revokedAt":null,"createdAt":"2026-11-12T10:00:00+00:00","updatedAt":"2026-11-12T10:00:00+00:00","userId":77,"tariffId":"2","tariffPriceId":"5","subscriptionId":"9","tariffAutoRenewal":true,"daysPeriod":30}';

    private const string TARIFF = '{"id":"2","code":"premium","name":"Премиум","description":"Всё включено","advantagePromo":null,"status":"active","visible":true,"priority":3,"createdAt":"2026-11-01T10:00:00+00:00","updatedAt":"2026-11-01T10:00:00+00:00","archivationDate":null,"prices":[{"id":"5","daysPeriod":30,"priceInKopeks":"199900.00","enabled":true,"visible":true,"datetime":null,"createdAt":"2026-11-01T10:00:00+00:00","updatedAt":"2026-11-01T10:00:00+00:00"}],"privileges":[{"id":"1","name":"Скрининг","description":"","visible":true}],"serviceRules":[{"id":"7","service":{"id":"3","code":"trade_signals","name":"Сигналы","description":""},"billingMethod":"separate","costPerUnitInKopeks":"0.00400000","billingDescription":null,"minPaymentSumInKopeks":"1000.00","visible":true}],"limitationRules":[{"id":"8","limitation":{"id":"4","code":"max_strategies","name":"Стратегии","description":""},"value":"50.00","promoTextTemplate":null,"visible":true}]}';

    /** @var array{method: string, url: string, headers: list<string>, body: string} */
    private array $captured = [];

    public function testSyncCatalogSendsPutWithToken(): void
    {
        $this->client('{"synced":true}')->syncCatalog(['services' => [], 'limitations' => [], 'tariffs' => []]);

        self::assertSame('PUT', $this->captured['method']);
        self::assertSame('http://tariffication.test/api/sdk/catalog', $this->captured['url']);
        self::assertContains('Authorization: Bearer jwt-token', $this->captured['headers']);
        self::assertSame(['services' => [], 'limitations' => [], 'tariffs' => []], $this->body());
    }

    public function testTariffsMapsFullTariff(): void
    {
        $tariffs = $this->client('{"tariffs":[' . self::TARIFF . ']}')->tariffs();

        self::assertSame('http://tariffication.test/api/sdk/catalog/tariffs', $this->captured['url']);
        self::assertCount(1, $tariffs);
        $tariff = $tariffs[0];
        self::assertSame('premium', $tariff->code);
        self::assertSame(3, $tariff->priority);
        self::assertSame(TariffStatus::Active, $tariff->status);
        self::assertTrue($tariff->isActive());
        self::assertSame('199900.00', $tariff->price(30)?->priceInKopeks);
        self::assertNull($tariff->price(7));
        $rule = $tariff->serviceRule('trade_signals');
        self::assertNotNull($rule);
        self::assertSame(BillingMethod::Separate, $rule->billingMethod);
        self::assertSame('1000.00', $rule->minPaymentSumInKopeks);
        self::assertSame('50.00', $tariff->limitationRule('max_strategies')?->value);
        self::assertNull($tariff->serviceRule('unknown'));
    }

    public function testCatalogItemsMapsNamesAndFindsByCode(): void
    {
        $items = $this->client('{"tariffs":[{"id":"2","code":"premium","name":"Премиум"}],"services":[{"id":"3","code":"trade_signals","name":"Сигналы"}]}')
            ->catalogItems(tariffIds: [2], serviceCodes: ['trade_signals']);

        self::assertStringContainsString('tariffIds=2', $this->captured['url']);
        self::assertStringContainsString('serviceCodes=trade_signals', $this->captured['url']);
        self::assertSame('Премиум', $items->tariffName('premium'));
        self::assertSame('Сигналы', $items->serviceName('trade_signals'));
        self::assertNull($items->tariffName('start'));
    }

    public function testCatalogItemsWithoutIdsDoesNotCallService(): void
    {
        $items = $this->client('{}')->catalogItems();

        self::assertSame([], $this->captured);
        self::assertSame([], $items->tariffs);
    }

    public function testUserTariffMapsAccessTariffAndRefunds(): void
    {
        $userTariff = $this->client('{"userId":77,"subscriberId":"4","access":' . self::ACCESS . ',"tariff":' . self::TARIFF . ',"refunds":{"tariffPrice":199900,"commissionPercent":10,"commission":5000,"refundsAmountBeforeCommission":50000,"refundsAmountAfterCommission":45000}}')
            ->userTariff(77);

        self::assertSame('http://tariffication.test/api/sdk/users/77/tariff', $this->captured['url']);
        self::assertTrue($userTariff->hasTariff());
        self::assertSame('payment:42', $userTariff->access?->reference);
        self::assertSame(AccessStatus::Active, $userTariff->access?->status);
        self::assertTrue($userTariff->access?->tariffAutoRenewal);
        self::assertSame(45000, $userTariff->refunds->refundsAmountAfterCommission);
    }

    public function testUserWithoutTariffHasNoAccess(): void
    {
        $userTariff = $this->client('{"userId":77,"subscriberId":null,"access":null,"tariff":null,"refunds":{"tariffPrice":0,"commissionPercent":0,"commission":0,"refundsAmountBeforeCommission":0,"refundsAmountAfterCommission":0}}')
            ->userTariff(77);

        self::assertFalse($userTariff->hasTariff());
        self::assertNull($userTariff->subscriberId);
    }

    public function testLimitReturnsValueAndNullForUnlimited(): void
    {
        self::assertSame('3.00', $this->client('{"code":"max_strategies","value":"3.00"}')->limit(77, 'max_strategies'));
        self::assertSame('http://tariffication.test/api/sdk/users/77/limits/max_strategies', $this->captured['url']);
        self::assertNull($this->client('{"code":"max_strategies","value":null}')->limit(77, 'max_strategies'));
    }

    public function testQuoteSendsVolumeAndMapsRule(): void
    {
        $rule = json_encode(json_decode(self::TARIFF, true)['serviceRules'][0], JSON_THROW_ON_ERROR);
        $quote = $this->client('{"serviceCode":"trade_signals","tariffCode":"premium","priceInKopeks":"4000.00","rule":' . $rule . '}')
            ->quote(77, 'trade_signals', '1000000');

        self::assertSame('POST', $this->captured['method']);
        self::assertSame('http://tariffication.test/api/sdk/users/77/services/trade_signals/quote', $this->captured['url']);
        self::assertSame(['volume' => '1000000'], $this->body());
        self::assertSame('4000.00', $quote->priceInKopeks);
        self::assertSame(BillingMethod::Separate, $quote->rule->billingMethod);
    }

    public function testQuoteRejectsInvalidVolume(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client('{}')->quote(77, 'trade_signals', '-1');
    }

    public function testEntitledUsersAreSentInBatches(): void
    {
        $calls = 0;
        $client = new TarifficationClient(
            httpClient: new MockHttpClient(function (string $method, string $url, array $options) use (&$calls): MockResponse {
                ++$calls;
                $ids = json_decode((string) $options['body'], true)['userIds'];

                return new MockResponse(json_encode(['userIds' => array_values(array_filter($ids, static fn (int $id): bool => 0 === $id % 2))], JSON_THROW_ON_ERROR));
            }),
            baseUrl: 'http://tariffication.test',
            token: 'jwt-token',
        );

        $entitled = $client->entitledUsers('trade_signals', range(1, 1200));

        self::assertSame(3, $calls);
        self::assertCount(600, $entitled);
        self::assertSame(2, $entitled[0]);
    }

    public function testGrantAccessSendsReferenceAndDistinguishesReplay(): void
    {
        $created = $this->client('{"access":' . self::ACCESS . '}', 201)->grantAccess(userId: 77, tariffPriceId: 5, reference: 'payment:42', tariffAutoRenewal: true);

        self::assertSame('http://tariffication.test/api/sdk/users/77/accesses', $this->captured['url']);
        self::assertSame(['tariffPriceId' => 5, 'reference' => 'payment:42', 'tariffAutoRenewal' => true], $this->body());
        self::assertFalse($created->replayed);

        $replayed = $this->client('{"access":' . self::ACCESS . '}', 200)->grantAccess(userId: 77, tariffPriceId: 5, reference: 'payment:42');

        self::assertTrue($replayed->replayed);
        self::assertSame('15', $replayed->access->id);
    }

    public function testGrantAccessValidatesReference(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client('{}')->grantAccess(userId: 77, tariffPriceId: 5, reference: str_repeat('x', 129));
    }

    public function testRenewAndRevokeUseAccessId(): void
    {
        $renewed = $this->client('{"access":' . self::ACCESS . '}', 201)->renewAccess(15, 'payment:43');
        self::assertSame('http://tariffication.test/api/sdk/accesses/15/renew', $this->captured['url']);
        self::assertSame(['reference' => 'payment:43'], $this->body());
        self::assertFalse($renewed->replayed);

        $revoked = $this->client('{"access":' . str_replace('"status":"active"', '"status":"revoked"', self::ACCESS) . '}')->revokeAccess(15);
        self::assertSame('http://tariffication.test/api/sdk/accesses/15/revoke', $this->captured['url']);
        self::assertSame(AccessStatus::Revoked, $revoked->status);
    }

    public function testSetAutoRenewalAndDueForRenewal(): void
    {
        $access = $this->client('{"access":' . self::ACCESS . '}')->setAutoRenewal(77, false);
        self::assertSame('PATCH', $this->captured['method']);
        self::assertSame(['tariffAutoRenewal' => false], $this->body());
        self::assertSame('15', $access->id);

        $due = $this->client('{"accesses":[' . self::ACCESS . ']}')->dueForRenewal(new \DateTimeImmutable('2026-12-12 15:00:00'));
        self::assertStringContainsString('date=2026-12-12', $this->captured['url']);
        self::assertCount(1, $due);
    }

    public function testApplicationErrorBecomesApiException(): void
    {
        $this->expectException(TarifficationApiException::class);

        try {
            $this->client('{"error":"no_active_tariff","message":"Подключите тариф"}', 422)->limit(77, 'max_strategies');
        } catch (TarifficationApiException $exception) {
            self::assertSame(TarifficationApiException::NO_ACTIVE_TARIFF, $exception->errorCode);
            self::assertSame(422, $exception->httpStatus);
            self::assertFalse($exception->isNotFound());

            throw $exception;
        }
    }

    public function testNotFoundIsRecognized(): void
    {
        try {
            $this->client('{"error":"tariff_price_not_found","message":"нет"}', 404)->grantAccess(userId: 77, tariffPriceId: 999, reference: 'payment:1');
            self::fail('Ожидалась TarifficationApiException');
        } catch (TarifficationApiException $exception) {
            self::assertTrue($exception->isNotFound());
        }
    }

    public function testErrorWithoutCodeIsRethrownAsHttpClientException(): void
    {
        $this->expectException(ClientExceptionInterface::class);

        $this->client('{"messages":["Forbidden"]}', 403)->tariffs();
    }

    public function testServerErrorIsRethrown(): void
    {
        $this->expectException(ServerExceptionInterface::class);

        $this->client('{}', 500)->tariffs();
    }

    private function client(string $body, int $status = 200): TarifficationClient
    {
        $this->captured = [];

        return new TarifficationClient(
            httpClient: new MockHttpClient(function (string $method, string $url, array $options) use ($body, $status): MockResponse {
                $this->captured = [
                    'method' => $method,
                    'url' => $url,
                    'headers' => $options['headers'] ?? [],
                    'body' => (string) ($options['body'] ?? ''),
                ];

                return new MockResponse($body, ['http_code' => $status, 'response_headers' => ['content-type' => 'application/json']]);
            }),
            baseUrl: 'http://tariffication.test',
            token: 'jwt-token',
        );
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        return json_decode($this->captured['body'], true, 512, JSON_THROW_ON_ERROR);
    }
}
