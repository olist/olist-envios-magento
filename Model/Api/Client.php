<?php
declare(strict_types=1);

namespace Olist\Envios\Model\Api;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class Client
{
    private const CACHE_TAG   = 'olist_envios_quote';
    private const CACHE_TTL   = 300; // 5 minutes — mirrors the WooCommerce transient TTL
    private const API_TIMEOUT = 10;  // seconds
    private const QUOTE_PATH  = '/v1/freights/magento';

    public function __construct(
        private readonly Curl            $httpClient,
        private readonly CacheInterface  $cache,
        private readonly Json            $json,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Fetches shipping rates from the Envios API.
     *
     * Returns the decoded response array on success, or null on any failure
     * (HTTP error, timeout, invalid JSON). The caller is responsible for
     * checking the returned value before building rate objects.
     *
     * @param string  $apiUrl  Base URL from admin config (e.g. https://envios-api.olist.com)
     * @param string  $token   Integration UUID token
     * @param array   $payload Assembled request body
     * @param bool    $debug   Whether to log the request and response
     */
    public function fetchRates(
        string $apiUrl,
        string $token,
        array  $payload,
        bool   $debug = false
    ): ?array {
        $body     = $this->json->serialize($payload);
        $cacheKey = self::CACHE_TAG . '_' . md5($body);

        $cached = $this->cache->load($cacheKey);
        if ($cached !== false) {
            return $this->json->unserialize($cached);
        }

        $url = rtrim($apiUrl, '/') . self::QUOTE_PATH . '?token=' . urlencode($token);

        $this->httpClient->setTimeout(self::API_TIMEOUT);
        $this->httpClient->addHeader('Content-Type', 'application/json');
        $this->httpClient->addHeader('Accept', 'application/json');

        if ($debug) {
            $this->logger->debug('[Olist Envios] API request', ['url' => $url, 'payload' => $payload]);
        }

        $this->httpClient->post($url, $body);

        $status   = $this->httpClient->getStatus();
        $response = $this->httpClient->getBody();

        if ($debug) {
            $this->logger->debug('[Olist Envios] API response', ['status' => $status, 'body' => $response]);
        }

        if ($status !== 200) {
            $this->logger->warning('[Olist Envios] unexpected HTTP status', ['status' => $status, 'url' => $url]);
            return null;
        }

        $decoded = $this->json->unserialize($response);
        if (!is_array($decoded)) {
            return null;
        }

        $this->cache->save(
            $this->json->serialize($decoded),
            $cacheKey,
            [self::CACHE_TAG],
            self::CACHE_TTL
        );

        return $decoded;
    }
}
