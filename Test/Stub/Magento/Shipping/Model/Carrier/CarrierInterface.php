<?php
declare(strict_types=1);

namespace Magento\Shipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;

interface CarrierInterface
{
    public function collectRates(RateRequest $request): mixed;
    public function getAllowedMethods(): array;
}
