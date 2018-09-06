<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Model;

use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;

class SID extends \Magento\Payment\Model\Method\AbstractMethod
{
    protected $_code                    = Config::METHOD_CODE;
    protected $_formBlockType           = 'SID\InstantEFT\Block\Form';
    protected $_infoBlockType           = 'SID\InstantEFT\Block\Payment\Info';
    protected $_configType              = 'SID\InstantEFT\Model\Config';
    protected $_isInitializeNeeded      = true;
    protected $_isGateway               = false;
    protected $_canOrder                = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canFetchTransactionInfo = true;
    protected $_canReviewPayment        = true;
    protected $_config;
    protected $_isOrderPaymentActionKey = 'is_order_action';
    protected $_authorizationCountKey   = 'authorization_count';
    protected $_storeManager;
    protected $_urlBuilder;
    public $_checkoutSession;
    protected $_exception;
    protected $transactionRepository;
    protected $transactionBuilder;

    public function __construct( \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        ConfigFactory $configFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Exception\LocalizedExceptionFactory $exception,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null, array $data = [] ) {
        parent::__construct( $context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data );
        $this->_storeManager         = $storeManager;
        $this->_urlBuilder           = $urlBuilder;
        $this->_checkoutSession      = $checkoutSession;
        $this->_exception            = $exception;
        $this->transactionRepository = $transactionRepository;
        $this->transactionBuilder    = $transactionBuilder;
        $parameters                  = ['params' => [$this->_code]];
        $this->_config               = $configFactory->create( $parameters );
    }

    public function setStore( $store )
    {
        $this->setData( 'store', $store );
        if ( null === $store ) {
            $store = $this->_storeManager->getStore()->getId();
        }
        $this->_config->setStoreId( is_object( $store ) ? $store->getId() : $store );
        return $this;
    }

    public function canUseForCurrency( $currencyCode )
    {
        return $this->_config->isCurrencyCodeSupported( $currencyCode );
    }

    public function getConfigPaymentAction()
    {
        return $this->_config->getPaymentAction();
    }

    public function isAvailable( \Magento\Quote\Api\Data\CartInterface $quote = null )
    {
        return parent::isAvailable( $quote ) && $this->_config->isMethodAvailable();
    }

    protected function getStoreName()
    {
        $storeName = $this->_scopeConfig->getValue( 'general/store_information/name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE );
        return $storeName;
    }

    protected function _placeOrder( Payment $payment, $amount )
    {}

    public function getStandardCheckoutFormFields()
    {
        $order         = $this->_checkoutSession->getLastRealOrder();
        $quoteId       = $order->getQuoteId();
        $orderEntityId = $order->getId();
        $address       = $order->getBillingAddress();
        $merchantCode  = $this->getConfigData( 'merchant_code' );
        $privateKey    = $this->getConfigData( 'private_key' );
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

    public function getTotalAmount( $order )
    {
        if ( $this->getConfigData( 'use_store_currency' ) ) {
            $price = $this->getNumberFormat( $order->getGrandTotal() );
        } else {
            $price = $this->getNumberFormat( $order->getBaseGrandTotal() );
        }
        return $price;
    }

    public function getNumberFormat( $number )
    {
        return number_format( $number, 2, '.', '' );
    }

    public function getPaidSuccessUrl()
    {
        return $this->_urlBuilder->getUrl( 'sid/redirect', array( '_secure' => true ) );
    }

    protected function getOrderTransaction( $payment )
    {
        return $this->transactionRepository->getByTransactionType( Transaction::TYPE_ORDER, $payment->getId(), $payment->getOrder()->getId() );
    }

    public function getOrderPlaceRedirectUrl()
    {
        return $this->_urlBuilder->getUrl( 'sid/redirect/redirect' );
    }

    public function getCheckoutRedirectUrl()
    {
        return $this->_urlBuilder->getUrl( 'sid/redirect/redirect' );
    }

    public function initialize( $paymentAction, $stateObject )
    {
        $stateObject->setState( \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT );
        $stateObject->setStatus( 'pending_payment' );
        $stateObject->setIsNotified( false );
        return parent::initialize( $paymentAction, $stateObject );
    }

    public function getSIDUrl()
    {
        return ( 'https://' . $this->getSIDHost() . '/paySID/' );
    }

    public function getSIDHost()
    {
        return "www.sidpayment.com";
    }
}
