<?php
/**
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Api\Data;

/**
 * Handles general payment information.
 */
interface GeneralPaymentInterface
{
    public const PAYMENT_ID       = 'payment_id';
    public const SIGNATURE        = 'signature';
    public const ERRORCODE        = 'errorcode';
    public const ERRORDESCRIPTION = 'errordescription';
    public const ERRORSOLUTION    = 'errorsolution';
    public const STATUS           = 'status';
    public const COUNTRY_CODE     = 'country_code';
    public const COUNTRY_NAME     = 'country_name';
    public const CURRENCY_CODE    = 'currency_code';
    public const CURRENCY_NAME    = 'currency_name';
    public const CURRENCY_SYMBOL  = 'currency_symbol';
    public const BANK_NAME        = 'bank_name';
    public const AMOUNT           = 'amount';
    public const REFERENCE        = 'reference';
    public const RECEIPTNO        = 'receiptno';
    public const TNXID            = 'tnxid';
    public const DATE_CREATED     = 'date_created';
    public const DATE_COMPLETED   = 'date_completed';
    public const NOTIFIED         = 'notified';
    public const REDIRECTED       = 'redirected';
    public const TIME_STAMP       = 'time_stamp';

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
