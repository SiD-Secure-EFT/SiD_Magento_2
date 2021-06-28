<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Helper;

use Magento\Framework\App\Config\BaseFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Data extends AbstractHelper
{
    protected static $_shouldAskToCreateBillingAgreement = false;
    protected $_paymentData;
    private $methodCodes;
    private $configFactory;

    public function __construct(
        Context $context,
        \Magento\Payment\Helper\Data $paymentData,
        BaseFactory $configFactory,
        array $methodCodes
    ) {
        $this->_paymentData  = $paymentData;
        $this->methodCodes   = $methodCodes;
        $this->configFactory = $configFactory;
        parent::__construct($context);
    }

    public function shouldAskToCreateBillingAgreement()
    {
        return self::$_shouldAskToCreateBillingAgreement;
    }

    public function getBillingAgreementMethods($store = null, $quote = null)
    {
        $result = [];
        foreach ($this->_paymentData->getStoreMethods($store, $quote) as $method) {
            if ($method instanceof MethodInterface) {
                $result[] = $method;
            }
        }

        return $result;
    }
}
