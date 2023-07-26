<?php
/**
 * Copyright (c) 2023 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

if (interface_exists("Magento\Framework\App\CsrfAwareActionInterface")) {
    class_alias('SID\SecureEFT\Controller\AbstractSIDm230', 'SID\SecureEFT\Controller\AbstractSID');
} else {
    class_alias('SID\SecureEFT\Controller\AbstractSIDm220', 'SID\SecureEFT\Controller\AbstractSID');
}
