<?php
declare(strict_types=1);

namespace Magento\Shipping\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Psr\Log\LoggerInterface;

abstract class AbstractCarrier
{
    protected $_code = '';

    // phpcs:disable PSR2.Classes.PropertyDeclaration
    protected ScopeConfigInterface $_scopeConfig;
    protected LoggerInterface      $_logger;
    protected ErrorFactory         $_rateErrorFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory         $rateErrorFactory,
        LoggerInterface      $logger,
        array                $data = []
    ) {
        $this->_scopeConfig      = $scopeConfig;
        $this->_rateErrorFactory = $rateErrorFactory;
        $this->_logger           = $logger;
    }

    public function getConfigData(string $field, ?int $storeId = null): mixed
    {
        return null;
    }

    public function getConfigFlag(string $key): bool
    {
        return false;
    }
}
