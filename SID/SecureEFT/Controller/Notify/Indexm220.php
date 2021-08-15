<?php
/**
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Controller\Notify;

use Exception;
use SID\SecureEFT\Controller\AbstractSID;

class Indexm220 extends AbstractSID
{

    public function execute()
    {
        $enableNotify = $this->_sidConfig->getConfigValue('enable_notify') == '1';
        $data         = $this->getRequest()->getParams();

        try {
            $sid_reference = $_POST["SID_REFERENCE"];
            $order = $this->_orderFactory->create()->loadByIncrementId($sid_reference);
            if ($enableNotify && ($order->getSidPaymentProcessed() != 1)) {
                $order->setSidPaymentProcessed(1)->save();
                if ($this->_sidResponseHandler->validateResponse($_POST)) {
                    // Get latest status of order before posting in case of multiple responses
                    if ($this->_sidResponseHandler->checkResponseAgainstSIDWebQueryService(
                        $_POST,
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
            $this->_redirect('checkout/cart');
        }
    }
}
