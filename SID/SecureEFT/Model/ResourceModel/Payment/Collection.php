<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Model\ResourceModel\Payment;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'payment_id';

    protected function _construct()
    {
        $this->_init('SID\SecureEFT\Model\Payment', 'SID\SecureEFT\Model\ResourceModel\Payment');
    }

}
