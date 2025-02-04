<?php
/**
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Payment\Gateway\Config\Config;

abstract class AbstractConfig extends Config
{
    public const PAYMENT_ACTION_SALE  = 'Sale';
    public const PAYMENT_ACTION_AUTH  = 'Authorization';
    public const PAYMENT_ACTION_ORDER = 'Order';
    public $_scopeConfig;
    protected $_methodCode;
    protected $_storeId;
    protected $pathPattern;
    protected $methodInstance;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->_scopeConfig = $scopeConfig;
    }

    public function setMethodInstance($method)
    {
        $this->methodInstance = $method;

        return $this;
    }

    public function setMethod($method)
    {
        if ($method instanceof MethodInterface) {
            $this->_methodCode = $method->getCode();
        } elseif (is_string($method)) {
            $this->_methodCode = $method;
        }

        return $this;
    }

    public function getMethodCode()
    {
        return $this->_methodCode;
    }

    public function setStoreId($storeId)
    {
        $this->_storeId = (int)$storeId;

        return $this;
    }

    public function getValue($field, $storeId = null)
    {
        $underscored = strtolower(preg_replace('/(.)([A-Z])/', "$1_$2", $field));
        $path        = $this->_getSpecificConfigPath($underscored);
        if ($path !== null) {
            $value = $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $this->_storeId);

            return $this->_prepareValue($underscored, $value);
        }

        return null;
    }

    public function setMethodCode($methodCode)
    {
        $this->_methodCode = $methodCode;
    }

    public function setPathPattern($pathPattern)
    {
        $this->pathPattern = $pathPattern;
    }

    public function isMethodAvailable($methodCode = null)
    {
        $methodCode = $methodCode ?: $this->_methodCode;

        return $this->isMethodActive($methodCode);
    }

    public function isMethodActive($method)
    {
        $isEnabled = $this->_scopeConfig->isSetFlag(
            "payment/{$method}/active",
            ScopeInterface::SCOPE_STORE,
            $this->_storeId
        );

        return $this->isMethodSupportedForCountry($method) && $isEnabled;
    }

    public function isMethodSupportedForCountry($method = null, $countryCode = null)
    {
        return true;
    }

    protected function _getSpecificConfigPath($fieldName)
    {
        if ($this->pathPattern) {
            return sprintf($this->pathPattern, $this->_methodCode, $fieldName);
        }

        return "payment/{$this->_methodCode}/{$fieldName}";
    }

    protected function _prepareValue($key, $value)
    {
        return $value;
    }
}
