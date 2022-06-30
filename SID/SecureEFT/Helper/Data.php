<?php
/**
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Helper;

use Exception;
use Magento\Framework\App\Config\BaseFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\Generic;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Url\Helper\Data as PaymentHelper;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\OrderNotifier;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Psr\Log\LoggerInterface;
use SID\SecureEFT\Model\PaymentFactory;

class Data extends AbstractHelper
{
    protected static $_shouldAskToCreateBillingAgreement = false;

    /**
     * @var \Magento\Payment\Helper\Data
     */
    protected $_paymentData;
    /**
     * @var LoggerInterface
     */
    protected $_logger;
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
     * @var DateTime
     */
    protected $_date;
    /**
     * @var array
     */
    private $methodCodes;

    public function __construct(
        Context $context,
        PaymentHelper $paymentData,
        LoggerInterface $logger,
        Builder $_transactionBuilder,
        Order $orderModel,
        CartRepositoryInterface $quoteRepository,
        DateTime $date,
        array $methodCodes
    ) {
        $this->_paymentData        = $paymentData;
        $this->methodCodes         = $methodCodes;
        $this->orderModel          = $orderModel;
        $this->_logger             = $logger;
        $this->_transactionBuilder = $_transactionBuilder;
        $this->quoteRepository     = $quoteRepository;
        $this->_date               = $date;
        parent::__construct($context);
    }

    /**
     * @return bool
     */
    public function shouldAskToCreateBillingAgreement()
    {
        return self::$_shouldAskToCreateBillingAgreement;
    }

    /**
     * @param null $store
     * @param null $quote
     *
     * @return array
     */
    public function getBillingAgreementMethods($store = null, $quote = null)
    {
        $result = [];
        foreach ($this->_paymentData->getStoreMethods($store, $quote) as $method) {
            if ($method instanceof MethodInterface) {
                $result[] = $method;
            }
        }

        return $result;
    }

    /**
     * @param $incrementId
     *
     * @return mixed
     */
    public function getOrderByIncrementId($incrementId)
    {
        return $this->orderModel->loadByIncrementId($incrementId);
    }

    /**
     * @param $order
     * @param array $paymentData
     *
     * @return mixed
     */
    public function createTransaction($order, array $paymentData)
    {
        $payment = $order->getPayment();
        $payment->setLastTransId($paymentData['txn_id'])
                ->setTransactionId($paymentData['txn_id'])
                ->setAdditionalInformation(
                    [Transaction::RAW_DETAILS => (array)$paymentData]
                );
        $formatedPrice = $order->getBaseCurrency()->formatTxt(
            $order->getGrandTotal()
        );

        $message     = __('The authorized amount is %1.', $formatedPrice);
        $trans       = $this->_transactionBuilder;
        $transaction = $trans->setPayment($payment)
                             ->setOrder($order)
                             ->setTransactionId($paymentData['txn_id'])
                             ->setAdditionalInformation(
                                 [Transaction::RAW_DETAILS => (array)$paymentData['additional_data']]
                             )
                             ->setFailSafe(true)
                             ->build(Transaction::TYPE_CAPTURE);

        $payment->addTransactionCommentsToOrder(
            $transaction,
            $message
        );
        try {
            $payment->setParentTransactionId(null);
            $payment->save();
            $order->save();

            return $transaction->save()->getTransactionId();
        } catch (Exception $e) {
            $this->_logger->error($e->getMessage());
        }
    }
}
