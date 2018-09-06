<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 * 
 * Released under the GNU General Public License
 */
namespace SID\InstantEFT\Api\Data;

interface PaymentInterface
{
    const PAYMENT_ID       = 'payment_id';
    const SIGNATURE        = 'signature';
    const ERRORCODE        = 'errorcode';
    const ERRORDESCRIPTION = 'errordescription';
    const ERRORSOLUTION    = 'errorsolution';
    const STATUS           = 'status';
    const COUNTRY_CODE     = 'country_code';
    const COUNTRY_NAME     = 'country_name';
    const CURRENCY_CODE    = 'currency_code';
    const CURRENCY_NAME    = 'currency_name';
    const CURRENCY_SYMBOL  = 'currency_symbol';
    const BANK_NAME        = 'bank_name';
    const AMOUNT           = 'amount';
    const REFERENCE        = 'reference';
    const RECEIPTNO        = 'receiptno';
    const TNXID            = 'tnxid';
    const DATE_CREATED     = 'date_created';
    const DATE_READY       = 'date_ready';
    const DATE_COMPLETED   = 'date_completed';
    const NOTIFIED         = 'notified';
    const REDIRECTED       = 'redirected';
    const TIME_STAMP       = 'time_stamp';

    public function getPaymentId();
    public function setPaymentId( $paymentId );

    public function getSignature();
    public function setSignature( $signature );

    public function getErrorCode();
    public function setErrorCode( $errorCode );

    public function getErrorDescription();
    public function setErrorDescription( $errorDescription );

    public function getErrorSolution();
    public function setErrorSolution( $errorSolution );

    public function getStatus();
    public function setStatus( $status );

    public function getCountryCode();
    public function setCountryCode( $countryCode );

    public function getCountryName();
    public function setCountryName( $countryName );

    public function getCurrencyCode();
    public function setCurrencyCode( $currencyCode );

    public function getCurrencyName();
    public function setCurrencyName( $currencyName );

    public function getCurrencySymbol();
    public function setCurrencySymbol( $currencySymbol );

    public function getBankName();
    public function setBankName( $bankName );

    public function getAmount();
    public function setAmount( $amount );

    public function getReference();
    public function setReference( $reference );

    public function getReceiptNo();
    public function setReceiptNo( $receiptNo );

    public function getTnxId();
    public function setTnxId( $tnxId );

    public function getDateCreated();
    public function setDateCreated( $dateCreated );

    public function getDateReady();
    public function setDateReady( $dateReady );

    public function getDateCompleted();
    public function setDateCompleted( $dateCompleted );

    public function getNotified();
    public function setNotified( $notified );

    public function getRedirected();
    public function setRedirected( $redirected );

    public function getTimeStamp();
    public function setTimeStamp( $timeStamp );
}
