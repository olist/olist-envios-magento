<?php
declare(strict_types=1);

namespace Olist\Envios\Controller\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\DataObject;
use Magento\Framework\View\LayoutFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;
use Olist\Envios\Block\Product\ShippingResult;

class Shipping implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawResultFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly QuoteFactory $quoteFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly LayoutFactory $layoutFactory
    ) {}

    public function execute(): Raw
    {
        $postcode  = preg_replace('/\D/', '', (string) $this->request->getParam('postcode'));
        $productId = (int) $this->request->getParam('product');
        $qty       = max(1, (int) $this->request->getParam('qty', 1));

        if (!$postcode || !$productId) {
            return $this->rawResult('');
        }

        try {
            $product = $this->productRepository->getById($productId);

            $quote = $this->quoteFactory->create();
            $quote->setStoreId($this->storeManager->getStore()->getId());
            $quote->setCurrency();
            $quote->addProduct($product, $this->buildBuyRequest($qty));

            $shipping = $quote->getShippingAddress();
            $shipping->setCountryId('BR')
                     ->setPostcode($postcode)
                     ->setCollectShippingRates(true);

            $quote->collectTotals();

            $rates = $shipping->getGroupedAllShippingRates();

            $block = $this->layoutFactory->create()
                ->createBlock(ShippingResult::class)
                ->setTemplate('Olist_Envios::product/view/result.phtml')
                ->setRates($rates);

            return $this->rawResult((string) $block->toHtml());

        } catch (\Throwable) {
            return $this->rawResult('');
        }
    }

    private function buildBuyRequest(int $qty): DataObject
    {
        $params = ['qty' => $qty];

        $superAttribute = $this->request->getParam('super_attribute');
        if ($superAttribute) {
            $params['super_attribute'] = $superAttribute;
        }

        $bundleOption = $this->request->getParam('bundle_option');
        if ($bundleOption) {
            $params['bundle_option']     = $bundleOption;
            $params['bundle_option_qty'] = $this->request->getParam('bundle_option_qty', []);
        }

        return new DataObject($params);
    }

    private function rawResult(string $html): Raw
    {
        $result = $this->rawResultFactory->create();
        $result->setContents($html);
        return $result;
    }
}
