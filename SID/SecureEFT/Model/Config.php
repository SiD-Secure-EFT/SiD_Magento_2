<?php
/**
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Model;

use Magento\Directory\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Config extends AbstractConfig
{
    const METHOD_CODE = 'sid';
    protected $directoryHelper;
    protected $_storeManager;
    protected $_supportedBuyerCountryCodes = ['ZA'];
    protected $_supportedCurrencyCodes = ['ZAR'];
    protected $_urlBuilder;
    protected $_assetRepo;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Data $directoryHelper,
        StoreManagerInterface $storeManager,
        Repository $assetRepo,
        LoggerInterface $logger,
        array $params = []
    ) {
        parent::__construct($scopeConfig);
        $this->directoryHelper = $directoryHelper;
        $this->_storeManager   = $storeManager;
        $this->_assetRepo      = $assetRepo;
        $this->logger          = $logger;
        if ($params) {
            $method = array_shift($params);
            $this->setMethod($method);
            if ($params) {
                $storeId = array_shift($params);
                $this->setStoreId($storeId);
            }
        }
    }

    public function isMethodAvailable($methodCode = null)
    {
        return parent::isMethodAvailable($methodCode);
    }

    public function getSupportedBuyerCountryCodes()
    {
        return $this->_supportedBuyerCountryCodes;
    }

    public function getMerchantCountry()
    {
        return $this->directoryHelper->getDefaultCountry($this->_storeId);
    }

    public function isMethodSupportedForCountry($method = null, $countryCode = null)
    {
        if ($method === null) {
            $method = $this->getMethodCode();
        }
        if ($countryCode === null) {
            $countryCode = $this->getMerchantCountry();
        }

        return in_array($method, $this->getCountryMethods($countryCode));
    }

    public function getCountryMethods($countryCode = null)
    {
        $countryMethods = [
            'other' => [
                self::METHOD_CODE,
            ],
        ];
        if ($countryCode === null) {
            return $countryMethods;
        }

        return isset($countryMethods[$countryCode]) ? $countryMethods[$countryCode] : $countryMethods['other'];
    }

    public function getPaymentMarkImageUrl()
    {
        return $this->_assetRepo->getUrl('SID_SecureEFT::images/logo.png');
    }

    public function getPaymentMarkWhatIsSID()
    {
        return 'SID Instant EFT';
    }

    public function getPaymentAction()
    {
        $paymentAction = null;
        $action        = $this->getValue('paymentAction');
        switch ($action) {
            case self::PAYMENT_ACTION_AUTH:
                $paymentAction = AbstractMethod::ACTION_AUTHORIZE;
                break;
            case self::PAYMENT_ACTION_SALE:
                $paymentAction = AbstractMethod::ACTION_AUTHORIZE_CAPTURE;
                break;
            case self::PAYMENT_ACTION_ORDER:
                $paymentAction = AbstractMethod::ACTION_ORDER;
                break;
            default:
                $this->logger->error(
                    "$action is not " . self::PAYMENT_ACTION_AUTH . " or " . self::PAYMENT_ACTION_SALE . " or " . self::PAYMENT_ACTION_ORDER
                );
        }

        return $paymentAction;
    }

    public function isCurrencyCodeSupported($code)
    {
        $supported = false;
        if (in_array($code, $this->_supportedCurrencyCodes)) {
            $supported = true;
        }

        return $supported;
    }

    protected function _getSupportedLocaleCode($localeCode = null)
    {
        if ( ! $localeCode || ! in_array($localeCode, $this->_supportedImageLocales)) {
            return 'en_US';
        }

        return $localeCode;
    }

    protected function _mapSIDFieldset($fieldName)
    {
        return "payment/{$this->_methodCode}/{$fieldName}";
    }

    protected function _getSpecificConfigPath($fieldName)
    {
        return $this->_mapSIDFieldset($fieldName);
    }
}
