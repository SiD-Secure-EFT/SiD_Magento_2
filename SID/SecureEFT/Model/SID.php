<?php
/**
 * Copyright (c) 2025 Payfast (Pty) Ltd
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
use Magento\Framework\DataObject;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\LocalizedExceptionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Payment\Model\MethodInterface;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentMethodInterface;
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
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use SID\SecureEFT\Helper\Data as SidHelper;
use SID\SecureEFT\Helper\SidAPI;
use Magento\Payment\Model\Method\AbstractMethodAdapter;


class SID extends AbstractExtensibleModel implements MethodInterface, PaymentMethodInterface
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
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;

    /**
     * @var DateTime
     */
    protected $_date;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var Area
     */
    private $state;
    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $_scopeConfig;

    public const API_BASE = "www.sidpayment.com";
    private SidHelper $_sidHelper;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \SID\SecureEFT\Model\ConfigFactory $configFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Data\Form\FormKey $formKey
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\Exception\LocalizedExceptionFactory $exception
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\App\State $state
     * @param \SID\SecureEFT\Model\PaymentFactory $paymentFactory
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param \SID\SecureEFT\Helper\Data $sidhelper
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
     * @param ScopeConfigInterface $_scopeConfig
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
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
        SidHelper $sidhelper,
        DateTime $date,
        ScopeConfigInterface $_scopeConfig,
        ManagerInterface $_eventManager,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
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
        $this->_sidHelper            = $sidhelper;
        $this->_date                 = $date;
        $this->_scopeConfig          = $_scopeConfig;
        $this->_eventManager         = $_eventManager;
    }

    /**
     * @inheritDoc
     */
    public function setStore($storeId)
    {
        $this->setData('store', $storeId);
        if (null == $storeId) {
            $storeId = $this->_storeManager->getStore()->getId();
        }
        $this->_config->setStoreId(is_object($storeId) ? $storeId->getId() : $storeId);
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
        return $this->_config->isMethodAvailable();
    }

    public function getStandardCheckoutFormFields()
    {
        $Payment       = array();
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

        $data = array(
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

        $Payment['reference']       = $orderId;
        $Payment['txn_id']          = $orderId;
        $Payment['additional_data'] = $data;

        $this->_sidHelper->createTransaction($order, $Payment);

        return $data;
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

        return $this;
    }

    public function getSIDUrl()
    {
        return ('https://' . $this->getSIDHost() . '/paySID/');
    }

    public function getSIDHost()
    {
        return self::API_BASE;
    }

    /**
     * @inheritdoc
     */
    public function fetchTransactionInfo(InfoInterface $payment, $transactionId): array
    {
        $state = ObjectManager::getInstance()->get('\Magento\Framework\App\State');

        if ($state->getAreaCode() == Area::AREA_ADMINHTML) {
            $order_id = $payment->getOrder()->getId();

            $order = $this->orderRepository->get($order_id);

            $sidAPI = $this->initialiseSidApi($payment);

            $transaction = $sidAPI->retrieveTransaction();

            if ($transaction) {
                $dataArray = array();

                $dateCreated = null;
                if (strlen($transaction->dateCreated ?? "") > 3) {
                    $dateCreated = strtotime(
                        substr(
                            (string)$transaction->dateCreated,
                            0,
                            strlen($transaction->dateCreated) - 3
                        )
                    );
                }
                $dateCompleted = null;
                if (strlen($transaction->dateCompleted ?? "") > 3) {
                    $dateCompleted = strtotime(
                        substr(
                            (string)$transaction->dateCompleted,
                            0,
                            strlen($transaction->dateCompleted) - 3
                        )
                    );
                }
                $dataArray['status']          = $transaction->status;
                $dataArray['country_code']    = $transaction->country;
                $dataArray['currency_symbol'] = $transaction->currency;
                $dataArray['bank_name']       = $transaction->bank;
                $dataArray['amount']          = (float)$transaction->amount;
                $dataArray['reference']       = $transaction->transactionId;
                $dataArray['date_created']    = $dateCreated;
                $dataArray['date_completed']  = $dateCompleted;
                $dataArray['receipt_no']      = $payment->getAdditionalInformation()["sid_receiptno"] ?? "";
                $dataArray['tnxid']           = $payment->getAdditionalInformation()["sid_tnxid"] ?? "";

                $this->validateOrder($order);
            } else {
                throw new LocalizedException(__("Transaction not found!"));
            }
        }

        return $dataArray ?? [];
    }

    /**
     * @param $order
     *
     * @return void
     */
    public function validateOrder($order): void
    {
        $payment = $order->getPayment();

        $sidAPI = $this->initialiseSidApi($payment);

        $transaction = $sidAPI->retrieveTransaction();

        $this->updatePayment($transaction, $payment);

        if ($order->getStatus() === Order::STATE_PENDING_PAYMENT) {
            $this->processPayment($order, $transaction);
        }
    }

    protected function getStoreName()
    {
        return $this->_scopeConfig->getValue(
            'general/store_information/name',
            ScopeInterface::SCOPE_STORE
        );
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

    private function updatePayment($transaction, $payment): void
    {
        $payment->setStatus($transaction->status);
        $payment->setCountryCode($transaction->country);
        $payment->setCurrencyCode($transaction->currency);
        $payment->setBankName($transaction->bank);
        $payment->setAmount($transaction->amount);
        $payment->setReference($transaction->transactionId);
        $payment->setDateCreated($transaction->dateCreated);
        $payment->setDateCompleted($transaction->dateCompleted);

        if (!$payment->getTimeStamp()) {
            $payment->setTimeStamp($this->_date->gmtDate());
        }
        $payment->save();
    }

    private function processPayment($order, $transaction)
    {
        if ($order->getStatus() === Order::STATE_PENDING_PAYMENT) {
            $sid_status = $transaction->status;
            $sid_amount = $transaction->amount;
            $sid_bank   = $transaction->bank;
            $sid_tnxid  = $transaction->transactionId;

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
                    $this->_sidHelper->sendOrderConfirmation($order);
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

    /**
     * Check refund availability
     *
     * @return bool
     */
    public function canRefund()
    {
        return true;
    }

    /**
     * Refund specified amount for payment
     *
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return bool
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function refund(InfoInterface $payment, $amount)
    {
        $sidRefundAPI = $this->initialiseSidApi($payment);

        $transactionRetrieval = $sidRefundAPI->retrieveTransaction() ?? null;

        if (!$transactionRetrieval) {
            throw new LocalizedException(__("Transaction not found!"));
        }

        $this->_logger->info("\n\nTRANSACTION: " . json_encode($transactionRetrieval));

        $transactionId = $transactionRetrieval->transactionId;
        $refund        = $sidRefundAPI->processRefund($transactionId, $amount);
        $this->_logger->info("\n\nREFUND: " . json_encode($refund));

        if (!isset($refund->refundStatus)) {
            throw new LocalizedException(__($refund->message));
        } elseif ($refund->refundStatus === "Pending"
                  || $refund->refundStatus === "partial"
                  || $refund->refundStatus === "refunded"
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param $payment
     *
     * @return SidAPI
     */
    public function initialiseSidApi($payment): SidAPI
    {
        $uname    = $this->getConfigValue('username') ?? "";
        $password = $this->getConfigValue('password') ?? "";

        $order_id = $payment->getOrder()->getId();

        $order = $this->orderRepository->get($order_id);

        $queryData = [
            "sellerReference" => $order->getRealOrderId(),
            "startDate"       => strtok($order->getCreatedAt("yyyy-mm-d"), " "),
            "endDate"         => date("Y-m-d")
        ];

        return new SidAPI($queryData, $uname, $password);
    }

    /**
     * Check partial refund availability for invoice
     *
     * @return bool
     * @api
     */
    public function canRefundPartialPerInvoice()
    {
        return $this->canRefund();
    }

    /**
     * Gets gateway code
     *
     * @return SID|string
     */
    public function getCode()
    {
        return $this->_code;
    }

    /**
     * Gets gateway form block type
     *
     * @return string
     */
    public function getFormBlockType()
    {
        return $this->_formBlockType;
    }

    /**
     * Gets gateway title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getConfigData('title');
    }

    /**
     * Gets gateway store name
     *
     * @return mixed
     */
    public function getStore()
    {
        return $this->getStoreName();
    }

    /**
     * Gateway can order attribute
     *
     * @return bool
     */
    public function canOrder()
    {
        return $this->_canOrder;
    }

    /**
     * Gateway can authorize attribute
     *
     * @return bool
     */
    public function canAuthorize()
    {
        return $this->_canAuthorize;
    }

    /**
     * Gateway can capture attribute
     *
     * @return bool
     */
    public function canCapture()
    {
        return $this->_canCapture;
    }

    /**
     * Gateway can capture partial attribute
     *
     * @return bool
     */
    public function canCapturePartial()
    {
        return $this->_canCapture;
    }

    /**
     * Gateway can capture once attribute
     *
     * @return bool
     */
    public function canCaptureOnce()
    {
        return $this->_canCapture;
    }

    /**
     * Gateway can void attribute
     *
     * @return bool
     */
    public function canVoid()
    {
        return $this->_canVoid;
    }

    /**
     * Gateway can use internal attribute
     *
     * @return bool
     */
    public function canUseInternal()
    {
        return $this->_canUseInternal;
    }

    /**
     * Gateway can use checkout attribute
     *
     * @return bool
     */
    public function canUseCheckout()
    {
        return $this->_canUseCheckout;
    }

    /**
     * Gateway can edit attribute
     *
     * @return false
     */
    public function canEdit()
    {
        return true;
    }

    /**
     * Gateway can transaction fetch info attribute
     *
     * @return bool
     */
    public function canFetchTransactionInfo()
    {
        return $this->_canFetchTransactionInfo;
    }

    /**
     * Gateway is gateway attribute
     *
     * @return bool
     */
    public function isGateway()
    {
        return $this->_isGateway;
    }

    /**
     * Gateway is offline attribute
     *
     * @return false
     */
    public function isOffline()
    {
        return false;
    }

    /**
     * Gateway is initialisation needed attribute
     *
     * @return bool
     */
    public function isInitializeNeeded()
    {
        return $this->_isInitializeNeeded;
    }

    /**
     * To check billing country is allowed for the payment method
     *
     * @param string $country
     *
     * @return bool
     */
    public function canUseForCountry($country)
    {
        /*
        for specific country, the flag will set up as 1
        */
        if ($this->getConfigData('allowspecific') == 1) {
            $availableCountries = explode(',', $this->getConfigData('specificcountry') ?? '');
            if (!in_array($country, $availableCountries)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gateway get info block type
     *
     * @return string
     */
    public function getInfoBlockType()
    {
        return $this->_infoBlockType;
    }

    /**
     * Retrieve payment information model object
     *
     * @return InfoInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getInfoInstance()
    {
        $instance = $this->getData('info_instance');
        if (!$instance instanceof InfoInterface) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('We cannot retrieve the payment information object instance.')
            );
        }

        return $instance;
    }

    /**
     * Gateway set info instance
     *
     * @param InfoInterface $info
     *
     * @return false
     */
    public function setInfoInstance(InfoInterface $info)
    {
        $this->setData('info_instance', $info);
    }

    /**
     * Validate payment method information object
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate()
    {
        /**
         * to validate payment method is allowed for billing country or not
         */
        $paymentInfo = $this->getInfoInstance();
        if ($paymentInfo instanceof Payment) {
            $billingCountry = $paymentInfo->getOrder()->getBillingAddress()->getCountryId();
        } else {
            $billingCountry = $paymentInfo->getQuote()->getBillingAddress()->getCountryId();
        }
        $billingCountry = $billingCountry ?: $this->directory->getDefaultCountry();

        if (!$this->canUseForCountry($billingCountry)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You can\'t use the payment type you selected to make payments to the billing country.')
            );
        }

        return $this;
    }

    /**
     * Gateway order function
     *
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return SID
     */
    public function order(InfoInterface $payment, $amount)
    {
        return $this;
    }

    /**
     * Gateway authorize function
     *
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return SID
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        return $this;
    }

    /**
     * Gateway capture function
     *
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return SID
     */
    public function capture(InfoInterface $payment, $amount)
    {
        return $this;
    }

    /**
     * Gateway cancel function
     *
     * @param InfoInterface $payment
     *
     * @return SID
     */
    public function cancel(InfoInterface $payment)
    {
        return $this;
    }

    /**
     * Gateway void function
     *
     * @param InfoInterface $payment
     *
     * @return SID
     */
    public function void(InfoInterface $payment)
    {
        return $this;
    }

    /**
     * Gateway can review attribute
     *
     * @return bool
     */
    public function canReviewPayment()
    {
        return $this->_canReviewPayment;
    }

    /**
     * Gateway accept payment attribute
     *
     * @param InfoInterface $payment
     *
     * @return false
     */
    public function acceptPayment(InfoInterface $payment)
    {
        return false;
    }

    /**
     * Gateway deny payment attribute
     *
     * @param InfoInterface $payment
     *
     * @return SID
     */
    public function denyPayment(InfoInterface $payment)
    {
        return $this;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|string|null|Store $storeId
     *
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getConfigData($field, $storeId = null)
    {
        if ('order_place_redirect_url' === $field) {
            return $this->getOrderPlaceRedirectUrl();
        }
        if (null === $storeId) {
            $storeId = $this->_storeManager->getStore()->getId();
        }
        $path = 'payment/' . $this->getCode() . '/' . $field;

        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Assign data to info model instance
     *
     * @param array|\Magento\Framework\DataObject $data
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        $this->_eventManager->dispatch(
            'payment_method_assign_data_' . $this->getCode(),
            [
                AbstractDataAssignObserver::METHOD_CODE => $this,
                AbstractDataAssignObserver::MODEL_CODE  => $this->getInfoInstance(),
                AbstractDataAssignObserver::DATA_CODE   => $data
            ]
        );

        $this->_eventManager->dispatch(
            'payment_method_assign_data',
            [
                AbstractDataAssignObserver::METHOD_CODE => $this,
                AbstractDataAssignObserver::MODEL_CODE  => $this->getInfoInstance(),
                AbstractDataAssignObserver::DATA_CODE   => $data
            ]
        );

        return $this;
    }

    /**
     * Is active
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return (bool)(int)$this->getConfigData('active', $storeId);
    }
}
