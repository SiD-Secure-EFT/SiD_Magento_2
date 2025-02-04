<?php
/**
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Controller\Notify;

use Magento\Framework\Controller\ResultFactory;
use SID\SecureEFT\Controller\AbstractSID;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Index extends AbstractSID implements CsrfAwareActionInterface
{
    public function execute()
    {
        $enableNotify = $this->_sidConfig->getConfigValue('enable_notify') == '1';

        $resultRedirectFactory = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $sid_reference = $_POST["SID_REFERENCE"];
            $order         = $this->orderFactory->create()->loadByIncrementId($sid_reference);

            $payment = $order->getPayment();
            if ($enableNotify && ($order->getSidPaymentProcessed() != 1)) {
                $order->setSidPaymentProcessed(1)->save();
                if ($this->_sidResponseHandler->validateResponse($_POST)) {
                    // Get latest status of order before posting in case of multiple responses
                    if ($this->_sidResponseHandler->checkResponseAgainstSIDWebQueryService(
                        $_POST,
                        $payment,
                        true,
                        $this->_date->gmtDate()
                    )) {
                        $this->_logger->debug(__METHOD__ . ' : Payment Successful');
                    } else {
                        $this->_logger->debug(__METHOD__ . ' : Payment Unsuccessful');
                    }
                }
            } else {
                $this->_logger->debug('IPN - ORDER ALREADY BEING PROCESSED');
            }

            header('HTTP/1.0 200 OK');
            flush();
        } catch (Exception $e) {
            $this->_logger->debug(__METHOD__ . ' : ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            $this->messageManager->addExceptionMessage($e, __('We can\'t start SID Checkout.'));

            return $resultRedirectFactory->setPath('checkout/cart');
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
}
