<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Controller;

use Magento\Checkout\Controller\Express\RedirectLoginInterface;
use Magento\Framework\App\Action\Action as AppAction;

abstract class AbstractSIDm220 extends AppAction implements RedirectLoginInterface
{
    protected $_logger;
    protected $_checkoutTypes = [];
    protected $_config;
    protected $_quote = false;
    protected $_configType = 'SID\SecureEFT\Model\Config';
    protected $_configMethod = \SID\SecureEFT\Model\Config::METHOD_CODE;
    protected $_checkoutType;
    protected $_customerSession;
    protected $_checkoutSession;
    protected $_quoteFactory;
    protected $_orderFactory;
    protected $sidSession;
    protected $_urlHelper;
    protected $_customerUrl;
    protected $_order;
    protected $pageFactory;
    protected $_transactionFactory;
    protected $_paymentMethod;
    protected $_date;
    protected $_sidResponseHandler;
    protected $orderRepository;
    protected $onepage;
    protected $_orderCollectionFactory;
    protected $_sidConfig;
    protected $_paymentFactory;

    /**
     * @var Magento\Sales\Model\Order\Payment\Transaction\Builder $_transactionBuilder
     */
    protected $_transactionBuilder;
    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $orderModel;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Session\Generic $sidSession,
        \Magento\Framework\Url\Helper\Data $urlHelper,
        \Magento\Customer\Model\Url $customerUrl,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \SID\SecureEFT\Model\SID $paymentMethod,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \SID\SecureEFT\Model\SIDResponseHandler $sidResponseHandler,
        \Magento\Sales\Model\OrderNotifier $OrderNotifier,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \SID\SecureEFT\Helper\SidConfig $sidConfig,
        \Magento\Sales\Model\Order\Payment\Transaction\Builder $_transactionBuilder,
        \SID\SecureEFT\Model\PaymentFactory $paymentFactory,
        \Magento\Sales\Model\Order $orderModel
    ) {
        $this->orderModel              = $orderModel;
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
        $this->_orderNotifier          = $OrderNotifier;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_sidConfig              = $sidConfig;
        $this->_paymentFactory         = $paymentFactory;
        $this->_transactionBuilder     = $_transactionBuilder;

        parent::__construct($context);
    }

    public function getConfigData($field)
    {
        return $this->_paymentMethod->getConfigData($field);
    }

    public function getCustomerBeforeAuthUrl()
    {
    }

    public function getActionFlagList()
    {
        return [];
    }

    public function getLoginUrl()
    {
        return $this->_customerUrl->getLoginUrl();
    }

    public function getRedirectActionName()
    {
        return 'redirect';
    }

    //Needs to be implemented

    public function redirectLogin()
    {
        $this->_actionFlag->set('', 'no-dispatch', true);
        $this->_customerSession->setBeforeAuthUrl($this->_redirect->getRefererUrl());
        $this->getResponse()->setRedirect(
            $this->_urlHelper->addRequestParam($this->_customerUrl->getLoginUrl(), ['context' => 'checkout'])
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

    protected function _initCheckout()
    {
        $this->_order = $this->_checkoutSession->getLastRealOrder();
        if ( ! $this->_order->getId()) {
            $this->getResponse()->setStatusHeader(404, '1.1', 'Not found');
            throw new \Magento\Framework\Exception\LocalizedException(__('We could not find "Order" for processing'));
        }
        if ($this->_order->getState() != \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) {
            $this->_order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)->setIsNotified(false)->save();
        }
        if ($this->_order->getQuoteId()) {
            $this->_checkoutSession->setSIDQuoteId($this->_checkoutSession->getQuoteId());
            $this->_checkoutSession->setSIDSuccessQuoteId($this->_checkoutSession->getLastSuccessQuoteId());
            $this->_checkoutSession->setSIDRealOrderId($this->_checkoutSession->getLastRealOrderId());
            $this->_checkoutSession->getQuote()->setIsActive(false)->save();
        }
    }

    protected function _getSession()
    {
        return $this->sidSession;
    }

    protected function _getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    protected function _getQuote()
    {
        if ( ! $this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }

        return $this->_quote;
    }
}
