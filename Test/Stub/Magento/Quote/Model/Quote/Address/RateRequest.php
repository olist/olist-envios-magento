<?php
declare(strict_types=1);

namespace Magento\Quote\Model\Quote\Address;

class RateRequest
{
    public function getDestPostcode(): ?string  { return null; }
    public function getAllItems(): array        { return []; }
    public function getPackageWeight(): float  { return 0.0; }
    public function getPackageHeight(): float  { return 0.0; }
    public function getPackageWidth(): float   { return 0.0; }
    public function getPackageDepth(): float   { return 0.0; }
    public function getPackageValue(): float   { return 0.0; }
}
