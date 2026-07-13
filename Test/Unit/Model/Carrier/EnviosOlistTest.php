<?php
declare(strict_types=1);

namespace Olist\Envios\Test\Unit\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Item;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Olist\Envios\Model\Api\Client;
use Olist\Envios\Model\Carrier\EnviosOlist;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EnviosOlistTest extends TestCase
{
    private MockObject&ScopeConfigInterface $scopeConfig;
    private MockObject&ErrorFactory         $rateErrorFactory;
    private MockObject&LoggerInterface      $logger;
    private MockObject&ResultFactory        $rateResultFactory;
    private MockObject&MethodFactory        $rateMethodFactory;
    private MockObject&Client               $apiClient;
    private MockObject&EncryptorInterface   $encryptor;
    private MockObject&Result               $result;
    private MockObject&Error                $error;

    protected function setUp(): void
    {
        $this->scopeConfig      = $this->createMock(ScopeConfigInterface::class);
        $this->rateErrorFactory = $this->createMock(ErrorFactory::class);
        $this->logger           = $this->createMock(LoggerInterface::class);
        $this->rateResultFactory = $this->createMock(ResultFactory::class);
        $this->rateMethodFactory = $this->createMock(MethodFactory::class);
        $this->apiClient        = $this->createMock(Client::class);
        $this->encryptor        = $this->createMock(EncryptorInterface::class);
        $this->result           = $this->createMock(Result::class);
        $this->error            = $this->createMock(Error::class);

        $this->encryptor->method('decrypt')->willReturnArgument(0);

        $this->rateResultFactory->method('create')->willReturn($this->result);
        $this->rateErrorFactory->method('create')->willReturn($this->error);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $config */
    private function makeCarrier(array $config = []): MockObject&EnviosOlist
    {
        $carrier = $this->getMockBuilder(EnviosOlist::class)
            ->setConstructorArgs([
                $this->scopeConfig,
                $this->rateErrorFactory,
                $this->logger,
                $this->rateResultFactory,
                $this->rateMethodFactory,
                $this->apiClient,
                $this->encryptor,
            ])
            ->onlyMethods(['getConfigFlag', 'getConfigData'])
            ->getMock();

        $carrier->method('getConfigFlag')
            ->willReturnCallback(fn(string $key) => (bool) ($config[$key] ?? false));

        $carrier->method('getConfigData')
            ->willReturnCallback(fn(string $key) => $config[$key] ?? null);

        return $carrier;
    }

    private function makeRequest(
        string $postcode = '01310100',
        float  $value    = 100.0,
    ): MockObject&RateRequest {
        $request = $this->createMock(RateRequest::class);
        $request->method('getDestPostcode')->willReturn($postcode);
        $request->method('getAllItems')->willReturn([]);
        $request->method('getPackageValueWithDiscount')->willReturn($value);
        return $request;
    }

    private function makeItem(
        string $sku    = 'SKU-1',
        float  $price  = 10.0,
        float  $qty    = 1.0,
        float  $weight = 1.0,
    ): MockObject&Item {
        $item = $this->createMock(Item::class);
        $item->method('getParentItemId')->willReturn(null);
        $item->method('getSku')->willReturn($sku);
        $item->method('getPrice')->willReturn($price);
        $item->method('getQty')->willReturn($qty);
        $item->method('getWeight')->willReturn($weight);
        return $item;
    }

    private function stubWeightUnit(string $unit = 'kgs'): void
    {
        $this->scopeConfig->method('getValue')
            ->with('general/locale/weight_unit', ScopeInterface::SCOPE_STORE)
            ->willReturn($unit);
    }

    private function activeConfig(): array
    {
        return [
            'active'    => true,
            'api_url'   => 'https://api.example.com',
            'api_token' => 'test-token',
            'debug'     => false,
            'title'     => 'Envios Olist',
            'specificerrmsg' => 'Frete indisponível',
        ];
    }

    // -------------------------------------------------------------------------
    // collectRates — inactive / config guard
    // -------------------------------------------------------------------------

    public function testReturnsFalseWhenCarrierIsInactive(): void
    {
        $carrier = $this->makeCarrier(['active' => false]);

        $this->assertFalse($carrier->collectRates($this->makeRequest()));
    }

    // -------------------------------------------------------------------------
    // collectRates — invalid postcodes are still forwarded to the API
    // -------------------------------------------------------------------------

    public function testForwardsRequestToApiWhenPostcodeIsNull(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();
        $request = $this->createMock(RateRequest::class);
        $request->method('getDestPostcode')->willReturn(null);

        $this->apiClient->expects($this->once())
            ->method('fetchRates')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn($p) => $p['destination'] === ''),
                $this->anything()
            )
            ->willReturn(null);

        $this->assertSame($this->result, $carrier->collectRates($request));
    }

    public function testForwardsRequestToApiWhenPostcodeIsEmpty(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();

        $this->apiClient->expects($this->once())
            ->method('fetchRates')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn($p) => $p['destination'] === ''),
                $this->anything()
            )
            ->willReturn(null);

        $this->assertSame($this->result, $carrier->collectRates($this->makeRequest('')));
    }

    public function testForwardsRequestToApiWhenPostcodeIsTooShort(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();

        // 7 digits — one short of the previously-required 8, still sent as-is
        $this->apiClient->expects($this->once())
            ->method('fetchRates')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn($p) => $p['destination'] === '0131010'),
                $this->anything()
            )
            ->willReturn(null);

        $this->assertSame($this->result, $carrier->collectRates($this->makeRequest('0131010')));
    }

    public function testStripsNonDigitsFromPostcode(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();

        $this->apiClient->expects($this->once())
            ->method('fetchRates')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn($p) => $p['destination'] === '01310100'),
                $this->anything()
            )
            ->willReturn(null);

        $carrier->collectRates($this->makeRequest('01310-100'));
    }

    // -------------------------------------------------------------------------
    // collectRates — API response handling
    // -------------------------------------------------------------------------

    public function testReturnsErrorWhenApiReturnsNull(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();
        $this->apiClient->method('fetchRates')->willReturn(null);

        $this->assertSame($this->result, $carrier->collectRates($this->makeRequest()));
    }

    public function testReturnsErrorWhenRatesAreEmpty(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();
        $this->apiClient->method('fetchRates')->willReturn(['rates' => []]);

        $this->assertSame($this->result, $carrier->collectRates($this->makeRequest()));
    }

    public function testReturnsErrorWhenRatesKeyIsMissing(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();
        $this->apiClient->method('fetchRates')->willReturn([]);

        $this->assertSame($this->result, $carrier->collectRates($this->makeRequest()));
    }

    // -------------------------------------------------------------------------
    // collectRates — happy path
    // -------------------------------------------------------------------------

    public function testBuildsRateMethodForEachApiRate(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();

        $method = $this->createMock(Method::class);
        $this->rateMethodFactory->method('create')->willReturn($method);

        $this->apiClient->method('fetchRates')->willReturn([
            'rates' => [
                ['service_code' => 'PAC', 'service_name' => 'PAC', 'price' => 15.0, 'delivery_days' => 5],
                ['service_code' => 'SEDEX', 'service_name' => 'SEDEX', 'price' => 30.0, 'delivery_days' => 2],
            ],
        ]);

        $this->result->expects($this->exactly(2))->method('append')->with($method);

        $actual = $carrier->collectRates($this->makeRequest());
        $this->assertSame($this->result, $actual);
    }

    public function testSetsMethodPriceFromApiRate(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();

        $method = $this->createMock(Method::class);
        $this->rateMethodFactory->method('create')->willReturn($method);

        $this->apiClient->method('fetchRates')->willReturn([
            'rates' => [['service_code' => 'PAC', 'service_name' => 'PAC', 'price' => 19.99, 'delivery_days' => 0]],
        ]);

        $method->expects($this->once())->method('setPrice')->with(19.99);
        $method->expects($this->once())->method('setCost')->with(19.99);

        $carrier->collectRates($this->makeRequest());
    }

    // -------------------------------------------------------------------------
    // Method title / delivery days label
    // -------------------------------------------------------------------------

    public function testMethodTitleIncludesPluralDaysLabel(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();

        $method = $this->createMock(Method::class);
        $this->rateMethodFactory->method('create')->willReturn($method);

        $this->apiClient->method('fetchRates')->willReturn([
            'rates' => [['service_code' => 'PAC', 'service_name' => 'PAC', 'price' => 10.0, 'delivery_days' => 5]],
        ]);

        $method->expects($this->once())->method('setMethodTitle')->with('PAC (5 dias úteis)');

        $carrier->collectRates($this->makeRequest());
    }

    public function testMethodTitleUsesSingularForOneDay(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();

        $method = $this->createMock(Method::class);
        $this->rateMethodFactory->method('create')->willReturn($method);

        $this->apiClient->method('fetchRates')->willReturn([
            'rates' => [['service_code' => 'SEDEX', 'service_name' => 'SEDEX', 'price' => 25.0, 'delivery_days' => 1]],
        ]);

        $method->expects($this->once())->method('setMethodTitle')->with('SEDEX (1 dia útil)');

        $carrier->collectRates($this->makeRequest());
    }

    public function testMethodTitleOmitsDaysWhenZero(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();

        $method = $this->createMock(Method::class);
        $this->rateMethodFactory->method('create')->willReturn($method);

        $this->apiClient->method('fetchRates')->willReturn([
            'rates' => [['service_code' => 'PAC', 'service_name' => 'Frete Grátis', 'price' => 0.0, 'delivery_days' => 0]],
        ]);

        $method->expects($this->once())->method('setMethodTitle')->with('Frete Grátis');

        $carrier->collectRates($this->makeRequest());
    }

    public function testMethodTitleFallsBackToDefaultNameWhenMissing(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();

        $method = $this->createMock(Method::class);
        $this->rateMethodFactory->method('create')->willReturn($method);

        $this->apiClient->method('fetchRates')->willReturn([
            'rates' => [['price' => 10.0]],
        ]);

        $method->expects($this->once())->method('setMethodTitle')->with('Envios da Olist');

        $carrier->collectRates($this->makeRequest());
    }

    // -------------------------------------------------------------------------
    // collectRates — error handling
    // -------------------------------------------------------------------------

    public function testLogsErrorAndReturnsErrorResultOnException(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();

        $this->apiClient->method('fetchRates')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $this->logger->expects($this->once())->method('error')
            ->with(
                $this->stringContains('[Olist Envios]'),
                $this->arrayHasKey('exception')
            );

        $actual = $carrier->collectRates($this->makeRequest());
        $this->assertSame($this->result, $actual);
    }

    // -------------------------------------------------------------------------
    // getAllowedMethods
    // -------------------------------------------------------------------------

    public function testGetAllowedMethodsReturnsCarrierCodeAndTitle(): void
    {
        $carrier = $this->makeCarrier(['title' => 'Envios Olist']);

        $this->assertSame(['olist_envios' => 'Envios Olist'], $carrier->getAllowedMethods());
    }

    // -------------------------------------------------------------------------
    // Weight conversion
    // -------------------------------------------------------------------------

    public function testItemWeightIsKeptAsIsWhenUnitIsKgs(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit('kgs');
        $request = $this->createMock(RateRequest::class);
        $request->method('getDestPostcode')->willReturn('01310100');
        $request->method('getPackageValueWithDiscount')->willReturn(100.0);
        $request->method('getAllItems')->willReturn([$this->makeItem(weight: 2.5)]);

        $this->apiClient->expects($this->once())
            ->method('fetchRates')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn($p) => $p['items'][0]['weight'] === 2.5),
                $this->anything()
            )
            ->willReturn(null);

        $carrier->collectRates($request);
    }

    public function testItemWeightIsConvertedFromLbsToKg(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit('lbs');
        $request = $this->createMock(RateRequest::class);
        $request->method('getDestPostcode')->willReturn('01310100');
        $request->method('getPackageValueWithDiscount')->willReturn(100.0);
        $request->method('getAllItems')->willReturn([$this->makeItem(weight: 2.0)]);

        $expectedKg = round(2.0 * 0.453592, 4);

        $this->apiClient->expects($this->once())
            ->method('fetchRates')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn($p) => $p['items'][0]['weight'] === $expectedKg),
                $this->anything()
            )
            ->willReturn(null);

        $carrier->collectRates($request);
    }

    public function testZeroWeightIsPassedThroughWithoutConversion(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit('lbs');
        $request = $this->createMock(RateRequest::class);
        $request->method('getDestPostcode')->willReturn('01310100');
        $request->method('getPackageValueWithDiscount')->willReturn(100.0);
        $request->method('getAllItems')->willReturn([$this->makeItem(weight: 0.0)]);

        $this->apiClient->expects($this->once())
            ->method('fetchRates')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn($p) => $p['items'][0]['weight'] === 0.0),
                $this->anything()
            )
            ->willReturn(null);

        $carrier->collectRates($request);
    }

    // -------------------------------------------------------------------------
    // Item dimensions
    // -------------------------------------------------------------------------

    public function testItemDimensionsAlwaysUseDefault(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();
        $request = $this->createMock(RateRequest::class);
        $request->method('getDestPostcode')->willReturn('01310100');
        $request->method('getPackageValueWithDiscount')->willReturn(100.0);
        $request->method('getAllItems')->willReturn([$this->makeItem()]);

        $this->apiClient->expects($this->once())
            ->method('fetchRates')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $p): bool {
                    $item = $p['items'][0];
                    return $item['height'] === 10.0 && $item['width'] === 10.0 && $item['length'] === 10.0;
                }),
                $this->anything()
            )
            ->willReturn(null);

        $carrier->collectRates($request);
    }

    // -------------------------------------------------------------------------
    // Payload structure
    // -------------------------------------------------------------------------

    public function testPayloadContainsDestinationItemsAndDeclaredValue(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();

        $this->apiClient->expects($this->once())
            ->method('fetchRates')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $p): bool {
                    return isset($p['destination'], $p['items'], $p['declared_value'])
                        && $p['destination'] === '01310100'
                        && $p['declared_value'] === 200.0;
                }),
                $this->anything()
            )
            ->willReturn(null);

        $carrier->collectRates($this->makeRequest('01310100', 200.0));
    }

    public function testApiIsCalledWithConfiguredUrlAndToken(): void
    {
        $config = array_merge($this->activeConfig(), [
            'api_url'   => 'https://envios-api.olist.com',
            'api_token' => 'uuid-token-123',
        ]);
        $carrier = $this->makeCarrier($config);
        $this->stubWeightUnit();

        $this->apiClient->expects($this->once())
            ->method('fetchRates')
            ->with('https://envios-api.olist.com', 'uuid-token-123', $this->anything(), false)
            ->willReturn(null);

        $carrier->collectRates($this->makeRequest());
    }
}
