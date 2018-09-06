<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Block\Payment;

class Info extends \Magento\Payment\Block\Info
{
    protected $_sidInfoFactory;

    public function __construct( \Magento\Framework\View\Element\Template\Context $context,
        \SID\InstantEFT\Model\InfoFactory $sidInfoFactory,
        array $data = [] ) {
        $this->_sidInfoFactory = $sidInfoFactory;
        parent::__construct( $context, $data );
    }

    protected function _prepareSpecificInformation( $transport = null )
    {
        $transport = parent::_prepareSpecificInformation( $transport );
        $payment   = $this->getInfo();
        $sidInfo   = $this->_sidInfoFactory->create();
        if ( !$this->getIsSecureMode() ) {
            $info = $sidInfo->getPaymentInfo( $payment, true );
            return $transport->addData( $info );
        }
        return $transport;
    }
}
