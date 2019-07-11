<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Controller;

if (interface_exists("Magento\Framework\App\CsrfAwareActionInterface")) {
    include __DIR__ . "/AbstractSID.m230.php";
} else {
    include __DIR__ . "/AbstractSID.m220.php";
}