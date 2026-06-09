<?php
declare(strict_types=1);

namespace Magento\Framework\App\Config;

interface ScopeConfigInterface
{
    public function getValue(string $path, string $scopeType = 'default', mixed $scopeCode = null): mixed;
    public function isSetFlag(string $path, string $scopeType = 'default', mixed $scopeCode = null): bool;
}
