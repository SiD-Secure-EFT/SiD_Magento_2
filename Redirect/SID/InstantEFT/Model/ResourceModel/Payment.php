<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Model\ResourceModel;

class Payment extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected $_date;
    public function __construct( \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        $resourcePrefix = null ) {
        parent::__construct( $context, $resourcePrefix );
        $this->_date = $date;
    }

    protected function _construct()
    {
        $this->_init( 'sid_instant_eft_payment', 'payment_id' );
    }

    protected function _beforeSave( \Magento\Framework\Model\AbstractModel $object )
    {
        if ( $object->isObjectNew() && !$object->getTimeStamp() ) {
            $object->setTimeStamp( $this->_date->gmtDate() );
        }
        return parent::_beforeSave( $object );
    }
}
