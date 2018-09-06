<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Controller\Redirect;

use Magento\Framework\Controller\ResultFactory;

class Order extends \SID\InstantEFT\Controller\AbstractSID
{

    public function getOrder( $id )
    {
        return $this->orderRepository->get( $id );
    }

    public function createOrder()
    {
        $quote = $this->setPaymentMethod();

        return $this->onePage->saveOrder();
    }

    public function getQuote()
    {
        return $this->onePage->getQuote();
    }

    public function setPaymentMethod()
    {
        $quote = $this->getQuote();

        $quote->setPaymentMethod( 'sid' );
        $quote->setInventoryProcessed( false );

        // Set Sales Order Payment
        $quote->getPayment()->importData( ['method' => 'sid'] );
        $quote->save(); //Now Save quote and your quote is ready

        // Collect Totals
        $quote->collectTotals();
        return $quote;
    }

    public function execute()
    {
        $order = $this->getOrder( $_POST['order_id'] );

        $resultPage = $this->resultFactory->create( ResultFactory::TYPE_PAGE );
        $formFields = $this->getStandardCheckoutFormFields( $order );
        $response   = $this->resultFactory->create( ResultFactory::TYPE_RAW );
        $response->setHeader( 'Content-type', 'text/plain' );
        $response->setHeader( 'X-Magento-Cache-Control', ' max-age=0, must-revalidate, no-cache, no-store Age: 0' );
        $response->setHeader( 'X-Magento-Cache-Debug', 'MISS' );
        $response->setContents(
            json_encode( $formFields )
        );
        return $response;
    }

    public function getStandardCheckoutFormFields( $order )
    {
        $model         = $this->_paymentMethod;
        $quoteId       = $order->getQuoteId();
        $orderEntityId = $order->getId();
        $address       = $order->getBillingAddress();
        $merchantCode  = $model->getConfigData( 'merchant_code' );
        $privateKey    = $model->getConfigData( 'private_key' );
        $currencyCode  = $order->getOrderCurrencyCode();
        $countryCode   = $address->getCountryId();
        $orderId       = $order->getRealOrderId();
        $orderTotal    = $order->getGrandTotal();
        $consistent    = strtoupper( hash( 'sha512', $merchantCode . $currencyCode . $countryCode . $orderId . $orderTotal . $quoteId . $orderEntityId . $privateKey ) );

        $fields = "";
        $fields = array(
            'SID_MERCHANT'   => $merchantCode,
            'SID_CURRENCY'   => $currencyCode,
            'SID_COUNTRY'    => $countryCode,
            'SID_REFERENCE'  => $orderId,
            'SID_AMOUNT'     => $orderTotal,
            'SID_CUSTOM_01'  => $quoteId,
            'SID_CUSTOM_02'  => $orderEntityId,
            'SID_CONSISTENT' => $consistent,
        );
        return $fields;
    }
}
