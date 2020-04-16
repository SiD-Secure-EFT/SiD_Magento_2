<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Model;

class Config extends AbstractConfig
{
    const METHOD_CODE = 'sid';
    protected $directoryHelper;
    protected $_storeManager;
    protected $_supportedBuyerCountryCodes = ['ZA'];
    protected $_supportedCurrencyCodes     = ['ZAR'];
    protected $_urlBuilder;
    protected $_assetRepo;

    public function __construct( \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Directory\Helper\Data $directoryHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        array $params = [] ) {
        parent::__construct( $scopeConfig );
        $this->directoryHelper = $directoryHelper;
        $this->_storeManager   = $storeManager;
        $this->_assetRepo      = $assetRepo;
        if ( $params ) {
            $method = array_shift( $params );
            $this->setMethod( $method );
            if ( $params ) {
                $storeId = array_shift( $params );
                $this->setStoreId( $storeId );
            }
        }
    }

    public function isMethodAvailable( $methodCode = null )
    {
        return parent::isMethodAvailable( $methodCode );
    }

    public function getSupportedBuyerCountryCodes()
    {
        return $this->_supportedBuyerCountryCodes;
    }

    public function getMerchantCountry()
    {
        return $this->directoryHelper->getDefaultCountry( $this->_storeId );
    }

    public function isMethodSupportedForCountry( $method = null, $countryCode = null )
    {
        if ( $method === null ) {
            $method = $this->getMethodCode();
        }
        if ( $countryCode === null ) {
            $countryCode = $this->getMerchantCountry();
        }
        return in_array( $method, $this->getCountryMethods( $countryCode ) );
    }

    public function getCountryMethods( $countryCode = null )
    {
        $countryMethods = [
            'other' => [
                self::METHOD_CODE,
            ],
        ];
        if ( $countryCode === null ) {
            return $countryMethods;
        }
        return isset( $countryMethods[$countryCode] ) ? $countryMethods[$countryCode] : $countryMethods['other'];
    }

    public function getPaymentMarkImageUrl()
    {
        return $this->_assetRepo->getUrl( 'SID_InstantEFT::images/logo.png' );
    }

    public function getPaymentMarkWhatIsSID()
    {
        return 'SID Instant EFT';
    }

    public function getPaymentAction()
    {
        $paymentAction = null;
        $action        = $this->getValue( 'paymentAction' );
        switch ( $action ) {
            case self::PAYMENT_ACTION_AUTH:
                $paymentAction = \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE;
                break;
            case self::PAYMENT_ACTION_SALE:
                $paymentAction = \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE_CAPTURE;
                break;
            case self::PAYMENT_ACTION_ORDER:
                $paymentAction = \Magento\Payment\Model\Method\AbstractMethod::ACTION_ORDER;
                break;
        }
        return $paymentAction;
    }

    public function isCurrencyCodeSupported( $code )
    {
        $supported = false;
        if ( in_array( $code, $this->_supportedCurrencyCodes ) ) {
            $supported = true;
        }
        return $supported;
    }

    protected function _getSupportedLocaleCode( $localeCode = null )
    {
        if ( !$localeCode || !in_array( $localeCode, $this->_supportedImageLocales ) ) {
            return 'en_US';
        }
        return $localeCode;
    }

    protected function _mapSIDFieldset( $fieldName )
    {
        return "payment/{$this->_methodCode}/{$fieldName}";
    }

    protected function _getSpecificConfigPath( $fieldName )
    {
        return $this->_mapSIDFieldset( $fieldName );
    }
}
