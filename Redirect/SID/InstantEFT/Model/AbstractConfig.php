<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Model;

use Magento\Payment\Model\MethodInterface;
use Magento\Payment\Model\Method\ConfigInterface;
use Magento\Store\Model\ScopeInterface;

abstract class AbstractConfig implements ConfigInterface
{
    const PAYMENT_ACTION_SALE  = 'Sale';
    const PAYMENT_ACTION_AUTH  = 'Authorization';
    const PAYMENT_ACTION_ORDER = 'Order';
    protected $_methodCode;
    protected $_storeId;
    protected $pathPattern;
    public $_scopeConfig;

    public function __construct( \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig )
    {
        $this->_scopeConfig = $scopeConfig;
    }

    protected $methodInstance;
    public function setMethodInstance( $method )
    {
        $this->methodInstance = $method;
        return $this;
    }

    public function setMethod( $method )
    {
        if ( $method instanceof MethodInterface ) {
            $this->_methodCode = $method->getCode();
        } elseif ( is_string( $method ) ) {
            $this->_methodCode = $method;
        }
        return $this;
    }

    public function getMethodCode()
    {
        return $this->_methodCode;
    }

    public function setStoreId( $storeId )
    {
        $this->_storeId = (int) $storeId;
        return $this;
    }

    public function getValue( $key, $storeId = null )
    {
        $underscored = strtolower( preg_replace( '/(.)([A-Z])/', "$1_$2", $key ) );
        $path        = $this->_getSpecificConfigPath( $underscored );
        if ( $path !== null ) {
            $value = $this->_scopeConfig->getValue( $path, ScopeInterface::SCOPE_STORE, $this->_storeId );
            $value = $this->_prepareValue( $underscored, $value );
            return $value;
        }
        return null;
    }

    public function setMethodCode( $methodCode )
    {
        $this->_methodCode = $methodCode;
    }

    public function setPathPattern( $pathPattern )
    {
        $this->pathPattern = $pathPattern;
    }

    protected function _getSpecificConfigPath( $fieldName )
    {
        if ( $this->pathPattern ) {
            return sprintf( $this->pathPattern, $this->_methodCode, $fieldName );
        }
        return "payment/{$this->_methodCode}/{$fieldName}";
    }

    protected function _prepareValue( $key, $value )
    {
        return $value;
    }

    public function isMethodAvailable( $methodCode = null )
    {
        $methodCode = $methodCode ?: $this->_methodCode;
        return $this->isMethodActive( $methodCode );
    }

    public function isMethodActive( $method )
    {
        switch ( $method ) {
            case Config::METHOD_CODE:
                $isEnabled = $this->_scopeConfig->isSetFlag( 'payment/' . Config::METHOD_CODE . '/active', ScopeInterface::SCOPE_STORE, $this->_storeId ) || $this->_scopeConfig->isSetFlag( 'payment/' . Config::METHOD_CODE . '/active', ScopeInterface::SCOPE_STORE, $this->_storeId );
                $method    = Config::METHOD_CODE;
                break;
            default:
                $isEnabled = $this->_scopeConfig->isSetFlag( "payment/{$method}/active", ScopeInterface::SCOPE_STORE, $this->_storeId );
        }
        return $this->isMethodSupportedForCountry( $method ) && $isEnabled;
    }

    public function isMethodSupportedForCountry( $method = null, $countryCode = null )
    {
        return true;
    }
}
