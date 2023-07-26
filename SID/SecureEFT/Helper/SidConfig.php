<?php
/**
 * Copyright (c) 2023 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class SidConfig
 * @package SID\SecureEFT\Helper
 */
class SidConfig
{
    protected $path = 'payment/sid/';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * SidConfig constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(ScopeConfigInterface $scopeConfig, StoreManagerInterface $storeManager)
    {
        $this->scopeConfig  = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * @param $key
     * @param int $storeId
     *
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getConfigValue($key)
    {
        $storeId = $this->storeManager->getStore()->getId();

        return $this->scopeConfig->getValue($this->path . $key, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
