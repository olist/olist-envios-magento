<?php
declare(strict_types=1);

namespace Olist\Envios\Block\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

class View extends Template
{
    private const CONFIG_ENABLED = 'carriers/olist_envios/show_on_product_page';

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getAjaxShippingUrl(): string
    {
        return $this->_urlBuilder->getUrl('olist_envios/product/shipping');
    }

    public function getCurrentProduct(): ?Product
    {
        return $this->registry->registry('current_product');
    }

    protected function _toHtml(): string
    {
        if (!$this->_scopeConfig->isSetFlag(self::CONFIG_ENABLED, ScopeInterface::SCOPE_STORE)) {
            return '';
        }

        return parent::_toHtml();
    }
}
