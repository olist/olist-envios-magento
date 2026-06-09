<?php
declare(strict_types=1);

namespace Magento\Framework\App;

interface CacheInterface
{
    public function load(string $identifier): string|false;
    public function save(string $data, string $identifier, array $tags = [], ?int $lifeTime = null): bool;
    public function remove(string $identifier): bool;
    public function clean(string $mode = 'all', array $tags = []): bool;
}
