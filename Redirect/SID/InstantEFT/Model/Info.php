<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Model;

class Info
{
    const SID_TNXID     = 'sid_tnxid';
    const SID_RECEIPTNO = 'sid_receiptno';
    const SID_BANK      = 'sid_bank';
    const SID_STATUS    = 'sid_status';

    protected $_paymentMap = array(
        self::SID_TNXID     => 'sid_tnxid',
        self::SID_RECEIPTNO => 'sid_receiptno',
        self::SID_BANK      => 'sid_bank',
        self::SID_STATUS    => 'sid_status',
    );
    protected $_logger;

    public function __construct( \Psr\Log\LoggerInterface $logger )
    {
        $this->_logger = $logger;
    }

    public function getPaymentInfo( \Magento\Payment\Model\InfoInterface $payment, $labelValuesOnly = false )
    {
        $result = $this->_getFullInfo( array_values( $this->_paymentMap ), $payment, $labelValuesOnly );
        return $result;
    }

    protected function _getFullInfo( array $keys, \Magento\Payment\Model\InfoInterface $payment, $labelValuesOnly )
    {
        $result = array();
        foreach ( $keys as $key ) {
            if ( !isset( $this->_paymentMapFull[$key] ) ) {
                $this->_paymentMapFull[$key] = array();
            }
            if ( !isset( $this->_paymentMapFull[$key]['label'] ) ) {
                if ( !$payment->hasAdditionalInformation( $key ) ) {
                    $this->_paymentMapFull[$key]['label'] = false;
                    $this->_paymentMapFull[$key]['value'] = false;
                } else {
                    $value                                = $payment->getAdditionalInformation( $key );
                    $this->_paymentMapFull[$key]['label'] = $this->_getLabel( $key );
                    $this->_paymentMapFull[$key]['value'] = $value;
                }
            }
            if ( !empty( $this->_paymentMapFull[$key]['value'] ) ) {
                if ( $labelValuesOnly ) {
                    $this->_logger->debug( __METHOD__ . ' : key = ' . print_r( $key, true ) );
                    $this->_logger->debug( __METHOD__ . ' : _paymentMapFull[key][label] = ' . print_r( $this->_paymentMapFull[$key]['label'], true ) );
                    $result[$this->_paymentMapFull[$key]['label']] = $this->_paymentMapFull[$key]['value'];
                } else {
                    $result[$key] = $this->_paymentMapFull[$key];
                }
            }
        }
        return $result;
    }

    protected function _getLabel( $key )
    {
        switch ( $key ) {
            case 'sid_tnxid':
                $label = __( 'SID Transaction ID' )->__toString();
                break;
            case 'sid_receiptno':
                $label = __( 'SID Receipt No' )->__toString();
                break;
            case 'sid_bank':
                $label = __( 'SID Bank' )->__toString();
                break;
            case 'sid_status':
                $label = __( 'SID Status' )->__toString();
                break;
            default:
                $label = '';
                break;
        }
        return $label;
    }
}
