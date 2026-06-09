<?php
declare(strict_types=1);

namespace Magento\Shipping\Model\Rate;

class ResultFactory
{
    public function create(array $data = []): Result
    {
        return new Result();
    }
}
