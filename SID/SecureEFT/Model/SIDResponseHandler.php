<?php
/**
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Model;

use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;
use SID\SecureEFT\Controller\AbstractSID;
use SID\SecureEFT\Helper\SidConfig;
use SID\SecureEFT\Helper\SidAPI;
use Magento\Sales\Api\OrderRepositoryInterface;

class SIDResponseHandler extends AbstractSID
{
    protected $_order;
    protected $orderFactory;
    protected $_transactionFactory;
    protected $_paymentFactory;
    protected $_paymentMethod;
    protected $_date;
    protected $_sidConfig;
    protected OrderSender $OrderSender;
    private InvoiceService $_invoiceService;
    private InvoiceSender $invoiceSender;
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    public function __construct(
        LoggerInterface $logger,
        OrderFactory $orderFactory,
        TransactionFactory $transactionFactory,
        PaymentFactory $paymentFactory,
        SID $paymentMethod,
        InvoiceSender $invoiceSender,
        InvoiceService $invoiceService,
        OrderSender $OrderSender,
        DateTime $date,
        SidConfig $sidConfig,
        OrderRepositoryInterface $orderRepository,
    ) {
        $this->_logger             = $logger;
        $this->orderFactory        = $orderFactory;
        $this->_paymentMethod      = $paymentMethod;
        $this->invoiceSender       = $invoiceSender;
        $this->_invoiceService     = $invoiceService;
        $this->OrderSender         = $OrderSender;
        $this->_transactionFactory = $transactionFactory;
        $this->_paymentFactory     = $paymentFactory;
        $this->_date               = $date;
        $this->_sidConfig          = $sidConfig;
        $this->orderRepository     = $orderRepository;
    }

    public function execute()
    {
        return $this->pageFactory->create();
    }

    public function validateResponse($sidResultData)
    {
        $sidError  = false;
        $sidErrMsg = '';
        foreach ($sidResultData as $key => $val) {
            $sidResultData[$key] = stripslashes($val);
        }
        if (empty($sidResultData)) {
            $sidError  = true;
            $sidErrMsg = 'Data received is empty';
        }
        if (!$sidError) {
            $sid_status     = strtoupper($sidResultData["SID_STATUS"] ?? '');
            $sid_merchant   = $sidResultData["SID_MERCHANT"] ?? '';
            $sid_country    = $sidResultData["SID_COUNTRY"] ?? '';
            $sid_currency   = $sidResultData["SID_CURRENCY"] ?? '';
            $sid_reference  = $sidResultData["SID_REFERENCE"] ?? '';
            $sid_amount     = $sidResultData["SID_AMOUNT"] ?? '';
            $sid_bank       = $sidResultData["SID_BANK"] ?? '';
            $sid_date       = $sidResultData["SID_DATE"] ?? '';
            $sid_receiptno  = $sidResultData["SID_RECEIPTNO"] ?? '';
            $sid_tnxid      = $sidResultData["SID_TNXID"] ?? '';
            $sid_custom_01  = $sidResultData["SID_CUSTOM_01"] ?? '';
            $sid_custom_02  = $sidResultData["SID_CUSTOM_02"] ?? '';
            $sid_custom_03  = $sidResultData["SID_CUSTOM_03"] ?? '';
            $sid_custom_04  = $sidResultData["SID_CUSTOM_04"] ?? '';
            $sid_custom_05  = $sidResultData["SID_CUSTOM_05"] ?? '';
            $sid_consistent = $sidResultData["SID_CONSISTENT"] ?? '';

            $sid_secret       = $this->_sidConfig->getConfigValue('private_key');
            $consistent_check = strtoupper(
                hash(
                    'sha512',
                    $sid_status . $sid_merchant . $sid_country . $sid_currency
                    . $sid_reference . $sid_amount . $sid_bank . $sid_date . $sid_receiptno
                    . $sid_tnxid . $sid_custom_01 . $sid_custom_02 . $sid_custom_03
                    . $sid_custom_04 . $sid_custom_05 . $sid_secret
                )
            );

            if (!hash_equals($sid_consistent, $consistent_check)) {
                $sidError  = true;
                $sidErrMsg .= 'Consistent is invalid. ';
            }
            if (!$sidError && $sid_merchant != $this->_sidConfig->getConfigValue("merchant_code")) {
                $sidError  = true;
                $sidErrMsg .= 'Merchant code received does not match stores merchant code. ';
            }
            if (!$sidError) {
                if (!$this->_order) {
                    $this->_order = $this->orderFactory->create()->loadByIncrementId($sid_reference);
                }
                if (!$this->_order) {
                    $sidError  = true;
                    $sidErrMsg .= 'Order not found. ';
                }
                if (!$sidError && ((float)$sid_amount != (float)$this->_order->getGrandTotal())) {
                    $sidError  = true;
                    $sidErrMsg .= 'Amount paid does not match order amount. ';
                }
            }
        }
        if ($sidError) {
            $this->_logger->debug(__METHOD__ . ' : Error occurred: ' . trim($sidErrMsg));

            return false;
        }

        return true;
    }

    public function checkResponseAgainstSIDWebQueryService(
        $sidResultData,
        $payment,
        $notified = null,
        $redirected = null
    ) {
        if (is_bool($notified)) {
            $notifyEnabled = $notified;
        } else {
            $notifyEnabled = $this->_sidConfig->getConfigValue('enable_notify') == '1';
        }

        $sidError  = false;
        $sidErrMsg = '';
        foreach ($sidResultData as $key => $val) {
            $sidResultData[$key] = stripslashes($val);
        }
        if (empty($sidResultData)) {
            $sidError  = true;
            $sidErrMsg = 'Data received is empty';
        }
        if (!$sidError) {
            $sid_status    = $sidResultData["SID_STATUS"] ?? '';
            $sid_country   = $sidResultData["SID_COUNTRY"] ?? '';
            $sid_currency  = $sidResultData["SID_CURRENCY"] ?? '';
            $sid_reference = $sidResultData["SID_REFERENCE"] ?? '';
            $sid_amount    = $sidResultData["SID_AMOUNT"] ?? '';

            $queryData = [
                "sellerReference" => $sid_reference,
            ];

            $sid_username = $this->_sidConfig->getConfigValue("username");
            $sid_password = $this->_sidConfig->getConfigValue("password");

            $sidAPI = new SidAPI($queryData, $sid_username, $sid_password);

            $transaction = $sidAPI->retrieveTransaction();

            if ($transaction) {
                $sidError  = false;
                $sidErrMsg = '';
                if ($transaction->sellerReference == $sid_reference) {
                    if (((string)$transaction->status) != $sid_status) {
                        $sidError  = true;
                        $sidErrMsg = 'Status mismatch';
                    }
                    if (!$sidError && ((string)$transaction->country) != $sid_country) {
                        $sidError  = true;
                        $sidErrMsg = 'Country mismatch';
                    }
                    if (!$sidError && ((string)$transaction->currency) != $sid_currency) {
                        $sidError  = true;
                        $sidErrMsg = 'Currency mismatch';
                    }
                    if (!$sidError && ((string)$transaction->amount) != $sid_amount) {
                        $sidError  = true;
                        $sidErrMsg = 'Amount mismatch';
                    }

                    $this->updatePayment(
                        $transaction,
                        $sidResultData["SID_TNXID"],
                        $sidResultData["SID_RECEIPTNO"],
                        $payment,
                        $redirected,
                        $notifyEnabled
                    );
                    if ($payment->getStatus() == 'COMPLETED') {
                        $sidError  = false;
                        $sidErrMsg = '';
                        $order     = $this->orderFactory->create()->loadByIncrementId($sid_reference);
                        if ($order->getStatus() === Order::STATE_PENDING_PAYMENT) {
                            $this->processPayment($sidResultData);
                        }
                    } else {
                        if ($payment->getStatus() == 'CANCELLED') {
                            $sidError  = true;
                            $sidErrMsg = 'Payment cancelled';
                            $order     = $this->orderFactory->create()->loadByIncrementId($sid_reference);
                            if ($this->_sidConfig->getConfigValue('enable_notify') != '1') {
                                $comment = "Redirect Response, Transaction has been cancelled, SID_TNXID: " . $sidResultData["SID_TNXID"];
                            } else {
                                $comment = "Notify Response, Transaction has been cancelled, SID_TNXID: " . $sidResultData["SID_TNXID"];
                            }
                            $order->addCommentToStatusHistory($comment)
                                  ->setIsCustomerNotified(false);

                            $this->orderRepository->save($order);
                        }
                    }
                }
            }
        }
        if ($sidError) {
            $this->cancelOrder($sidResultData);
            $this->_logger->debug(__METHOD__ . ' : Error occurred: ' . $sidErrMsg);

            return false;
        }

        return true;
    }

    public function updatePayment(
        $transaction,
        $tnxId,
        $sidReceiptNo,
        $payment,
        $redirected = null,
        $notified = null,
    ) {
        $payment->setStatus($transaction->status);
        $payment->setCountryCode($transaction->country);
        $payment->setCurrencyCode($transaction->currency);
        $payment->setBankName($transaction->bank);
        $payment->setAmount($transaction->amount);
        $payment->setReference($transaction->transactionId);
        $payment->setReceiptNo($sidReceiptNo);
        $payment->setTnxId($tnxId);
        $payment->setDateCreated($transaction->dateCreated);
        $payment->setDateCompleted($transaction->dateCompleted);
        if (!$payment->getTimeStamp()) {
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

    private function processPayment($sidResultData)
    {
        $sid_reference = $sidResultData["SID_REFERENCE"] ?? '';
        if (!$this->_order) {
            $this->_order = $this->orderFactory->create()->loadByIncrementId($sid_reference);
        }
        if ($this->_order->getStatus() === Order::STATE_PENDING_PAYMENT) {
            $sid_status    = strtoupper($sidResultData["SID_STATUS"] ?? '');
            $sid_amount    = $sidResultData["SID_AMOUNT"] ?? '';
            $sid_bank      = $sidResultData["SID_BANK"] ?? '';
            $sid_receiptno = $sidResultData["SID_RECEIPTNO"] ?? '';
            $sid_tnxid     = $sidResultData["SID_TNXID"] ?? '';

            $status = Order::STATE_PROCESSING;
            if ($this->_sidConfig->getConfigValue('Successful_Order_status') != "") {
                $status = $this->_sidConfig->getConfigValue('Successful_Order_status');
            }
            $this->_order->setStatus($status); //configure the status
            // Save the order using the repository
            $this->orderRepository->save($this->_order);

            $order = $this->_order;

            if ($this->_sidConfig->getConfigValue('enable_notify') != '1') {
                $comment = "Redirect Response, Transaction has been approved, SID_TNXID: " . $sid_tnxid;
            } else {
                $comment = "Notify Response, Transaction has been approved, SID_TNXID: " . $sid_tnxid;
            }

            $order->addCommentToStatusHistory($comment)->setIsCustomerNotified(false);
            $this->orderRepository->save($order);

            $model                  = $this->_paymentMethod;
            $order_successful_email = $model->getConfigData('order_email');

            if ($order_successful_email != '0') {
                $this->OrderSender->send($order);
                $order->addCommentToStatusHistory(
                    __(
                        'Notified customer about order #%1.',
                        $order->getId()
                    )
                )->setIsCustomerNotified(true);

                // Save the order using the repository
                $this->orderRepository->save($order);
            }

            // Capture invoice when payment is successful
            $invoice = $this->_invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
            $invoice->register();

            // Save the invoice to the order
            $transaction = $this->_transactionFactory->create()
                                                     ->addObject($invoice)
                                                     ->addObject($invoice->getOrder());
            $transaction->save();

            // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
            $send_invoice_email = $model->getConfigData('invoice_email');
            if ($send_invoice_email != '0') {
                $this->invoiceSender->send($invoice);
                $order->addCommentToStatusHistory(
                    __(
                        'Notified customer about invoice #%1.',
                        $invoice->getId()
                    )
                )->setIsCustomerNotified(true);

                // Save the order using the repository
                $this->orderRepository->save($order);
            }

            $payment = $this->_order->getPayment();
            $payment->setAdditionalInformation("sid_tnxid", $sid_tnxid);
            $payment->setAdditionalInformation("sid_receiptno", $sid_receiptno);
            $payment->setAdditionalInformation("sid_bank", $sid_bank);
            $payment->setAdditionalInformation("sid_status", $sid_status);
            $payment->registerCaptureNotification($sid_amount);
            $payment->save();
        } else {
            $this->_logger->debug(__METHOD__ . ' : Order processed already');
        }
    }

    private function cancelOrder($sidResultData)
    {
        if (!$this->_order) {
            $sid_reference = $sidResultData["SID_REFERENCE"] ?? '';
            $this->_order  = $this->orderFactory->create()->loadByIncrementId($sid_reference);
        }
        if ($this->_order->getStatus() === Order::STATE_PENDING_PAYMENT) {
            $sid_status = strtoupper($sidResultData["SID_STATUS"] ?? '');
            $this->_order->addCommentToStatusHistory(__($sid_status))->setIsCustomerNotified(false);
            $this->_order->cancel();

            // Save the order using the repository
            $this->orderRepository->save($this->_order);
        }
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
    
    public function getResponse()
    {
        // getResponse
    }

}
