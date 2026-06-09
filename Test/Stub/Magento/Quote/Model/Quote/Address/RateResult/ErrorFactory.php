<?php
declare(strict_types=1);

namespace Magento\Quote\Model\Quote\Address\RateResult;

class ErrorFactory
{
    public function create(array $data = []): Error
    {
        return new Error();
    }
}
