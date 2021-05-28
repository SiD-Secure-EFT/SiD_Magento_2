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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedExceptionFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
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

    //empty because it's being called by Magento at checkout

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
}
