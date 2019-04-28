<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Model;

use Magento\Framework\DataObject\IdentityInterface;
use SID\InstantEFT\Api\Data\PaymentInterface;

class Payment extends \Magento\Framework\Model\AbstractModel implements PaymentInterface, IdentityInterface
{
    const CACHE_TAG = 'sid_payment';

    public function _construct()
    {
        $this->_init( 'SID\InstantEFT\Model\ResourceModel\Payment' );
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getPaymentId()];
    }

    public function getPaymentId()
    {
        return $this->getData( self::PAYMENT_ID );
    }

    public function setPaymentId( $paymentId )
    {
        return $this->setData( self::PAYMENT_ID, $paymentId );
    }

    public function getSignature()
    {
        return $this->getData( self::SIGNATURE );
    }

    public function setSignature( $signature )
    {
        return $this->setData( self::SIGNATURE, $signature );
    }

    public function getErrorCode()
    {
        return $this->getData( self::ERRORCODE );
    }

    public function setErrorCode( $errorCode )
    {
        return $this->setData( self::ERRORCODE, $errorCode );
    }

    public function getErrorDescription()
    {
        return $this->getData( self::ERRORDESCRIPTION );
    }

    public function setErrorDescription( $errorDescription )
    {
        return $this->setData( self::ERRORDESCRIPTION, $errorDescription );
    }

    public function getErrorSolution()
    {
        return $this->getData( self::ERRORSOLUTION );
    }

    public function setErrorSolution( $errorSolution )
    {
        return $this->setData( self::ERRORSOLUTION, $errorSolution );
    }

    public function getStatus()
    {
        return $this->getData( self::STATUS );
    }

    public function setStatus( $status )
    {
        return $this->setData( self::STATUS, $status );
    }

    public function getCountryCode()
    {
        return $this->getData( self::COUNTRY_CODE );
    }

    public function setCountryCode( $countryCode )
    {
        return $this->setData( self::COUNTRY_CODE, $countryCode );
    }

    public function getCountryName()
    {
        return $this->getData( self::COUNTRY_NAME );
    }

    public function setCountryName( $countryName )
    {
        return $this->setData( self::COUNTRY_NAME, $countryName );
    }

    public function getCurrencyCode()
    {
        return $this->getData( self::CURRENCY_CODE );
    }

    public function setCurrencyCode( $currencyCode )
    {
        return $this->setData( self::CURRENCY_CODE, $currencyCode );
    }

    public function getCurrencyName()
    {
        return $this->getData( self::CURRENCY_NAME );
    }

    public function setCurrencyName( $currencyName )
    {
        return $this->setData( self::CURRENCY_NAME, $currencyName );
    }

    public function getCurrencySymbol()
    {
        return $this->getData( self::CURRENCY_SYMBOL );
    }

    public function setCurrencySymbol( $currencySymbol )
    {
        return $this->setData( self::CURRENCY_SYMBOL, $currencySymbol );
    }

    public function getBankName()
    {
        return $this->getData( self::BANK_NAME );
    }

    public function setBankName( $bankName )
    {
        return $this->setData( self::BANK_NAME, $bankName );
    }

    public function getAmount()
    {
        return $this->getData( self::AMOUNT );
    }

    public function setAmount( $amount )
    {
        return $this->setData( self::AMOUNT, $amount );
    }

    public function getReference()
    {
        return $this->getData( self::REFERENCE );
    }

    public function setReference( $reference )
    {
        return $this->setData( self::REFERENCE, $reference );
    }

    public function getReceiptNo()
    {
        return $this->getData( self::RECEIPTNO );
    }

    public function setReceiptNo( $receiptNo )
    {
        return $this->setData( self::RECEIPTNO, $receiptNo );
    }

    public function getTnxId()
    {
        return $this->getData( self::TNXID );
    }

    public function setTnxId( $tnxId )
    {
        return $this->setData( self::TNXID, $tnxId );
    }

    public function getDateCreated()
    {
        return $this->getData( self::DATE_CREATED );
    }

    public function setDateCreated( $dateCreated )
    {
        return $this->setData( self::DATE_CREATED, $dateCreated );
    }

    public function getDateReady()
    {
        return $this->getData( self::DATE_READY );
    }

    public function setDateReady( $dateReady )
    {
        return $this->setData( self::DATE_READY, $dateReady );
    }

    public function getDateCompleted()
    {
        return $this->getData( self::DATE_COMPLETED );
    }

    public function setDateCompleted( $dateCompleted )
    {
        return $this->setData( self::DATE_COMPLETED, $dateCompleted );
    }

    public function getNotified()
    {
        return $this->getData( self::NOTIFIED );
    }

    public function setNotified( $notified )
    {
        return $this->setData( self::NOTIFIED, $notified );
    }

    public function getRedirected()
    {
        return $this->getData( self::REDIRECTED );
    }

    public function setRedirected( $redirected )
    {
        return $this->setData( self::REDIRECTED, $redirected );
    }

    public function getTimeStamp()
    {
        return $this->getData( self::TIME_STAMP );
    }

    public function setTimeStamp( $timeStamp )
    {
        return $this->setData( self::TIME_STAMP, $timeStamp );
    }
}
