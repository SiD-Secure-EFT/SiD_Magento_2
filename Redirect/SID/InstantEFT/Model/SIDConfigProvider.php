<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use SID\InstantEFT\Helper\Data as SIDHelper;

class SIDConfigProvider implements ConfigProviderInterface
{
    protected $localeResolver;
    protected $config;
    protected $currentCustomer;
    protected $sidHelper;
    protected $methodCodes = [Config::METHOD_CODE];
    protected $methods     = [];
    protected $paymentHelper;

    public function __construct( ConfigFactory $configFactory,
        ResolverInterface $localeResolver,
        CurrentCustomer $currentCustomer,
        SIDHelper $sidHelper,
        PaymentHelper $paymentHelper ) {
        $this->localeResolver  = $localeResolver;
        $this->config          = $configFactory->create();
        $this->currentCustomer = $currentCustomer;
        $this->sidHelper       = $sidHelper;
        $this->paymentHelper   = $paymentHelper;
        foreach ( $this->methodCodes as $code ) {
            $this->methods[$code] = $this->paymentHelper->getMethodInstance( $code );
        }
    }

    public function getConfig()
    {
        $config = ['payment' => [
            'sid' => [
                'paymentAcceptanceMarkSrc'  => $this->config->getPaymentMarkImageUrl(),
                'paymentAcceptanceMarkHref' => $this->config->getPaymentMarkWhatIsSID()]],
        ];
        foreach ( $this->methodCodes as $code ) {
            if ( $this->methods[$code]->isAvailable() ) {
                $config['payment']['sid']['redirectUrl'][$code]          = $this->getMethodRedirectUrl( $code );
                $config['payment']['sid']['billingAgreementCode'][$code] = $this->getBillingAgreementCode( $code );
            }
        }
        return $config;
    }

    protected function getMethodRedirectUrl( $code )
    {
        $methodUrl = $this->methods[$code]->getCheckoutRedirectUrl();
        return $methodUrl;
    }

    protected function getBillingAgreementCode( $code )
    {
        $customerId = $this->currentCustomer->getCustomerId();
        $this->config->setMethod( $code );
        return $this->sidHelper->shouldAskToCreateBillingAgreement( $this->config, $customerId );
    }
}
