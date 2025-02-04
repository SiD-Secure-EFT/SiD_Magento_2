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
 * Handles financial details related to a payment.
 */
interface FinancialDetailsInterface
{
    public function getCurrencySymbol();

    public function setCurrencySymbol($currencySymbol);

    public function getBankName();

    public function setBankName($bankName);

    public function getAmount();

    public function setAmount($amount);

    public function getReference();

    public function setReference($reference);

    public function getReceiptNo();

    public function setReceiptNo($receiptNo);

    public function getTnxId();

    public function setTnxId($tnxId);

    public function getDateCreated();

    public function setDateCreated($dateCreated);

    public function getDateCompleted();

    public function setDateCompleted($dateCompleted);

    public function getNotified();

    public function setNotified($notified);
}
