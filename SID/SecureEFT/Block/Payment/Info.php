<?php
/**
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Block\Payment;

use Magento\Framework\View\Element\Template\Context;
use SID\SecureEFT\Model\InfoFactory;

class Info extends \Magento\Payment\Block\Info
{
    protected $_sidInfoFactory;

    public function __construct(
        Context $context,
        InfoFactory $sidInfoFactory,
        array $data = []
    ) {
        $this->_sidInfoFactory = $sidInfoFactory;
        parent::__construct($context, $data);
    }

    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $payment   = $this->getInfo();
        $sidInfo   = $this->_sidInfoFactory->create();
        if (!$this->getIsSecureMode()) {
            $info = $sidInfo->getPaymentInfo($payment, true);

            return $transport->addData($info);
        }

        return $transport;
    }
}
