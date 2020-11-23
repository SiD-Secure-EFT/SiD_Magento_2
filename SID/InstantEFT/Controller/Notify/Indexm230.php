<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Controller\Notify;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Indexm230 extends \SID\InstantEFT\Controller\AbstractSID implements CsrfAwareActionInterface
{
    public function execute()
    {
        if ( $this->_sidConfig->getConfigValue( 'enable_notify' ) == '1' ) {
            try {
                if ( $this->_sidResponseHandler->validateResponse( $_POST ) ) {
                    // Get latest status of order before posting in case of multiple responses
                    $sid_reference = $_POST["SID_REFERENCE"];
                    $order         = $this->_orderFactory->create()->loadByIncrementId( $sid_reference );
                    if ( $this->_sidResponseHandler->checkResponseAgainstSIDWebQueryService( $_POST, true,
                        $this->_date->gmtDate() ) ) {
                        $this->_logger->debug( __METHOD__ . ' : Payment Successful' );
                    } else {
                        $this->_logger->debug( __METHOD__ . ' : Payment Unsuccessful' );
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

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException( RequestInterface $request ):  ? InvalidRequestException
    {
        // TODO: Implement createCsrfValidationException() method.
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf( RequestInterface $request ) :  ? bool
    {
        return true;
    }
}