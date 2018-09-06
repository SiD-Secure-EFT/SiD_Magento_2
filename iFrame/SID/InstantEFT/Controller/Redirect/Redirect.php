<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Controller\Redirect;

class Redirect extends \SID\InstantEFT\Controller\AbstractSID
{
    protected $resultPageFactory;
    protected $_configMethod = \SID\InstantEFT\Model\Config::METHOD_CODE;

    public function execute()
    {
        $page_object = $this->pageFactory->create();
        try {
            $this->_initCheckout();
        } catch ( \Magento\Framework\Exception\LocalizedException $e ) {
            $this->_logger->debug( __METHOD__ . ' : ' . $e->getMessage() );
            $this->messageManager->addExceptionMessage( $e, $e->getMessage() );
            $this->_redirect( 'checkout/cart' );
        } catch ( \Exception $e ) {
            $this->_logger->debug( __METHOD__ . ' : ' . $e->getMessage() );
            $this->messageManager->addExceptionMessage( $e, __( 'We can\'t start SID Checkout.' ) );
            $this->_redirect( 'checkout/cart' );
        }
        return $page_object;
    }
}
