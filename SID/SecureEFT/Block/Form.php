<?php
/**
 * Copyright (c) 2023 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Block;

use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Element\Template\Context;
use SID\SecureEFT\Helper\Data;
use SID\SecureEFT\Model\Config;
use SID\SecureEFT\Model\ConfigFactory;

class Form extends \Magento\Payment\Block\Form
{
    protected $_methodCode = Config::METHOD_CODE;
    protected $_sidData;
    protected $sidConfigFactory;
    protected $_localeResolver;
    protected $_config;
    protected $_isScopePrivate;
    protected $currentCustomer;

    public function __construct(
        Context $context,
        ConfigFactory $sidConfigFactory,
        ResolverInterface $localeResolver,
        Data $sidData,
        CurrentCustomer $currentCustomer,
        array $data = []
    ) {
        $this->_sidData         = $sidData;
        $this->sidConfigFactory = $sidConfigFactory;
        $this->_localeResolver  = $localeResolver;
        $this->_config          = null;
        $this->_isScopePrivate  = true;
        $this->currentCustomer  = $currentCustomer;
        parent::__construct($context, $data);
    }

    public function getMethodCode()
    {
        return $this->_methodCode;
    }

    protected function _construct()
    {
        $this->_config = $this->sidConfigFactory->create()->setMethod($this->getMethodCode());
        parent::_construct();
    }
}
