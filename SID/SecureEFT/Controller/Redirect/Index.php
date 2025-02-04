<?php
/**
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Controller\Redirect;

use Exception;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment\Transaction;
use SID\SecureEFT\Controller\AbstractSID;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Index extends AbstractSID implements HttpGetActionInterface, HttpPostActionInterface
{
    public const CARTPATH    = "checkout/cart";
    public const SUCCESSPATH = "checkout/onepage/success";
    protected $resultPageFactory;
    protected $order;

    public function execute()
    {
        $enableRedirect = !$this->_sidConfig->getConfigValue('enable_notify') == '1';

        $data                  = $this->request->getParams();
        $resultRedirectFactory = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $this->prepareTransactionData($data);

        $order   = $this->getOrderByIncrementId($data['SID_REFERENCE']);
        $payment = $order->getPayment();
        if ($enableRedirect && ($order->getSidPaymentProcessed() != 1)) {
            try {
                $order->setSidPaymentProcessed(1)->save();
                $this->_logger->debug(__METHOD__ . ' : ' . print_r($data, true));
                $payment_successful = false;
                if ($this->_sidResponseHandler->validateResponse(
                        $data
                    ) && $this->_sidResponseHandler->checkResponseAgainstSIDWebQueryService(
                        $data,
                        $payment,
                        $this->_date->gmtDate(),
                        false
                    )) {
                    $payment_successful = true;

                    return $resultRedirectFactory->setPath(self::SUCCESSPATH);
                }
                if (!$payment_successful) {
                    $this->restoreQuote();
                    throw new LocalizedException(__('Your payment was unsuccessful'));
                }
            } catch (LocalizedException $e) {
                $this->_logger->debug(__METHOD__ . ' : ' . $e->getMessage());
                $this->messageManager->addExceptionMessage($e, $e->getMessage());
                $this->restoreQuote();

                return $resultRedirectFactory->setPath(self::CARTPATH);
            } catch (Exception $e) {
                $this->_logger->debug(__METHOD__ . ' : ' . $e->getMessage() . '\n' . $e->getTraceAsString());
                $this->messageManager->addExceptionMessage($e, __('We can\'t start SID Checkout.'));
                $this->restoreQuote();

                return $resultRedirectFactory->setPath(self::CARTPATH);
            }
        } else {
            $this->_logger->debug('REDIRECT - ORDER ALREADY BEING PROCESSED');
            //Notify enabled. Process enough to redirect to correct page
            try {
                $this->_logger->debug(__METHOD__ . ' : ' . print_r($data, true));
                $payment_successful = false;

                $sentConsistent = $data['SID_CONSISTENT'];
                unset($data['SID_CONSISTENT']);
                $consistentString       = implode('', $data);
                $consistentString       .= $this->_sidConfig->getConfigValue('private_key');
                $ourConsistent          = strtoupper(hash('sha512', $consistentString));
                $verified               = hash_equals($ourConsistent, $sentConsistent);
                $data['SID_CONSISTENT'] = $sentConsistent;

                if ($verified && $data['SID_STATUS'] == 'COMPLETED') {
                    if ($this->_sidResponseHandler->validateResponse(
                            $data
                        ) && $this->_sidResponseHandler->checkResponseAgainstSIDWebQueryService(
                            $data,
                            $payment,
                            $this->_date->gmtDate(),
                            false
                        )) {
                        $payment_successful = true;

                        return $resultRedirectFactory->setPath(self::SUCCESSPATH);
                    }
                }
                if (!$payment_successful) {
                    $this->restoreQuote();
                    throw new LocalizedException(__('Your payment was unsuccessful'));
                }
            } catch (LocalizedException $e) {
                $this->_logger->debug(__METHOD__ . ' : ' . $e->getMessage());
                $this->messageManager->addExceptionMessage($e, $e->getMessage());
                $this->restoreQuote();

                return $resultRedirectFactory->setPath(self::CARTPATH);
            }
        }
    }

    public function prepareTransactionData($data)
    {
        $Payment                    = array();
        $Payment['status']          = $data['SID_STATUS'];
        $Payment['reference']       = $data['SID_REFERENCE'];
        $Payment['txn_id']          = $data['SID_TNXID'];
        $Payment['additional_data'] = $data;
        $this->order                = $this->getOrderByIncrementId($Payment['reference']);
        $this->setlastOrderDetails();
        $this->createTransaction($this->order, $Payment);
    }

    public function createTransaction($order, $paymentData = array())
    {
        try {
            //get payment object from order object
            $payment = $order->getPayment();
            $payment->setLastTransId($paymentData['txn_id'])
                    ->setTransactionId($paymentData['txn_id'])
                    ->setAdditionalInformation(
                        [Transaction::RAW_DETAILS => (array)$paymentData]
                    );
            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $message = __('The authorized amount is %1.', $formatedPrice);
            //get the object of builder class
            $trans       = $this->_transactionBuilder;
            $transaction = $trans->setPayment($payment)
                                 ->setOrder($order)
                                 ->setTransactionId($paymentData['txn_id'])
                                 ->setAdditionalInformation(
                                     [Transaction::RAW_DETAILS => (array)$paymentData['additional_data']]
                                 )
                                 ->setFailSafe(true)
                //build method creates the transaction and returns the object
                                 ->build(Transaction::TYPE_CAPTURE);

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId(null);
            $payment->save();
            $order->save();

            $savedTransaction = $this->transactionRepository->save($transaction);

            return $savedTransaction->getTransactionId();
        } catch (Exception $e) {
            $this->_logger->error($e->getMessage());
        }
    }

    public function getOrderByIncrementId($incrementId)
    {
        return $this->orderModel->loadByIncrementId($incrementId);
    }

    public function setlastOrderDetails()
    {
        $order       = $this->order;
        $orderId     = $order->getId();
        $customerId  = $order->getCustomerId();
        $quoteId     = $order->getQuoteId();
        $incrementId = $order->getIncrementId();
        $this->_checkoutSession->setData('last_order_id', $orderId);
        $this->_checkoutSession->setData('last_quote_id', $quoteId);
        $this->_checkoutSession->setData('last_success_quote_id', $quoteId);
        $this->_checkoutSession->setData('last_real_order_id', $incrementId);
        $_SESSION['customer_base']['customer_id']           = $customerId;
        $_SESSION['default']['visitor_data']['customer_id'] = $customerId;
        $_SESSION['customer_base']['customer_id']           = $customerId;
    }

    public function restoreQuote()
    {
        $order = $this->order;
        $quote = $this->quoteRepository->get($order->getQuoteId());
        $quote->setIsActive(1)->setReservedOrderId(null);
        $this->quoteRepository->save($quote);
        $this->_checkoutSession->replaceQuote($quote)->unsLastRealOrderId();
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return $this->logger->debug("Invalid request exception when attempting to validate CSRF");
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

}
