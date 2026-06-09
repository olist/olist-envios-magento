<?php
declare(strict_types=1);

namespace Magento\Quote\Model\Quote\Address\RateResult;

class Method
{
    public function setCarrier(string $code): void {}
    public function setCarrierTitle(string $title): void {}
    public function setMethod(string $code): void {}
    public function setMethodTitle(string $title): void {}
    public function setPrice(float $price): void {}
    public function setCost(float $cost): void {}
}
