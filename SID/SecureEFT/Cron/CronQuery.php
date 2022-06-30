<?php
/**
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Cron;

use DateInterval;
use DateTime;
use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Url;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\Generic;
use Magento\Framework\Stdlib\DateTime\DateTime as GmtTime;
use Magento\Framework\Url\Helper\Data;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\OrderNotifier;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Psr\Log\LoggerInterface;
use SID\SecureEFT\Helper\SidConfig;
use SID\SecureEFT\Model\PaymentFactory;
use SID\SecureEFT\Model\SID;
use SID\SecureEFT\Model\SIDResponseHandler;

class CronQuery
{
    protected $_logger;
    protected $_customerSession;
    protected $_checkoutSession;
    protected $_quoteFactory;
    protected $_orderFactory;
    protected $sidSession;
    protected $_urlHelper;
    protected $_customerUrl;
    protected $pageFactory;
    protected $_transactionFactory;
    protected $_paymentMethod;
    protected $_date;
    protected $_sidResponseHandler;
    protected $_orderCollectionFactory;
    protected $_state;
    protected $_sidConfig;
    protected $_paymentFactory;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var Magento\Sales\Model\Order\Payment\Transaction\Builder $_transactionBuilder
     */
    protected $_transactionBuilder;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Magento\Customer\Model\Session $customerSession,
        Session $checkoutSession,
        QuoteFactory $quoteFactory,
        OrderFactory $orderFactory,
        Generic $sidSession,
        Data $urlHelper,
        Url $customerUrl,
        LoggerInterface $logger,
        TransactionFactory $transactionFactory,
        SID $paymentMethod,
        GmtTime $date,
        SIDResponseHandler $sidResponseHandler,
        CollectionFactory $orderCollectionFactory,
        State $state,
        SidConfig $sidConfig,
        Builder $_transactionBuilder,
        PaymentFactory $paymentFactory,
        OrderRepositoryInterface $orderRepository,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->_logger                 = $logger;
        $this->_customerSession        = $customerSession;
        $this->_checkoutSession        = $checkoutSession;
        $this->_quoteFactory           = $quoteFactory;
        $this->_orderFactory           = $orderFactory;
        $this->sidSession              = $sidSession;
        $this->_urlHelper              = $urlHelper;
        $this->_customerUrl            = $customerUrl;
        $this->pageFactory             = $pageFactory;
        $this->_transactionFactory     = $transactionFactory;
        $this->_paymentMethod          = $paymentMethod;
        $this->_date                   = $date;
        $this->_sidResponseHandler     = $sidResponseHandler;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_state                  = $state;
        $this->orderRepository         = $orderRepository;
        $this->_sidConfig              = $sidConfig;
        $this->_paymentFactory         = $paymentFactory;
        $this->_transactionBuilder     = $_transactionBuilder;
        $this->quoteRepository         = $quoteRepository;
    }

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

                $orderIds = array();
                foreach ($ocf as $order) {
                    if ($order->getPayment()->getMethod() == "sid") {
                        $orderIds[] = $order->getId();
                    }
                }

                $this->_logger->info('Orders for cron: ' . json_encode($orderIds));

                foreach ($orderIds as $orderId) {
                    $order                  = $this->orderRepository->get($orderId);
                    $orderquery['orderId']  = $order->getRealOrderId();
                    $orderquery['country']  = $order->getBillingAddress()->getCountryId();
                    $orderquery['currency'] = $order->getOrderCurrencyCode();
                    $orderquery['amount']   = $order->getGrandTotal();

                    $this->doSoapQuery($orderquery);
                }
            }
        );
    }

    public function getQueryResponse($sidWsdl, $soapXml, $header, $justResult = false)
    {
        $queryResponse = curl_init();
        curl_setopt($queryResponse, CURLOPT_URL, $sidWsdl);
        curl_setopt($queryResponse, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($queryResponse, CURLOPT_TIMEOUT, 10);
        curl_setopt($queryResponse, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($queryResponse, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($queryResponse, CURLOPT_HEADER, 0);
        curl_setopt($queryResponse, CURLOPT_POST, true);
        curl_setopt($queryResponse, CURLOPT_POSTFIELDS, $soapXml);
        curl_setopt($queryResponse, CURLOPT_HTTPHEADER, $header);
        $queryResult = curl_exec($queryResponse);
        $queryError  = curl_error($queryResponse);
        curl_close($queryResponse);

        if ( ! $justResult) {
            return ["queryResult" => $queryResult, "queryError" => $queryError];
        } else {
            return $queryResult;
        }
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
