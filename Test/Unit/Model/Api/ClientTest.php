<?php
declare(strict_types=1);

namespace Olist\Envios\Test\Unit\Model\Api;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Olist\Envios\Model\Api\Client;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ClientTest extends TestCase
{
    private MockObject&Curl            $httpClient;
    private MockObject&CacheInterface  $cache;
    private MockObject&Json            $json;
    private MockObject&LoggerInterface $logger;
    private Client                     $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(Curl::class);
        $this->cache      = $this->createMock(CacheInterface::class);
        $this->json       = $this->createMock(Json::class);
        $this->logger     = $this->createMock(LoggerInterface::class);

        $this->client = new Client(
            $this->httpClient,
            $this->cache,
            $this->json,
            $this->logger,
        );
    }

    public function testReturnsCachedDataWithoutHttpCall(): void
    {
        $payload       = ['destination' => '01310100'];
        $serialized    = '{"destination":"01310100"}';
        $cachedData    = ['rates' => [['service_code' => 'PAC', 'price' => 10.0]]];

        $this->json->method('serialize')->with($payload)->willReturn($serialized);
        $this->cache->method('load')->willReturn('CACHED_STRING');
        $this->json->method('unserialize')->with('CACHED_STRING')->willReturn($cachedData);

        $this->httpClient->expects($this->never())->method('post');

        $result = $this->client->fetchRates('https://api.example.com', 'token', $payload);

        $this->assertSame($cachedData, $result);
    }

    public function testMakesHttpPostAndReturnsParsedResponseOnSuccess(): void
    {
        $payload      = ['destination' => '01310100'];
        $responseData = ['rates' => [['service_code' => 'PAC', 'price' => 15.0]]];

        $this->json->method('serialize')->willReturnOnConsecutiveCalls('{}', '{}');
        $this->cache->method('load')->willReturn(false);

        $this->httpClient->expects($this->once())->method('setTimeout')->with(10);
        $this->httpClient->expects($this->exactly(2))->method('addHeader');
        $this->httpClient->expects($this->once())->method('post');
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')->willReturn('{}');

        $this->json->method('unserialize')->willReturn($responseData);
        $this->cache->expects($this->once())->method('save');

        $result = $this->client->fetchRates('https://api.example.com', 'token', $payload);

        $this->assertSame($responseData, $result);
    }

    public function testCachesSuccessfulResponse(): void
    {
        $payload      = ['destination' => '01310100'];
        $responseData = ['rates' => []];

        $this->json->method('serialize')->willReturn('{}');
        $this->cache->method('load')->willReturn(false);
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')->willReturn('{}');
        $this->json->method('unserialize')->willReturn($responseData);

        $this->cache->expects($this->once())
            ->method('save')
            ->with(
                $this->isType('string'),
                $this->stringContains('olist_envios_quote_'),
                $this->containsEqual('olist_envios_quote'),
                300
            );

        $this->client->fetchRates('https://api.example.com', 'token', $payload);
    }

    public function testReturnsNullOnNon200Status(): void
    {
        $this->json->method('serialize')->willReturn('{}');
        $this->cache->method('load')->willReturn(false);
        $this->httpClient->method('getStatus')->willReturn(500);
        $this->httpClient->method('getBody')->willReturn('{}');

        $this->logger->expects($this->once())->method('warning');

        $result = $this->client->fetchRates('https://api.example.com', 'token', []);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenResponseIsNotAnArray(): void
    {
        $this->json->method('serialize')->willReturn('{}');
        $this->cache->method('load')->willReturn(false);
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')->willReturn('"just a string"');
        $this->json->method('unserialize')->willReturn('just a string');

        $result = $this->client->fetchRates('https://api.example.com', 'token', []);

        $this->assertNull($result);
    }

    public function testLogsRequestAndResponseWhenDebugIsTrue(): void
    {
        $this->json->method('serialize')->willReturn('{}');
        $this->cache->method('load')->willReturn(false);
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')->willReturn('{}');
        $this->json->method('unserialize')->willReturn([]);

        $this->logger->expects($this->exactly(2))
            ->method('debug')
            ->with(
                $this->stringContains('[Olist Envios]'),
                $this->isType('array')
            );

        $this->client->fetchRates('https://api.example.com', 'token', [], debug: true);
    }

    public function testDoesNotLogWhenDebugIsFalse(): void
    {
        $this->json->method('serialize')->willReturn('{}');
        $this->cache->method('load')->willReturn(false);
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')->willReturn('{}');
        $this->json->method('unserialize')->willReturn([]);

        $this->logger->expects($this->never())->method('debug');

        $this->client->fetchRates('https://api.example.com', 'token', [], debug: false);
    }

    public function testUrlIsBuiltFromApiUrlAndToken(): void
    {
        $this->json->method('serialize')->willReturn('{}');
        $this->cache->method('load')->willReturn(false);
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')->willReturn('{}');
        $this->json->method('unserialize')->willReturn([]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with(
                'https://api.example.com/v1/freights/channel-quote/magento/?token=my-token',
                $this->anything()
            );

        $this->client->fetchRates('https://api.example.com', 'my-token', []);
    }

    public function testTrailingSlashOnApiUrlIsNormalized(): void
    {
        $this->json->method('serialize')->willReturn('{}');
        $this->cache->method('load')->willReturn(false);
        $this->httpClient->method('getStatus')->willReturn(200);
        $this->httpClient->method('getBody')->willReturn('{}');
        $this->json->method('unserialize')->willReturn([]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with(
                $this->stringStartsWith('https://api.example.com/v1/'),
                $this->anything()
            );

        $this->client->fetchRates('https://api.example.com/', 'token', []);
    }
}
