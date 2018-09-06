<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Model\ResourceModel\Payment;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'payment_id';

    protected function _construct()
    {
        $this->_init( 'SID\InstantEFT\Model\Payment', 'SID\InstantEFT\Model\ResourceModel\Payment' );
    }

}
