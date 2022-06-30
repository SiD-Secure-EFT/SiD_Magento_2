<?php
/**
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Api\Data;

interface PaymentInterface2
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

    public function getDateReady();

    public function setDateReady($dateReady);

    public function getDateCompleted();

    public function setDateCompleted($dateCompleted);

    public function getNotified();

    public function setNotified($notified);
}
