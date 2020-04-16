<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Block;

use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Element\Template\Context;
use SID\InstantEFT\Model\Config;

class Form extends \Magento\Payment\Block\Form
{
    protected $_methodCode = Config::METHOD_CODE;
    protected $_sidData;
    protected $sidConfigFactory;
    protected $_localeResolver;
    protected $_config;
    protected $_isScopePrivate;
    protected $currentCustomer;

    public function __construct( Context $context,
        \SID\InstantEFT\Model\ConfigFactory $sidConfigFactory,
        ResolverInterface $localeResolver,
        \SID\InstantEFT\Helper\Data $sidData,
        CurrentCustomer $currentCustomer, array $data = [] ) {
        $this->_sidData         = $sidData;
        $this->sidConfigFactory = $sidConfigFactory;
        $this->_localeResolver  = $localeResolver;
        $this->_config          = null;
        $this->_isScopePrivate  = true;
        $this->currentCustomer  = $currentCustomer;
        parent::__construct( $context, $data );
    }

    protected function _construct()
    {
        $this->_config = $this->sidConfigFactory->create()->setMethod( $this->getMethodCode() );
        parent::_construct();
    }

    public function getMethodCode()
    {
        return $this->_methodCode;
    }
}
