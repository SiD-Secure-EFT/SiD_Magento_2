<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Model;

class SIDResponseHandler
{
    protected $_order;
    protected $_orderFactory;
    protected $_transactionFactory;
    protected $_paymentFactory;
    protected $_paymentMethod;
    protected $_date;

    public function __construct( \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \SID\InstantEFT\Model\PaymentFactory $paymentFactory,
        \SID\InstantEFT\Model\SID $paymentMethod,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $OrderSender,
        \Magento\Framework\Stdlib\DateTime\DateTime $date
    ) {
        $this->_logger             = $logger;
        $this->_orderFactory       = $orderFactory;
        $this->_paymentMethod      = $paymentMethod;
        $this->invoiceSender       = $invoiceSender;
        $this->_invoiceService     = $invoiceService;
        $this->OrderSender         = $OrderSender;
        $this->_transactionFactory = $transactionFactory;
        $this->_paymentFactory     = $paymentFactory;
        $this->_date               = $date;
    }

    public function validateResponse( $sidResultData )
    {
        $sidError  = false;
        $sidErrMsg = '';
        foreach ( $sidResultData as $key => $val ) {
            $sidResultData[$key] = stripslashes( $val );
        }
        if ( empty( $sidResultData ) ) {
            $sidError  = true;
            $sidErrMsg = 'Data received is empty';
        }
        if ( !$sidError ) {
            $sid_status     = strtoupper( $sidResultData["SID_STATUS"] );
            $sid_merchant   = $sidResultData["SID_MERCHANT"];
            $sid_country    = $sidResultData["SID_COUNTRY"];
            $sid_currency   = $sidResultData["SID_CURRENCY"];
            $sid_reference  = $sidResultData["SID_REFERENCE"];
            $sid_amount     = $sidResultData["SID_AMOUNT"];
            $sid_bank       = $sidResultData["SID_BANK"];
            $sid_date       = $sidResultData["SID_DATE"];
            $sid_receiptno  = $sidResultData["SID_RECEIPTNO"];
            $sid_tnxid      = $sidResultData["SID_TNXID"];
            $sid_custom_01  = $sidResultData["SID_CUSTOM_01"];
            $sid_custom_02  = $sidResultData["SID_CUSTOM_02"];
            $sid_consistent = $sidResultData["SID_CONSISTENT"];

            $sid_secret       = $this->_paymentMethod->getConfigData( 'private_key' );
            $consistent_check = strtoupper( hash( 'sha512', $sid_status . $sid_merchant . $sid_country . $sid_currency
                . $sid_reference . $sid_amount . $sid_bank . $sid_date . $sid_receiptno
                . $sid_tnxid . $sid_custom_01 . $sid_custom_02 . $sid_secret ) );

            if ( $consistent_check != $sid_consistent ) {
                $sidError  = true;
                $sidErrMsg = 'Consistent is invalid.';
            }
            if ( !$sidError && $sid_merchant != $this->_paymentMethod->getConfigData( "merchant_code" ) ) {
                $sidError  = true;
                $sidErrMsg = 'Merchant code received does not match stores merchant code.';
            }
            if ( !$sidError ) {
                if ( !$this->_order ) {
                    $this->_order = $this->_orderFactory->create()->loadByIncrementId( $sid_reference );
                }
                if ( !$this->_order ) {
                    $sidError  = true;
                    $sidErrMsg = 'Order not found.';
                }
                if ( !$sidError ) {
                    if ( (float) $sid_amount != (float) $this->_order->getGrandTotal() ) {
                        $sidError  = true;
                        $sidErrMsg = 'Amount paid does not match order amount.';
                    }
                }
            }
        }
        if ( $sidError ) {
            $this->_logger->debug( __METHOD__ . ' : Error occurred: ' . $sidErrMsg );
            return false;
        }
        return true;
    }

    public function checkResponseAgainstSIDWebQueryService( $sidResultData, $notified = null, $redirected = null )
    {
        $sidError  = false;
        $sidErrMsg = '';
        foreach ( $sidResultData as $key => $val ) {
            $sidResultData[$key] = stripslashes( $val );
        }
        if ( empty( $sidResultData ) ) {
            $sidError  = true;
            $sidErrMsg = 'Data received is empty';
        }
        if ( !$sidError ) {
            $sid_status    = $sidResultData["SID_STATUS"];
            $sid_merchant  = $sidResultData["SID_MERCHANT"];
            $sid_country   = $sidResultData["SID_COUNTRY"];
            $sid_currency  = $sidResultData["SID_CURRENCY"];
            $sid_reference = $sidResultData["SID_REFERENCE"];
            $sid_amount    = $sidResultData["SID_AMOUNT"];
            $sid_username  = $this->_paymentMethod->getConfigData( "username" );
            $sid_password  = $this->_paymentMethod->getConfigData( "password" );

            $xml_string = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                  <soap:Body>
                  <sid_order_query xmlns="http://tempuri.org/"><XML>&lt;?xml version="1.0" encoding="UTF-8"?&gt;&lt;sid_order_query_request&gt;&lt;merchant&gt;&lt;code&gt;' . $sid_merchant . '&lt;/code&gt;&lt;uname&gt;' . $sid_username . '&lt;/uname&gt;&lt;pword&gt;' . $sid_password . '&lt;/pword&gt;&lt;/merchant&gt;&lt;orders&gt;&lt;transaction&gt;&lt;country&gt;' . $sid_country . '&lt;/country&gt;&lt;currency&gt;' . $sid_currency . '&lt;/currency&gt;&lt;amount&gt;' . $sid_amount . '&lt;/amount&gt;&lt;reference&gt;' . $sid_reference . '&lt;/reference&gt;&lt;/transaction&gt;&lt;/orders&gt;&lt;/sid_order_query_request&gt;</XML></sid_order_query>
                  </soap:Body>
                  </soap:Envelope>';
            $header = array(
                "Content-Type: text/xml",
                "Cache-Control: no-cache",
                "Pragma: no-cache",
                "SOAPAction: http://tempuri.org/sid_order_query",
                "Content-length: " . strlen( $xml_string ) );
            $url     = "https://" . $this->_paymentMethod->getSIDHost() . "/api/?wsdl";
            $soap_do = curl_init();
            curl_setopt( $soap_do, CURLOPT_URL, $url );
            curl_setopt( $soap_do, CURLOPT_CONNECTTIMEOUT, 10 );
            curl_setopt( $soap_do, CURLOPT_TIMEOUT, 10 );
            curl_setopt( $soap_do, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $soap_do, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $soap_do, CURLOPT_HEADER, 0 );
            curl_setopt( $soap_do, CURLOPT_POST, true );
            curl_setopt( $soap_do, CURLOPT_POSTFIELDS, $xml_string );
            curl_setopt( $soap_do, CURLOPT_HTTPHEADER, $header );
            $result = curl_exec( $soap_do );
            curl_close( $soap_do );

            $this->_logger->debug( __METHOD__ . ' : ' . $result );

            $soap = simplexml_load_string( $result );
            $soap->registerXPathNamespace( 'ns1', 'http://tempuri.org/' );
            $sid_order_queryResultString = (string) $soap->xpath( '//ns1:sid_order_queryResponse/ns1:sid_order_queryResult[1]' )[0];

            $sid_order_query_response = simplexml_load_string( $sid_order_queryResultString );
            if ( $sid_order_query_response->data->outcome['errorcode'] != "0" ) {
                $sidError  = true;
                $sidErrMsg = $sid_order_query_response->data->outcome['errorcode'] . ' : ' . $sid_order_query_response->data->outcome['errorsolution'];
            }
            if ( !$sidError ) {
                $sidError  = true;
                $sidErrMsg = 'Order not found';
                foreach ( $sid_order_query_response->data->orders->transaction as $transaction ) {
                    if ( $transaction->reference == $sid_reference ) {
                        if (  ( (string) $transaction->status ) != $sid_status ) {
                            $sidErrMsg = 'Status mismatch';
                        }
                        if ( !$sidError && ( (string) $transaction->country->code ) != $sid_country ) {
                            $sidErrMsg = 'Country mismatch';
                        }
                        if ( !$sidError && ( (string) $transaction->currency->code ) != $sid_currency ) {
                            $sidErrMsg = 'Currency mismatch';
                        }
                        if ( !$sidError && ( (string) $transaction->amount ) != $sid_amount ) {
                            $sidErrMsg = 'Amount mismatch';
                        }
                        $dateCreated = null;
                        if ( $transaction->date_created && strlen( (string) $transaction->date_created ) > 3 ) {
                            $dateCreated = strtotime( substr( (string) $transaction->date_created, 0, strlen( $transaction->date_created ) - 3 ) );
                        }
                        $dateReady = null;
                        if ( $transaction->date_ready && strlen( (string) $transaction->date_ready ) > 3 ) {
                            $dateReady = strtotime( substr( (string) $transaction->date_ready, 0, strlen( $transaction->date_ready ) - 3 ) );
                        }
                        $dateCompleted = null;
                        if ( $transaction->date_completed && strlen( (string) $transaction->date_completed ) > 3 ) {
                            $dateCompleted = strtotime( substr( (string) $transaction->date_completed, 0, strlen( $transaction->date_completed ) - 3 ) );
                        }
                        $payment = $this->updatePayment( (string) $sid_order_query_response->data['signature'],
                            (string) $sid_order_query_response->data->outcome['errorcode'],
                            (string) $sid_order_query_response->data->outcome['errordescription'],
                            (string) $sid_order_query_response->data->outcome['errorsolution'],
                            (string) $transaction->status,
                            (string) $transaction->country->code,
                            (string) $transaction->country->name,
                            (string) $transaction->currency->code,
                            (string) $transaction->currency->name,
                            (string) $transaction->currency->symbol,
                            (string) $transaction->bank->name,
                            (float) $transaction->amount,
                            (string) $transaction->reference,
                            (string) $transaction->receiptno,
                            (string) $transaction->tnxid,
                            $dateCreated, $dateReady, $dateCompleted,
                            $redirected, $notified );
                        if ( $payment->getStatus() == 'COMPLETED' ) {
                            $sidError  = false;
                            $sidErrMsg = '';
                            $order     = $this->_orderFactory->create()->loadByIncrementId( $sid_reference );
                            if ( $order->getStatus() === \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT ) {
                                $this->processPayment( $sidResultData, $notified );
                            }
                        } else if ( $payment->getStatus() == 'CANCELLED' ) {
                            $sidErrMsg = 'Payment cancelled';
                        }
                        break;
                    }
                }
            }
        }
        if ( $sidError ) {
            $this->cancelOrder( $sidResultData );
            $this->_logger->debug( __METHOD__ . ' : Error occurred: ' . $sidErrMsg );
            return false;
        }
        return true;
    }

    private function updatePayment( $signature, $errorCode, $errorDescription, $errorSolution, $status,
        $countryCode, $countryName, $currencyCode, $currencyName, $currencySymbol,
        $bankName, $amount, $reference, $receiptNo, $tnxid, $dateCreated, $dateReady,
        $dateCompleted, $redirected, $notified ) {
        $payment = $this->_paymentFactory->create();
        $payment = $payment->load( $tnxid, 'tnxid' );

        $payment->setSignature( $signature );
        $payment->setErrorCode( $errorCode );
        $payment->setErrorDescription( $errorDescription );
        $payment->setErrorSolution( $errorSolution );
        $payment->setStatus( $status );
        $payment->setCountryCode( $countryCode );
        $payment->setCountryName( $countryName );
        $payment->setCurrencyCode( $currencyCode );
        $payment->setCurrencyName( $currencyName );
        $payment->setCurrencySymbol( $currencySymbol );
        $payment->setBankName( $bankName );
        $payment->setAmount( $amount );
        $payment->setReference( $reference );
        $payment->setReceiptNo( $receiptNo );
        $payment->setTnxId( $tnxid );
        $payment->setDateCreated( $dateCreated );
        $payment->setDateReady( $dateReady );
        $payment->setDateCompleted( $dateCompleted );
        if ( !$payment->getTimeStamp() ) {
            $payment->setTimeStamp( $this->_date->gmtDate() );
        }
        if ( $notified ) {
            $payment->setNotified( $notified );
        }
        if ( $redirected ) {
            $payment->setRedirected( $redirected );
        }
        $payment->save();
        return $payment;
    }

    private function processPayment( $sidResultData, $notified )
    {
        $sid_reference = $sidResultData["SID_REFERENCE"];
        if ( !$this->_order ) {
            $this->_order = $this->_orderFactory->create()->loadByIncrementId( $sid_reference );
        }
        if ( $this->_order->getStatus() === \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT ) {
            $sid_status    = strtoupper( $sidResultData["SID_STATUS"] );
            $sid_amount    = $sidResultData["SID_AMOUNT"];
            $sid_bank      = $sidResultData["SID_BANK"];
            $sid_receiptno = $sidResultData["SID_RECEIPTNO"];
            $sid_tnxid     = $sidResultData["SID_TNXID"];

            $status = \Magento\Sales\Model\Order::STATE_PROCESSING;
            if ( $this->_paymentMethod->getConfigData( 'Successful_Order_status' ) != "" ) {
                $status = $this->_paymentMethod->getConfigData( 'Successful_Order_status' );
            }
            $this->_order->setStatus( $status ); //configure the status
            $this->_order->setState( $status )->save(); //try and configure the status
            $this->_order->save();

            $order = $this->_order;

            if ( !$notified ) {
                $order->addStatusHistoryComment( "Redirect Response, Transaction has been approved, SID_TNXID: " . $sid_tnxid )->setIsCustomerNotified( false )->save();
            } else {
                $order->addStatusHistoryComment( "Notify Response, Transaction has been approved, SID_TNXID: " . $sid_tnxid )->setIsCustomerNotified( false )->save();
            }

            $model                  = $this->_paymentMethod;
            $order_successful_email = $model->getConfigData( 'order_email' );

            if ( $order_successful_email != '0' ) {
                $this->OrderSender->send( $order );
                $order->addStatusHistoryComment( __( 'Notified customer about order #%1.', $order->getId() ) )->setIsCustomerNotified( true )->save();
            }

            // Capture invoice when payment is successfull
            $invoice = $this->_invoiceService->prepareInvoice( $order );
            $invoice->setRequestedCaptureCase( \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE );
            $invoice->register();

            // Save the invoice to the order
            $transaction = $this->_transactionFactory->create()
                ->addObject( $invoice )
                ->addObject( $invoice->getOrder() );
            $transaction->save();

            // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
            $send_invoice_email = $model->getConfigData( 'invoice_email' );
            if ( $send_invoice_email != '0' ) {
                $this->invoiceSender->send( $invoice );
                $order->addStatusHistoryComment( __( 'Notified customer about invoice #%1.', $invoice->getId() ) )->setIsCustomerNotified( true )->save();
            }

            $payment = $this->_order->getPayment();
            $payment->setAdditionalInformation( "sid_tnxid", $sid_tnxid );
            $payment->setAdditionalInformation( "sid_receiptno", $sid_receiptno );
            $payment->setAdditionalInformation( "sid_bank", $sid_bank );
            $payment->setAdditionalInformation( "sid_status", $sid_status );
            $payment->registerCaptureNotification( $sid_amount );
            $payment->save();

        } else {
            $this->_logger->debug( __METHOD__ . ' : Order processed already' );
        }
    }

    private function cancelOrder( $sidResultData )
    {
        if ( !$this->_order ) {
            $sid_reference = $sidResultData["SID_REFERENCE"];
            $this->_order  = $this->_orderFactory->create()->loadByIncrementId( $sid_reference );
        }
        if ( $this->_order->getStatus() === \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT ) {
            $sid_status = strtoupper( $sidResultData["SID_STATUS"] );
            $this->_order->addStatusHistoryComment( __( $sid_status ) )->setIsCustomerNotified( false );
            $this->_order->cancel()->save();
        }
    }
}
