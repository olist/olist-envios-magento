<?php
declare(strict_types=1);

namespace Magento\Quote\Model\Quote\Address;

class RateRequest
{
    public function getDestPostcode(): ?string { return null; }
    public function getAllItems(): array       { return []; }
    public function getPackageValueWithDiscount(): float { return 0.0; }
}
