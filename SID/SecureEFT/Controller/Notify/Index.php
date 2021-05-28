<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Controller\Notify;

/**
 * Check for existence of CsrfAwareActionInterface - only v2.3.0+
 */
if (interface_exists("Magento\Framework\App\CsrfAwareActionInterface")) {
    class_alias('SID\SecureEFT\Controller\Notify\Indexm230', 'SID\SecureEFT\Controller\Notify\Index');
} else {
    class_alias('SID\SecureEFT\Controller\Notify\Indexm220', 'SID\SecureEFT\Controller\Notify\Index');
}
