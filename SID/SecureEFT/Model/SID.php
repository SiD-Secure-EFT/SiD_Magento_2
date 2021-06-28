<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Model;

use Magento\Checkout\Model\Session;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedExceptionFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class SID extends AbstractMethod
{
    protected $_code = Config::METHOD_CODE;
    protected $_formBlockType = 'SID\SecureEFT\Block\Form';
    protected $_infoBlockType = 'SID\SecureEFT\Block\Payment\Info';
    protected $_configType = 'SID\SecureEFT\Model\Config';
    protected $_isInitializeNeeded = true;
    protected $_isGateway = false;
    protected $_canOrder = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canVoid = false;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canFetchTransactionInfo = true;
    protected $_canReviewPayment = true;
    protected $_config;
    protected $_isOrderPaymentActionKey = 'is_order_action';
    protected $_authorizationCountKey = 'authorization_count';
    protected $_storeManager;
    protected $_urlBuilder;
    protected $_formKey;
    protected $_checkoutSession;
    protected $_exception;
    protected $transactionRepository;
    protected $transactionBuilder;
    protected $_paymentFactory;
    protected $_invoiceSender;
    protected $_invoiceService;
    protected $_transactionFactory;


    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var Area
     */
    private $state;

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        ConfigFactory $configFactory,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        Session $checkoutSession,
        LocalizedExceptionFactory $exception,
        TransactionRepositoryInterface $transactionRepository,
        BuilderInterface $transactionBuilder,
        OrderRepositoryInterface $orderRepository,
        State $state,
        PaymentFactory $paymentFactory,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        TransactionFactory $transactionFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->_storeManager         = $storeManager;
        $this->_urlBuilder           = $urlBuilder;
        $this->_formKey              = $formKey;
        $this->_checkoutSession      = $checkoutSession;
        $this->_exception            = $exception;
        $this->transactionRepository = $transactionRepository;
        $this->transactionBuilder    = $transactionBuilder;
        $parameters                  = ['params' => [$this->_code]];
        $this->_config               = $configFactory->create($parameters);
        $this->orderRepository       = $orderRepository;
        $this->state                 = $state;
        $this->_paymentFactory       = $paymentFactory;
        $this->_transactionFactory   = $transactionFactory;
        $this->_invoiceService       = $invoiceService;
        $this->_invoiceSender        = $invoiceSender;
    }

    public function setStore($store)
    {
        $this->setData('store', $store);
        if (null === $store) {
            $store = $this->_storeManager->getStore()->getId();
        }
        $this->_config->setStoreId(is_object($store) ? $store->getId() : $store);

        return $this;
    }

    public function canUseForCurrency($currencyCode)
    {
        return $this->_config->isCurrencyCodeSupported($currencyCode);
    }

    public function getConfigPaymentAction()
    {
        return $this->_config->getPaymentAction();
    }

    public function isAvailable(CartInterface $quote = null)
    {
        return parent::isAvailable($quote) && $this->_config->isMethodAvailable();
    }

    public function getStandardCheckoutFormFields()
    {
        $order         = $this->_checkoutSession->getLastRealOrder();
        $quoteId       = $order->getQuoteId();
        $orderEntityId = $order->getId();
        $address       = $order->getBillingAddress();
        $merchantCode  = $this->getConfigData('merchant_code');
        $privateKey    = $this->getConfigData('private_key');
        $currencyCode  = $order->getOrderCurrencyCode();
        $countryCode   = $address->getCountryId();
        $orderId       = $order->getRealOrderId();
        $orderTotal    = $order->getGrandTotal();
        $csfrFormKey   = $this->_formKey->getFormKey();
        $consistent    = strtoupper(
            hash(
                'sha512',
                $merchantCode . $currencyCode . $countryCode . $orderId . $orderTotal . $quoteId . $orderEntityId . $csfrFormKey . $privateKey
            )
        );

        return array(
            'SID_MERCHANT'   => $merchantCode,
            'SID_CURRENCY'   => $currencyCode,
            'SID_COUNTRY'    => $countryCode,
            'SID_REFERENCE'  => $orderId,
            'SID_AMOUNT'     => $orderTotal,
            'SID_CUSTOM_01'  => $quoteId,
            'SID_CUSTOM_02'  => $orderEntityId,
            'SID_CUSTOM_03'  => $csfrFormKey,
            'SID_CONSISTENT' => $consistent,
        );
    }

    // Empty because it's being called by Magento at checkout

    public function getTotalAmount($order)
    {
        if ($this->getConfigData('use_store_currency')) {
            $price = $this->getNumberFormat($order->getGrandTotal());
        } else {
            $price = $this->getNumberFormat($order->getBaseGrandTotal());
        }

        return $price;
    }

    public function getNumberFormat($number)
    {
        return number_format($number, 2, '.', '');
    }

    public function getPaidSuccessUrl()
    {
        return $this->_urlBuilder->getUrl('sid/redirect', array('_secure' => true));
    }

    public function getOrderPlaceRedirectUrl()
    {
        return $this->_urlBuilder->getUrl('sid/redirect/redirect');
    }

    public function getCheckoutRedirectUrl()
    {
        return $this->getOrderPlaceRedirectUrl();
    }

    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);

        return parent::initialize($paymentAction, $stateObject);
    }

    public function getSIDUrl()
    {
        return ('https://' . $this->getSIDHost() . '/paySID/');
    }

    public function getSIDHost()
    {
        return "www.sidpayment.com";
    }

    /**
     * @inheritdoc
     */
    public function fetchTransactionInfo(InfoInterface $payment, $transactionId)
    {
        $state       = ObjectManager::getInstance()->get('\Magento\Framework\App\State');
        $paymentData = array();
        $orderquery  = array();
        if ($state->getAreaCode() == Area::AREA_ADMINHTML) {
            $order_id = $payment->getOrder()->getId();

            $order = $this->orderRepository->get($order_id);

            $orderquery['orderId']       = $order->getRealOrderId();
            $orderquery['country']       = $order->getBillingAddress()->getCountryId();
            $orderquery['currency']      = $order->getOrderCurrencyCode();
            $orderquery['amount']        = $order->getGrandTotal();
            $orderquery['PAYMENT_TITLE'] = "SID_SECURE_EFT";
            $queryResponse               = $this->doSoapQuery($orderquery);
            $queryResult                 = $queryResponse['queryResult'];
            $queryError                  = $queryResponse['queryError'];
            $dataArray                   = array();
            if ( ! $queryError) {
                $soap = simplexml_load_string($queryResult);
                $soap->registerXPathNamespace('ns1', 'http://tempuri.org/');
                $sid_order_queryResultString = (string)$soap->xpath(
                    '//ns1:sid_order_queryResponse/ns1:sid_order_queryResult[1]'
                )[0];


                $sid_order_query_response = simplexml_load_string($sid_order_queryResultString);
                $transaction_details      = $sid_order_query_response->data->orders->transaction;
                foreach ($transaction_details as $transaction) {
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
                    $dataArray['signature']        = (string)$sid_order_query_response->data['signature'];
                    $dataArray['errorcode']        = (string)$sid_order_query_response->data->outcome['errorcode'];
                    $dataArray['errordescription'] = (string)$sid_order_query_response->data->outcome['errordescription'];
                    $dataArray['errorsolution']    = (string)$sid_order_query_response->data->outcome['errorsolution'];
                    $dataArray['status']           = (string)$transaction->status;
                    $dataArray['code']             = (string)$transaction->country->code;
                    $dataArray['name']             = (string)$transaction->country->name;
                    $dataArray['code']             = (string)$transaction->currency->code;
                    $dataArray['name']             = (string)$transaction->currency->name;
                    $dataArray['symbol']           = (string)$transaction->currency->symbol;
                    $dataArray['name']             = (string)$transaction->bank->name;
                    $dataArray['amount']           = (float)$transaction->amount;
                    $dataArray['reference']        = (string)$transaction->reference;
                    $dataArray['receiptno']        = (string)$transaction->receiptno;
                    $dataArray['tnxid']            = (string)$transaction->tnxid;
                    $dataArray['date_created']     = $dateCreated;
                    $dataArray['date_ready']       = $dateReady;
                    $dataArray['date_completed']   = $dateCompleted;
                }
            }
            $this->processApiResponse($order, $queryResponse);
        }

        return $dataArray;
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

    protected function getStoreName()
    {
        return $this->_scopeConfig->getValue(
            'general/store_information/name',
            ScopeInterface::SCOPE_STORE
        );
    }

    protected function _placeOrder(Payment $payment, $amount)
    {
    }

    protected function getOrderTransaction($payment)
    {
        return $this->transactionRepository->getByTransactionType(
            Transaction::TYPE_ORDER,
            $payment->getId(),
            $payment->getOrder()->getId()
        );
    }

    protected function getConfigValue($field)
    {
        return $this->_scopeConfig->getValue(
            "payment/sid/$field",
            ScopeInterface::SCOPE_STORE
        );
    }

    protected function doSoapQuery($orderquery)
    {
        $sidWsdl = 'https://www.sidpayment.com/api/?wsdl';

        $code     = $this->getConfigValue('merchant_code');
        $uname    = $this->getConfigValue('username');
        $password = $this->getConfigValue('password');

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

        //$this->_logger->debug('Query Response: ' . $queryResult);
        return $queryResponse;
    }

    protected function processApiResponse($order, $queryResponse)
    {
        $queryResult = $queryResponse['queryResult'];
        $queryError  = $queryResponse['queryError'];
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
                $order->setStatus($status);
                $order->addStatusHistoryComment('Fetch Transaction Data: Cancelled due to error ' . $sidErrMsg);
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
                    if ($this->getConfigValue('Successful_Order_status') != "") {
                        $status = $this->getConfigValue('Successful_Order_status');
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

                $order_successful_email = $this->getConfigValue('order_email');

                if ($order_successful_email != '0') {
                    $this->OrderSender->send($order);
                    $order->addStatusHistoryComment(
                        __(
                            'Notified customer about order #%1.',
                            $order->getId()
                        )
                    )->setIsCustomerNotified(true)->save();
                }

                // Capture invoice when payment is successfull
                $invoice = $this->_invoiceService->prepareInvoice($order);
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                $invoice->register();

                // Save the invoice to the order
                $transaction = $this->_transactionFactory->create()
                                                         ->addObject($invoice)
                                                         ->addObject($invoice->getOrder());
                $transaction->save();

                // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
                $send_invoice_email = $this->getConfigValue('invoice_email');
                if ($send_invoice_email != '0') {
                    $this->_invoiceSender->send($invoice);
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
                    "Fetch Transaction Query, Transaction has been cancelled, SID_TNXID: " . $sid_tnxid
                )->setIsCustomerNotified(false)->save();
            }
        } else {
            $this->_logger->debug(__METHOD__ . ' : Order processed already');
        }
    }
}
