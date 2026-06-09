<?php
declare(strict_types=1);

namespace Magento\Quote\Model\Quote\Address\RateResult;

class MethodFactory
{
    public function create(array $data = []): Method
    {
        return new Method();
    }
}
