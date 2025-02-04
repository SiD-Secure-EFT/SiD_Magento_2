<?php
/**
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace Sid\SecureEFT\Controller;

use Magento\Checkout\Controller\Express\RedirectLoginInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\Generic;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Url\Helper\Data;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\OrderNotifier;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Psr\Log\LoggerInterface;
use SID\SecureEFT\Helper\SidConfig;
use SID\SecureEFT\Model\Config;
use SID\SecureEFT\Model\PaymentFactory;
use SID\SecureEFT\Model\SID;
use SID\SecureEFT\Model\SIDResponseHandler;
use Magento\Sales\Api\TransactionRepositoryInterface;

abstract class AbstractSID implements CsrfAwareActionInterface
{
    protected $_logger;
    protected $_checkoutTypes = [];
    protected $_config;
    protected $_quote = false;
    protected $_configType = 'SID\SecureEFT\Model\Config';
    protected $_configMethod = Config::METHOD_CODE;
    protected $_checkoutType;
    protected $_customerSession;
    protected $_checkoutSession;
    protected $_quoteFactory;
    protected $orderFactory;
    protected $sidSession;
    protected $_urlHelper;
    protected $_customerUrl;
    protected $_order;
    protected $pageFactory;
    protected $_transactionFactory;
    protected $_paymentMethod;
    protected $_date;
    protected $_sidResponseHandler;
    protected $onepage;
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
     * @var Order
     */
    protected $orderModel;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;
    /**
     * @var OrderNotifier
     */
    protected OrderNotifier $_orderNotifier;
    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var ResultFactory
     */
    protected ResultFactory $resultFactory;
    protected TransactionRepositoryInterface $transactionRepository;
    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $messageManager;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Magento\Customer\Model\Session $customerSession,
        CheckoutSession $checkoutSession,
        QuoteFactory $quoteFactory,
        OrderFactory $orderFactory,
        Generic $sidSession,
        Data $urlHelper,
        Url $customerUrl,
        LoggerInterface $logger,
        TransactionFactory $transactionFactory,
        SID $paymentMethod,
        DateTime $date,
        SIDResponseHandler $sidResponseHandler,
        OrderNotifier $OrderNotifier,
        CollectionFactory $orderCollectionFactory,
        State $state,
        SidConfig $sidConfig,
        Builder $_transactionBuilder,
        PaymentFactory $paymentFactory,
        Order $orderModel,
        OrderRepositoryInterface $orderRepository,
        CartRepositoryInterface $quoteRepository,
        RequestInterface $request,
        ResultFactory $resultFactory,
        TransactionRepositoryInterface $transactionRepository,
        ManagerInterface $messageManager,
    ) {
        $this->orderModel              = $orderModel;
        $this->_logger                 = $logger;
        $this->_customerSession        = $customerSession;
        $this->_checkoutSession        = $checkoutSession;
        $this->_quoteFactory           = $quoteFactory;
        $this->orderFactory            = $orderFactory;
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
        $this->_state                  = $state;
        $this->orderRepository         = $orderRepository;
        $this->_sidConfig              = $sidConfig;
        $this->_paymentFactory         = $paymentFactory;
        $this->_transactionBuilder     = $_transactionBuilder;
        $this->quoteRepository         = $quoteRepository;
        $this->request                 = $request;
        $this->resultFactory           = $resultFactory;
        $this->transactionRepository   = $transactionRepository;
        $this->messageManager          = $messageManager;
    }

    public function getConfigData($field)
    {
        return $this->_paymentMethod->getConfigData($field);
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

        if (!$justResult) {
            return ["queryResult" => $queryResult, "queryError" => $queryError];
        } else {
            return $queryResult;
        }
    }

    protected function _initCheckout()
    {
        $this->_order = $this->_checkoutSession->getLastRealOrder();
        if (!$this->_order->getId()) {
            $this->getResponse()->setStatusHeader(404, '1.1', 'Not found');
            throw new LocalizedException(__('We could not find "Order" for processing'));
        }
        if ($this->_order->getState() != Order::STATE_PENDING_PAYMENT) {
            $this->_order->setState(Order::STATE_PENDING_PAYMENT)->setIsNotified(false)->save();
        }
        if ($this->_order->getQuoteId()) {
            $this->_checkoutSession->setSIDQuoteId($this->_checkoutSession->getQuoteId());
            $this->_checkoutSession->setSIDSuccessQuoteId($this->_checkoutSession->getLastSuccessQuoteId());
            $this->_checkoutSession->setSIDRealOrderId($this->_checkoutSession->getLastRealOrderId());
            $quote = $this->_checkoutSession->getQuote();
            if ($quote) {
                $quote->setIsActive(false);
                $this->quoteRepository->save($quote);
            }
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
        if (!$this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }

        return $this->_quote;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
