<?php
declare(strict_types=1);

namespace Olist\Envios\Test\Unit\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
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
        $this->result           = $this->createMock(Result::class);
        $this->error            = $this->createMock(Error::class);

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
        float  $weight   = 1.0,
        float  $value    = 100.0,
    ): MockObject&RateRequest {
        $request = $this->createMock(RateRequest::class);
        $request->method('getDestPostcode')->willReturn($postcode);
        $request->method('getAllItems')->willReturn([]);
        $request->method('getPackageWeight')->willReturn($weight);
        $request->method('getPackageHeight')->willReturn(0.0);
        $request->method('getPackageWidth')->willReturn(0.0);
        $request->method('getPackageDepth')->willReturn(0.0);
        $request->method('getPackageValue')->willReturn($value);
        return $request;
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

    public function testPackageWeightIsKeptAsIsWhenUnitIsKgs(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit('kgs');

        $this->apiClient->expects($this->once())
            ->method('fetchRates')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn($p) => $p['package']['weight'] === 2.5),
                $this->anything()
            )
            ->willReturn(null);

        $carrier->collectRates($this->makeRequest('01310100', 2.5));
    }

    public function testPackageWeightIsConvertedFromLbsToKg(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit('lbs');

        $expectedKg = round(2.0 * 0.453592, 4);

        $this->apiClient->expects($this->once())
            ->method('fetchRates')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn($p) => $p['package']['weight'] === $expectedKg),
                $this->anything()
            )
            ->willReturn(null);

        $carrier->collectRates($this->makeRequest('01310100', 2.0));
    }

    public function testZeroWeightIsPassedThroughWithoutConversion(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit('lbs');

        $this->apiClient->expects($this->once())
            ->method('fetchRates')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn($p) => $p['package']['weight'] === 0.0),
                $this->anything()
            )
            ->willReturn(null);

        $carrier->collectRates($this->makeRequest('01310100', 0.0));
    }

    // -------------------------------------------------------------------------
    // Payload structure
    // -------------------------------------------------------------------------

    public function testPayloadContainsDestinationPackageAndItems(): void
    {
        $carrier = $this->makeCarrier($this->activeConfig());
        $this->stubWeightUnit();

        $this->apiClient->expects($this->once())
            ->method('fetchRates')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $p): bool {
                    return isset($p['destination'], $p['package'], $p['items'])
                        && $p['destination'] === '01310100'
                        && $p['package']['declared_value'] === 200.0;
                }),
                $this->anything()
            )
            ->willReturn(null);

        $carrier->collectRates($this->makeRequest('01310100', 1.0, 200.0));
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
