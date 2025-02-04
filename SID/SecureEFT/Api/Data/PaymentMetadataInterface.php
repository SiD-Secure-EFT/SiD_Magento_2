<?php
/**
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Api\Data;

/**
 * Handles additional metadata for payment operations.
 */
interface PaymentMetadataInterface
{
    public function getRedirected();

    public function setRedirected($redirected);

    public function getTimeStamp();

    public function setTimeStamp($timeStamp);
}
