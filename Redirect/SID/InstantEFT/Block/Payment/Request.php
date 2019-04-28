<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Block\Payment;

class Request extends \Magento\Framework\View\Element\Template
{
    protected $_paymentMethod;
    protected $_orderFactory;
    protected $_checkoutSession;
    protected $readFactory;
    protected $reader;

    public function __construct( \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory,
        \Magento\Framework\Module\Dir\Reader $reader,
        \SID\InstantEFT\Model\SID $paymentMethod,
        array $data = [] ) {
        $this->_orderFactory    = $orderFactory;
        $this->_checkoutSession = $checkoutSession;
        parent::__construct( $context, $data );
        $this->_isScopePrivate = true;
        $this->readFactory     = $readFactory;
        $this->reader          = $reader;
        $this->_paymentMethod  = $paymentMethod;
    }

    public function _prepareLayout()
    {
        $this->setMessage( 'Redirecting to SID' )
            ->setId( 'sid_checkout' )
            ->setName( 'sid_checkout' )
            ->setFormMethod( 'POST' )
            ->setFormAction( $this->_paymentMethod->getSIDUrl() )
            ->setFormData( $this->_paymentMethod->getStandardCheckoutFormFields() )
            ->setSubmitForm( '<script type="text/javascript">document.getElementById( "sid_checkout" ).submit();</script>' );

        return parent::_prepareLayout();
    }
}
