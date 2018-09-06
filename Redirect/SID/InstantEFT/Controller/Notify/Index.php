<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Controller\Notify;

class Index extends \SID\InstantEFT\Controller\AbstractSID
{
    public function execute()
    {
        try {
            // Give redirect method a chance to process
            sleep( 15 );
            $this->_logger->debug( __METHOD__ . ' : ' . print_r( $_POST, true ) );
            if ( $this->_sidResponseHandler->validateResponse( $_POST ) ) {
                // Get latest status of order before posting in case of multiple responses
                $sid_reference = $_POST["SID_REFERENCE"];
                $order         = $this->_orderFactory->create()->loadByIncrementId( $sid_reference );
                if ( $order->getStatus() === \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT ) {
                    if ( $this->_sidResponseHandler->checkResponseAgainstSIDWebQueryService( $_POST, null, $this->_date->gmtDate() ) ) {
                        $this->_logger->debug( __METHOD__ . ' : Payment Successful' );
                    } else {
                        $this->_logger->debug( __METHOD__ . ' : Payment Unsuccessful' );
                    }
                }
            };
            header( 'HTTP/1.0 200 OK' );
            flush();
        } catch ( \Exception $e ) {
            $this->_logger->debug( __METHOD__ . ' : ' . $e->getMessage() . '\n' . $e->getTraceAsString() );
            $this->messageManager->addExceptionMessage( $e, __( 'We can\'t start SID Checkout.' ) );
            $this->_redirect( 'checkout/cart' );
        }
    }
}
