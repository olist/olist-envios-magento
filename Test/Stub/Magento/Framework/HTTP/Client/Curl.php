<?php
declare(strict_types=1);

namespace Magento\Framework\HTTP\Client;

class Curl
{
    public function setTimeout(int $value): void {}
    public function addHeader(string $name, string $value): void {}
    public function post(string $uri, array|string $params): void {}
    public function getStatus(): int { return 0; }
    public function getBody(): string { return ''; }
}
