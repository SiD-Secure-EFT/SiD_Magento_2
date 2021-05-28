<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Controller\Notify;

class Indexm220 extends \SID\SecureEFT\Controller\AbstractSID
{
    public function execute()
    {
        try {
            if ($this->_sidResponseHandler->validateResponse($_POST)) {
                // Get latest status of order before posting in case of multiple responses
                $sid_reference = $_POST["SID_REFERENCE"];
                $this->_orderFactory->create()->loadByIncrementId($sid_reference);
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
            header('HTTP/1.0 200 OK');
            flush();
        } catch (\Exception $e) {
            $this->_logger->debug(__METHOD__ . ' : ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            $this->messageManager->addExceptionMessage($e, __('We can\'t start SID Checkout.'));
            $this->_redirect('checkout/cart');
        }
    }
}
