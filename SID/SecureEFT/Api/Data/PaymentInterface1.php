<?php
/**
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Api\Data;

interface PaymentInterface1
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

    public function setPaymentId($paymentId);

    public function getSignature();

    public function setSignature($signature);

    public function getErrorCode();

    public function setErrorCode($errorCode);

    public function getErrorDescription();

    public function setErrorDescription($errorDescription);

    public function getErrorSolution();

    public function setErrorSolution($errorSolution);

    public function getStatus();

    public function setStatus($status);

    public function getCountryCode();

    public function setCountryCode($countryCode);

    public function getCountryName();

    public function setCountryName($countryName);

    public function getCurrencyCode();

    public function setCurrencyCode($currencyCode);

    public function getCurrencyName();

    public function setCurrencyName($currencyName);
}
