<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;

class SidConfig
{
    protected $_scopeConfig;
    protected $path = 'payment/sid/';

    public function __construct( \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig )
    {
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * @inheritDoc
     */
    public function isSetFlag( $path, $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeCode = null )
    {
        // TODO: Implement isSetFlag() method.
    }

    public function getConfigValue( $key )
    {
        return $this->_scopeConfig->getValue( $this->path . $key );
    }
}
