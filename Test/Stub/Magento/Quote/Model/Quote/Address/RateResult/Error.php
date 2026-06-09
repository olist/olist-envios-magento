<?php
declare(strict_types=1);

namespace Magento\Quote\Model\Quote\Address\RateResult;

class Error
{
    public function setCarrier(string $code): void {}
    public function setCarrierTitle(string $title): void {}
    public function setErrorMessage(string $message): void {}
}
