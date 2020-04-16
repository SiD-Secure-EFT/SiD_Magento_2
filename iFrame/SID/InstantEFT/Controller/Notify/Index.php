<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Controller\Notify;

/**
 * Check for existence of CsrfAwareActionInterface - only v2.3.0+
 */
if ( interface_exists( "Magento\Framework\App\CsrfAwareActionInterface" ) ) {
    class_alias( 'SID\InstantEFT\Controller\Notify\Indexm230', 'SID\InstantEFT\Controller\Notify\Index' );
} else {
    class_alias( 'SID\InstantEFT\Controller\Notify\Indexm220', 'SID\InstantEFT\Controller\Notify\Index' );
}
