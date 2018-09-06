<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Controller\Adminhtml\Payment;

class Index extends \SID\InstantEFT\Controller\Adminhtml\Payment
{
    public function execute()
    {
        $resultPage = $this->_initAction();
        $resultPage->addBreadcrumb( __( 'Search Payments' ), __( 'Search Payments' ) );
        $resultPage->getConfig()->getTitle()->prepend( __( 'SID Instant EFT Payments' ) );
        return $resultPage;
    }
}
