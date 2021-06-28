<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Cron;

use DateInterval;
use DateTime;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Area;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use SID\SecureEFT\Controller\AbstractSID;

class CronQuery extends AbstractSID
{

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    public function execute()
    {
        $this->_state->emulateAreaCode(
            Area::AREA_FRONTEND,
            function () {
                $cutoffTime = (new DateTime())->sub(new DateInterval('PT10M'))->format('Y-m-d H:i:s');
                $this->_logger->info('Cutoff: ' . $cutoffTime);
                $ocf = $this->_orderCollectionFactory->create();
                $ocf->addAttributeToSelect('entity_id');
                $ocf->addAttributeToFilter('status', ['eq' => 'pending_payment']);
                $ocf->addAttributeToFilter('created_at', ['lt' => $cutoffTime]);
                $ocf->addAttributeToFilter('updated_at', ['lt' => $cutoffTime]);
                $orderIds = $ocf->getData();

                $this->_logger->info('Orders for cron: ' . json_encode($orderIds));

                foreach ($orderIds as $orderId) {
                    $order                  = $this->orderRepository->get($orderId['entity_id']);
                    $orderquery['orderId']  = $order->getRealOrderId();
                    $orderquery['country']  = $order->getBillingAddress()->getCountryId();
                    $orderquery['currency'] = $order->getOrderCurrencyCode();
                    $orderquery['amount']   = $order->getGrandTotal();

                    $this->doSoapQuery($orderquery);
                }
            }
        );
    }

    protected function doSoapQuery($orderquery)
    {
        $sidWsdl = 'https://www.sidpayment.com/api/?wsdl';

        $code     = $this->_sidConfig->getConfigValue('merchant_code');
        $uname    = $this->_sidConfig->getConfigValue('username');
        $password = $this->_sidConfig->getConfigValue('password');

        $soapXml = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><sid_order_query xmlns="http://tempuri.org/"><XML>';
        $xml     = '<?xml version="1.0" encoding="UTF-8"?><sid_order_query_request><merchant><code>' . $code . '</code><uname>' . $uname . '</uname><pword>' . $password . '</pword></merchant><orders>';

        $xml .= '<transaction><country>' . $orderquery['country'] . '</country><currency>' . $orderquery['currency'] . '</currency><amount>' . $orderquery['amount'] . '</amount><reference>' . $orderquery['orderId'] . '</reference></transaction>';

        $xml .= '</orders></sid_order_query_request>';

        $xml = preg_replace(['/</', '/>/'], ['&lt;', '&gt;'], $xml);

        $soapXml .= $xml . '</XML></sid_order_query></soap:Body></soap:Envelope>';

        $this->_logger->debug('Query Request: ' . $soapXml);

        $header        = array(
            "Content-Type: text/xml",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "SOAPAction: http://tempuri.org/sid_order_query",
            "Content-length: " . strlen($soapXml),
        );
        $queryResponse = $this->getQueryResponse($sidWsdl, $soapXml, $header);
        $queryResult   = $queryResponse['queryResult'];
        $queryError    = $queryResponse['queryError'];

        $this->_logger->debug('Query Response: ' . $queryResult);

        if ( ! $queryError) {
            $soap = simplexml_load_string($queryResult);
            $soap->registerXPathNamespace('ns1', 'http://tempuri.org/');
            $sid_order_queryResultString = (string)$soap->xpath(
                '//ns1:sid_order_queryResponse/ns1:sid_order_queryResult[1]'
            )[0];

            $sid_order_query_response = simplexml_load_string($sid_order_queryResultString);
            if ($sid_order_query_response->data->outcome['errorcode'] != "0") {
                $sidError  = true;
                $sidErrMsg = $sid_order_query_response->data->outcome['errorcode'] . ' : ' . $sid_order_query_response->data->outcome['errorsolution'];
                $this->_logger->error('Query error: ' . $sidErrMsg);
                // Set the corresponding order to cancelled
                $status = Order::STATE_CANCELED;
                $order  = $this->_orderFactory->create()->loadByIncrementId($orderquery['orderId']);
                $order->setStatus($status);
                $order->addStatusHistoryComment('Cron Job: Cancelled due to error ' . $sidErrMsg);
                $order->save();
            } else {
                $sidError = false;
            }
            if ( ! $sidError) {
                foreach ($sid_order_query_response->data->orders->transaction as $transaction) {
                    $dateCreated = null;
                    if ($transaction->date_created && strlen((string)$transaction->date_created) > 3) {
                        $dateCreated = strtotime(
                            substr(
                                (string)$transaction->date_created,
                                0,
                                strlen($transaction->date_created) - 3
                            )
                        );
                    }
                    $dateReady = null;
                    if ($transaction->date_ready && strlen((string)$transaction->date_ready) > 3) {
                        $dateReady = strtotime(
                            substr(
                                (string)$transaction->date_ready,
                                0,
                                strlen($transaction->date_ready) - 3
                            )
                        );
                    }
                    $dateCompleted = null;
                    if ($transaction->date_completed && strlen((string)$transaction->date_completed) > 3) {
                        $dateCompleted = strtotime(
                            substr(
                                (string)$transaction->date_completed,
                                0,
                                strlen($transaction->date_completed) - 3
                            )
                        );
                    }
                    $this->updatePayment(
                        (string)$sid_order_query_response->data['signature'],
                        (string)$sid_order_query_response->data->outcome['errorcode'],
                        (string)$sid_order_query_response->data->outcome['errordescription'],
                        (string)$sid_order_query_response->data->outcome['errorsolution'],
                        (string)$transaction->status,
                        (string)$transaction->country->code,
                        (string)$transaction->country->name,
                        (string)$transaction->currency->code,
                        (string)$transaction->currency->name,
                        (string)$transaction->currency->symbol,
                        (string)$transaction->bank->name,
                        (float)$transaction->amount,
                        (string)$transaction->reference,
                        (string)$transaction->receiptno,
                        (string)$transaction->tnxid,
                        $dateCreated,
                        $dateReady,
                        $dateCompleted
                    );
                    $order = $this->_orderFactory->create()->loadByIncrementId($transaction->reference);
                    if ($order->getStatus() === Order::STATE_PENDING_PAYMENT) {
                        $this->processPayment($order, $transaction);
                    }
                }
            }
        } else {
            $this->_logger->error('Query error: ' . $queryError);
        }
    }

    private function updatePayment(
        $signature,
        $errorCode,
        $errorDescription,
        $errorSolution,
        $status,
        $countryCode,
        $countryName,
        $currencyCode,
        $currencyName,
        $currencySymbol,
        $bankName,
        $amount,
        $reference,
        $receiptNo,
        $tnxid,
        $dateCreated,
        $dateReady,
        $dateCompleted,
        $redirected = null,
        $notified = null
    ) {
        $payment = $this->_paymentFactory->create();
        $payment = $payment->load($tnxid, 'tnxid');

        $payment->setSignature($signature);
        $payment->setErrorCode($errorCode);
        $payment->setErrorDescription($errorDescription);
        $payment->setErrorSolution($errorSolution);
        $payment->setStatus($status);
        $payment->setCountryCode($countryCode);
        $payment->setCountryName($countryName);
        $payment->setCurrencyCode($currencyCode);
        $payment->setCurrencyName($currencyName);
        $payment->setCurrencySymbol($currencySymbol);
        $payment->setBankName($bankName);
        $payment->setAmount($amount);
        $payment->setReference($reference);
        $payment->setReceiptNo($receiptNo);
        $payment->setTnxId($tnxid);
        $payment->setDateCreated($dateCreated);
        $payment->setDateReady($dateReady);
        $payment->setDateCompleted($dateCompleted);
        if ( ! $payment->getTimeStamp()) {
            $payment->setTimeStamp($this->_date->gmtDate());
        }
        if ($notified) {
            $payment->setNotified($notified);
        }
        if ($redirected) {
            $payment->setRedirected($redirected);
        }
        $payment->save();

        return $payment;
    }

    private function processPayment($order, $transaction)
    {
        if ($order->getStatus() === Order::STATE_PENDING_PAYMENT) {
            $sid_status    = $transaction->status->__toString();
            $sid_amount    = $transaction->amount->__toString();
            $sid_bank      = $transaction->bank->name->__toString();
            $sid_receiptno = $transaction->receiptno->__toString();
            $sid_tnxid     = $transaction->tnxid->__toString();

            switch ($sid_status) {
                case 'COMPLETED':
                    $status = Order::STATE_PROCESSING;
                    if ($this->_sidConfig->getConfigValue('Successful_Order_status') != "") {
                        $status = $this->_sidConfig->getConfigValue('Successful_Order_status');
                    }
                    break;
                case 'CANCELLED':
                default:
                    $status = Order::STATE_CANCELED;
                    break;
            }

            $order->setStatus($status);
            $order->save();

            if ($sid_status == 'COMPLETED') {
                $order->addStatusHistoryComment(
                    "Cron Query, Transaction has been approved, SID_TNXID: " . $sid_tnxid
                )->setIsCustomerNotified(false)->save();

                $order_successful_email = $this->_sidConfig->getConfigValue('order_email');

                if ($order_successful_email != '0') {
                    $this->_sidResponseHandler->OrderSender->send($order);
                    $order->addStatusHistoryComment(
                        __(
                            'Notified customer about order #%1.',
                            $order->getId()
                        )
                    )->setIsCustomerNotified(true)->save();
                }

                // Capture invoice when payment is successfull
                $invoice = $this->_sidResponseHandler->_invoiceService->prepareInvoice($order);
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                $invoice->register();

                // Save the invoice to the order
                $transaction = $this->_transactionFactory->create()
                                                         ->addObject($invoice)
                                                         ->addObject($invoice->getOrder());
                $transaction->save();

                // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
                $send_invoice_email = $this->_sidConfig->getConfigValue('invoice_email');
                if ($send_invoice_email != '0') {
                    $this->_sidResponseHandler->invoiceSender->send($invoice);
                    $order->addStatusHistoryComment(
                        __(
                            'Notified customer about invoice #%1.',
                            $invoice->getId()
                        )
                    )->setIsCustomerNotified(true)->save();
                }

                $payment = $order->getPayment();
                $payment->setAdditionalInformation("sid_tnxid", $sid_tnxid);
                $payment->setAdditionalInformation("sid_receiptno", $sid_receiptno);
                $payment->setAdditionalInformation("sid_bank", $sid_bank);
                $payment->setAdditionalInformation("sid_status", $sid_status);
                $payment->registerCaptureNotification($sid_amount);
                $payment->save();
            } else {
                $order->addStatusHistoryComment(
                    "Cron Query, Transaction has been cancelled, SID_TNXID: " . $sid_tnxid
                )->setIsCustomerNotified(false)->save();
            }
        } else {
            $this->_logger->debug(__METHOD__ . ' : Order processed already');
        }
    }
}
