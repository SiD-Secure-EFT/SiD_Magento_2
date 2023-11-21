<?php
/**
 * Copyright (c) 2023 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Api\Data;

interface PaymentInterface3
{
    public function getRedirected();

    public function setRedirected($redirected);

    public function getTimeStamp();

    public function setTimeStamp($timeStamp);
}
