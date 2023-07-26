<?php
/**
 * Copyright (c) 2023 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Controller\Redirect;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use SID\SecureEFT\Controller\AbstractSID;
use SID\SecureEFT\Model\Config;

require_once __DIR__ . '/../AbstractSID.php';

class Redirect extends AbstractSID
{
    protected $resultPageFactory;
    protected $_configMethod = Config::METHOD_CODE;

    public function execute()
    {
        $page_object = $this->pageFactory->create();
        try {
            $this->_initCheckout();
        } catch (LocalizedException $e) {
            $this->_logger->debug(__METHOD__ . ' : ' . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
            $this->_redirect('checkout/cart');
        } catch (Exception $e) {
            $this->_logger->debug(__METHOD__ . ' : ' . $e->getMessage());
            $this->messageManager->addExceptionMessage($e, __('We can\'t start SID Checkout.'));
            $this->_redirect('checkout/cart');
        }

        return $page_object;
    }
}
