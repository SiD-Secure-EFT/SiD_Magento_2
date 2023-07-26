<?php
/**
 * Copyright (c) 2023 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Controller\Adminhtml\Payment;

use SID\SecureEFT\Controller\Adminhtml\Payment;

class Index extends Payment
{
    public function execute()
    {
        $resultPage = $this->_initAction();
        $resultPage->addBreadcrumb(__('Search Payments'), __('Search Payments'));
        $resultPage->getConfig()->getTitle()->prepend(__('SID Secure EFT Payments'));

        return $resultPage;
    }
}
