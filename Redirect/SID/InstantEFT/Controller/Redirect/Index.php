<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Controller\Redirect;

require_once __DIR__ . '/../AbstractSID.php';

class Index extends \SID\InstantEFT\Controller\AbstractSID
{
    protected $resultPageFactory;

    public function execute()
    {
        try {
            $data = $this->getRequest()->getParams();
            $this->_logger->debug( __METHOD__ . ' : ' . print_r( $data, true ) );
            $payment_successful = false;
            if ( $this->_sidResponseHandler->validateResponse( $data ) ) {
                if ( $this->_sidResponseHandler->checkResponseAgainstSIDWebQueryService( $data, $this->_date->gmtDate(), false ) ) {
                    $payment_successful = true;
                    $this->_redirect( 'checkout/onepage/success' );
                }
            }
            if ( !$payment_successful ) {
                throw new \Magento\Framework\Exception\LocalizedException( __( 'Your payment was unsuccessful' ) );
                $this->_checkoutSession->restoreQuote();
            }
        } catch ( \Magento\Framework\Exception\LocalizedException $e ) {
            $this->_logger->debug( __METHOD__ . ' : ' . $e->getMessage() );
            $this->messageManager->addExceptionMessage( $e, $e->getMessage() );
            $this->_checkoutSession->restoreQuote();
            $this->_redirect( 'checkout/cart' );
        } catch ( \Exception $e ) {
            $this->_logger->debug( __METHOD__ . ' : ' . $e->getMessage() . '\n' . $e->getTraceAsString() );
            $this->messageManager->addExceptionMessage( $e, __( 'We can\'t start SID Checkout.' ) );
            $this->_checkoutSession->restoreQuote();
            $this->_redirect( 'checkout/cart' );
        }
    }

}
