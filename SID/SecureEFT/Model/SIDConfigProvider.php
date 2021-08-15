<?php
/**
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use SID\SecureEFT\Helper\Data as SIDHelper;

class SIDConfigProvider implements ConfigProviderInterface
{
    protected $localeResolver;
    protected $config;
    protected $currentCustomer;
    protected $sidHelper;
    protected $methodCodes = [Config::METHOD_CODE];
    protected $methods = [];
    protected $paymentHelper;

    public function __construct(
        ConfigFactory $configFactory,
        ResolverInterface $localeResolver,
        CurrentCustomer $currentCustomer,
        SIDHelper $sidHelper,
        PaymentHelper $paymentHelper
    ) {
        $this->localeResolver  = $localeResolver;
        $this->config          = $configFactory->create();
        $this->currentCustomer = $currentCustomer;
        $this->sidHelper       = $sidHelper;
        $this->paymentHelper   = $paymentHelper;
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $this->paymentHelper->getMethodInstance($code);
        }
    }

    public function getConfig()
    {
        $dbConfig = [
            'payment' => [
                'sid' => [
                    'paymentAcceptanceMarkSrc'  => $this->config->getPaymentMarkImageUrl(),
                    'paymentAcceptanceMarkHref' => $this->config->getPaymentMarkWhatIsSID()
                ]
            ],
        ];
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $dbConfig['payment']['sid']['redirectUrl'][$code]          = $this->getMethodRedirectUrl($code);
                $dbConfig['payment']['sid']['billingAgreementCode'][$code] = $this->getBillingAgreementCode();
            }
        }

        return $dbConfig;
    }

    protected function getMethodRedirectUrl($code)
    {
        return $this->methods[$code]->getCheckoutRedirectUrl();
    }

    protected function getBillingAgreementCode()
    {
        return $this->sidHelper->shouldAskToCreateBillingAgreement();
    }
}
