<?php
declare(strict_types=1);

namespace Olist\Envios\Block\Product;

use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class ShippingResult extends Template
{
    public function __construct(
        Context $context,
        private readonly PriceHelper $priceHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getRates(): array
    {
        return $this->getData('rates') ?? [];
    }

    public function formatPrice(float $price): string
    {
        return $this->priceHelper->currency($price, true, false);
    }
}
