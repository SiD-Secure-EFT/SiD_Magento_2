<?php
/**
 * Copyright (c) 2023 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Block\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\OrderFactory;
use SID\SecureEFT\Model\SID;

class Request extends Template
{
    protected $_paymentMethod;
    protected $_orderFactory;
    protected $_checkoutSession;
    protected $readFactory;
    protected $reader;

    public function __construct(
        Context $context,
        OrderFactory $orderFactory,
        Session $checkoutSession,
        ReadFactory $readFactory,
        Reader $reader,
        SID $paymentMethod,
        array $data = []
    ) {
        $this->_orderFactory    = $orderFactory;
        $this->_checkoutSession = $checkoutSession;
        parent::__construct($context, $data);
        $this->_isScopePrivate = true;
        $this->readFactory     = $readFactory;
        $this->reader          = $reader;
        $this->_paymentMethod  = $paymentMethod;
    }

    public function _prepareLayout()
    {
        $this->setMessage('Redirecting to SID')
             ->setId('sid_checkout')
             ->setName('sid_checkout')
             ->setFormMethod('POST')
             ->setFormAction($this->_paymentMethod->getSIDUrl())
             ->setFormData($this->_paymentMethod->getStandardCheckoutFormFields())
             ->setSubmitForm(
                 '<script type="text/javascript">document.getElementById( "sid_checkout" ).submit();</script>'
             );

        return parent::_prepareLayout();
    }
}
