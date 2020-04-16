<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Controller;

use Magento\Checkout\Controller\Express\RedirectLoginInterface;
use Magento\Framework\App\Action\Action as AppAction;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use SID\InstantEFT\Model;

abstract class AbstractSIDm230 extends AppAction implements RedirectLoginInterface, CsrfAwareActionInterface
{
    protected $_logger;
    protected $_checkoutTypes = [];
    protected $_config;
    protected $_quote        = false;
    protected $_configType   = 'SID\InstantEFT\Model\Config';
    protected $_configMethod = \SID\InstantEFT\Model\Config::METHOD_CODE;
    protected $_checkoutType;
    protected $_customerSession;
    protected $_checkoutSession;
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

    public function __construct( \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Session\Generic $sidSession,
        \Magento\Framework\Url\Helper\Data $urlHelper,
        \Magento\Customer\Model\Url $customerUrl,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \SID\InstantEFT\Model\SID $paymentMethod,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Checkout\Model\Type\Onepage $onepage,
        \SID\InstantEFT\Model\SIDResponseHandler $sidResponseHandler,
        \Magento\Sales\Model\OrderNotifier $OrderNotifier,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \SID\InstantEFT\Helper\SidConfig $sidConfig,
        \SID\InstantEFT\Model\PaymentFactory $paymentFactory
    ) {
        $this->_logger                 = $logger;
        $this->_customerSession        = $customerSession;
        $this->_checkoutSession        = $checkoutSession;
        $this->_orderFactory           = $orderFactory;
        $this->sidSession              = $sidSession;
        $this->_urlHelper              = $urlHelper;
        $this->_customerUrl            = $customerUrl;
        $this->pageFactory             = $pageFactory;
        $this->_transactionFactory     = $transactionFactory;
        $this->_paymentMethod          = $paymentMethod;
        $this->_date                   = $date;
        $this->orderRepository         = $orderRepository;
        $this->onePage                 = $onepage;
        $this->_sidResponseHandler     = $sidResponseHandler;
        $this->_orderNotifier          = $OrderNotifier;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_sidConfig              = $sidConfig;
        $this->_paymentFactory         = $paymentFactory;

        parent::__construct( $context );
        $parameters    = ['params' => [$this->_configMethod]];
        $this->_config = $this->_objectManager->create( $this->_configType, $parameters );
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ):  ? InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf( RequestInterface $request ) :  ? bool
    {
        return true;
    }

    public function getConfigData( $field )
    {
        return $this->_paymentMethod->getConfigData( $field );
    }

    protected function _initCheckout()
    {
        $this->_order = $this->_checkoutSession->getLastRealOrder();
        if ( !$this->_order->getId() ) {
            $this->getResponse()->setStatusHeader( 404, '1.1', 'Not found' );
            throw new \Magento\Framework\Exception\LocalizedException( __( 'We could not find "Order" for processing' ) );
        }
        if ( $this->_order->getState() != \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT ) {
            $this->_order->setState( \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT )->setIsNotified( false )->save();
        }
        if ( $this->_order->getQuoteId() ) {
            $this->_checkoutSession->setSIDQuoteId( $this->_checkoutSession->getQuoteId() );
            $this->_checkoutSession->setSIDSuccessQuoteId( $this->_checkoutSession->getLastSuccessQuoteId() );
            $this->_checkoutSession->setSIDRealOrderId( $this->_checkoutSession->getLastRealOrderId() );
            $this->_checkoutSession->getQuote()->setIsActive( false )->save();
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
        if ( !$this->_quote ) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }
        return $this->_quote;
    }

    public function getCustomerBeforeAuthUrl()
    {
        return;
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

    public function redirectLogin()
    {
        $this->_actionFlag->set( '', 'no-dispatch', true );
        $this->_customerSession->setBeforeAuthUrl( $this->_redirect->getRefererUrl() );
        $this->getResponse()->setRedirect(
            $this->_urlHelper->addRequestParam( $this->_customerUrl->getLoginUrl(), ['context' => 'checkout'] )
        );
    }
}
