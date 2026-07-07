<?php
declare(strict_types=1);

namespace Olist\Envios\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Olist\Envios\Model\Api\Client;
use Psr\Log\LoggerInterface;

class EnviosOlist extends AbstractCarrier implements CarrierInterface
{
    protected $_code = 'olist_envios';

    private const LBS_TO_KG = 0.453592;
    private const DEFAULT_DIMENSION_CM = 10.0;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory         $rateErrorFactory,
        LoggerInterface      $logger,
        private readonly ResultFactory    $rateResultFactory,
        private readonly MethodFactory    $rateMethodFactory,
        private readonly Client           $apiClient,
        private readonly EncryptorInterface $encryptor,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Called by Magento's shipping collector for every carrier on every rate request.
     * Must never throw — always returns Result or false.
     */
    public function collectRates(RateRequest $request): Result|bool
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        try {
            $postcode = $this->extractPostcode($request);
            $payload  = $this->buildPayload($postcode, $request);
            $data     = $this->apiClient->fetchRates(
                (string) $this->getConfigData('api_url'),
                $this->encryptor->decrypt((string) $this->getConfigData('api_token')),
                $payload,
                (bool) $this->getConfigFlag('debug')
            );

            if ($data === null || empty($data['rates'])) {
                return $this->errorResult();
            }

            return $this->buildResult($data['rates']);

        } catch (\Throwable $e) {
            $this->_logger->error('[Olist Envios] unexpected error in collectRates', [
                'exception' => $e->getMessage(),
            ]);
            return $this->errorResult();
        }
    }

    public function getAllowedMethods(): array
    {
        return [$this->_code => $this->getConfigData('title')];
    }

    // -------------------------------------------------------------------------

    /**
     * Strips non-digits from the destination postcode. No length validation:
     * requests are always forwarded to the API, even with malformed postcodes,
     * so invalid data is visible in the API logs instead of silently dropped here.
     */
    private function extractPostcode(RateRequest $request): string
    {
        return preg_replace('/\D/', '', (string) $request->getDestPostcode());
    }

    private function buildPayload(string $postcode, RateRequest $request): array
    {
        return [
            'destination'    => $postcode,
            'items'          => $this->buildItems($request),
            // Net of discounts: a discounted cart is worth less than the sum
            // of its undiscounted unit prices.
            'declared_value' => (float) $request->getPackageValueWithDiscount(),
        ];
    }

    /**
     * Maps cart items to the API `items` array.
     *
     * Dimensions are sent as DEFAULT_DIMENSION_CM: height/length/width aren't
     * core Magento product attributes, and there's no reliable way to know
     * what unit an arbitrary custom attribute would be stored in.
     *
     * Child items of configurable/grouped products are skipped to avoid
     * double-counting — Magento adds both the parent and its simple child.
     */
    private function buildItems(RateRequest $request): array
    {
        $items = [];

        foreach ((array) $request->getAllItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }

            $items[] = [
                'reference'  => (string) $item->getSku(),
                'unit_price' => (float)  $item->getPrice(),
                'quantity'   => (int)    $item->getQty(),
                'height'     => self::DEFAULT_DIMENSION_CM,
                'length'     => self::DEFAULT_DIMENSION_CM,
                'width'      => self::DEFAULT_DIMENSION_CM,
                'weight'     => $this->normalizeWeightToKg((float) $item->getWeight()),
            ];
        }

        return $items;
    }

    /**
     * Converts a weight value to kilograms based on the store's configured unit.
     * Reads general/locale/weight_unit; handles "kgs" (no-op) and "lbs".
     */
    private function normalizeWeightToKg(float $weight): float
    {
        if ($weight <= 0) {
            return 0.0;
        }

        $unit = (string) $this->_scopeConfig->getValue(
            'general/locale/weight_unit',
            ScopeInterface::SCOPE_STORE
        );

        $kg = $unit === 'lbs' ? $weight * self::LBS_TO_KG : $weight;

        return round($kg, 4);
    }

    /**
     * Converts the API `rates` array into a Magento Result with Method objects.
     * Appends delivery time to the label as "X dias úteis", matching the
     * WooCommerce plugin's display convention.
     */
    private function buildResult(array $rates): Result
    {
        $result = $this->rateResultFactory->create();

        foreach ($rates as $rate) {
            $method = $this->rateMethodFactory->create();

            $serviceCode  = (string) ($rate['service_code']  ?? '');
            $serviceName  = (string) ($rate['service_name']  ?? 'Envios da Olist');
            $price        = (float)  ($rate['price']         ?? 0);
            $deliveryDays = (int)    ($rate['delivery_days'] ?? 0);

            $label = $deliveryDays > 0
                ? sprintf(
                    '%s (%d %s)',
                    $serviceName,
                    $deliveryDays,
                    $deliveryDays === 1 ? 'dia útil' : 'dias úteis'
                )
                : $serviceName;

            $method->setCarrier($this->_code);
            $method->setCarrierTitle((string) $this->getConfigData('title'));
            $method->setMethod($serviceCode);
            $method->setMethodTitle($label);
            $method->setPrice($price);
            $method->setCost($price);

            $result->append($method);
        }

        return $result;
    }

    /**
     * Returns a Result with a single error rate. Shown to the buyer as a
     * "carrier unavailable" message. Used for all failure paths so that
     * checkout is never broken by an API issue.
     */
    private function errorResult(): Result
    {
        $error = $this->_rateErrorFactory->create();
        $error->setCarrier($this->_code);
        $error->setCarrierTitle((string) $this->getConfigData('title'));
        $error->setErrorMessage((string) $this->getConfigData('specificerrmsg'));

        $result = $this->rateResultFactory->create();
        $result->append($error);

        return $result;
    }
}
