<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\InstantEFT\Controller\Redirect;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Index extends \SID\InstantEFT\Controller\AbstractSID
{
    protected $resultPageFactory;
    protected $enableRedirect;

    public function execute()
    {
        $data           = $this->getRequest()->getParams();
        $enableNotify   = $this->_sidConfig->getConfigValue( 'enable_notify' ) == '1';
        $enableRedirect = !$enableNotify;

        if ( $enableRedirect ) {
            try {
                $payment_successful = false;
                if ( $this->_sidResponseHandler->validateResponse( $data ) ) {
                    if ( $this->_sidResponseHandler->checkResponseAgainstSIDWebQueryService( $data,
                        $this->_date->gmtDate(), false ) ) {
                        $payment_successful = true;
                        flush();
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
        } else {
            //Notify enabled. Process enough to redirect to correct page
            try {
                $data = $this->getRequest()->getParams();
                $this->_logger->debug( __METHOD__ . ' : ' . print_r( $data, true ) );
                $payment_successful = false;

                $sentConsistent = $data['SID_CONSISTENT'];
                unset( $data['SID_CONSISTENT'] );
                $consistentString = '';
                foreach ( $data as $d ) {
                    $consistentString .= $d;
                }
                $consistentString .= $this->_sidConfig->getConfigValue( 'private_key' );
                $ourConsistent = strtoupper( hash( 'sha512', $consistentString ) );
                $verified      = hash_equals( $ourConsistent, $sentConsistent );

                if ( $verified && $data['SID_STATUS'] == 'COMPLETED' ) {
                    $payment_successful = true;
                    $this->_redirect( 'checkout/onepage/success' );
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
