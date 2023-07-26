<?php
/**
 * Copyright (c) 2023 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;

class Payment extends AbstractDb
{
    protected $_date;

    public function __construct(
        Context $context,
        DateTime $date,
        $resourcePrefix = null
    ) {
        parent::__construct($context, $resourcePrefix);
        $this->_date = $date;
    }

    protected function _construct()
    {
        $this->_init('sid_instant_eft_payment', 'payment_id');
    }

    protected function _beforeSave(AbstractModel $object)
    {
        if ($object->isObjectNew() && ! $object->getTimeStamp()) {
            $object->setTimeStamp($this->_date->gmtDate());
        }

        return parent::_beforeSave($object);
    }
}
