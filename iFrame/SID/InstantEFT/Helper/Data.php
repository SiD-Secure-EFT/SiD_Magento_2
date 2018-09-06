<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected static $_shouldAskToCreateBillingAgreement = false;
    protected $_paymentData;
    private $methodCodes;
    private $configFactory;

    public function __construct( \Magento\Framework\App\Helper\Context $context,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\BaseFactory $configFactory,
        array $methodCodes ) {
        $this->_paymentData  = $paymentData;
        $this->methodCodes   = $methodCodes;
        $this->configFactory = $configFactory;
        parent::__construct( $context );
    }

    public function shouldAskToCreateBillingAgreement()
    {
        return self::$_shouldAskToCreateBillingAgreement;
    }

    public function getBillingAgreementMethods( $store = null, $quote = null )
    {
        $result = [];
        foreach ( $this->_paymentData->getStoreMethods( $store, $quote ) as $method ) {
            if ( $method instanceof MethodInterface ) {
                $result[] = $method;
            }
        }
        return $result;
    }
}
