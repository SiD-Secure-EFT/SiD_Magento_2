<?php
/**
 * Copyright (c) 2023 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Helper;

use stdClass;

class SidAPI
{
    public const API_BASE = "https://www.sidpayment.com/services/api/v30";
    private array $queryArr;
    private string $username;
    private string $password;

    /**
     * @param $queryArr
     * @param $username
     * @param $password
     */
    public function __construct($queryArr, $username, $password)
    {
        $this->queryArr = $queryArr;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @return stdClass|bool|null
     */
    public function retrieveTransaction(): stdClass|bool|null
    {
        $apiQuery = self::API_BASE . "/transactions" . $this->buildQueryString();
        $query = $this->doAPICall($apiQuery);
        if ($query) {
            return end(json_decode($query)->transactions);
        }
        return false;
    }

    /**
     * @return string
     */
    private function buildQueryString(): string
    {
        $queryString = "/query?";
        foreach ($this->queryArr as $query => $value) {
            $queryString .= $query . "=" . $value . "&";
        }
        return rtrim($queryString, "&");
    }

    public function refundReport(): stdClass|bool|null
    {
        $uri = self::API_BASE . "/refunds" . str_replace("/query", "", $this->buildQueryString());

        return json_decode($this->doAPICall($uri));
    }

    /**
     * @param $transactionId
     * @param $amount
     * @return stdClass|bool|null
     */
    public function processRefund($transactionId, $amount): stdClass|bool|null
    {
        $this->queryArr = [
            "transactionId" => $transactionId,
            "refundAmount"  => $amount
        ];

        return json_decode($this->doAPICall(self::API_BASE . "/refunds", $this->queryArr));
    }

    /**
     * @param $uri
     * @param array $data
     * @return string
     */
    private function doAPICall($uri, array $data = []): string
    {
        $ch = curl_init();
        $curlConfig = array(
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->username . ":" . $this->password
        );

        if (!empty($data)) {
            $curlConfig[CURLOPT_POST] = true;
            $curlConfig[CURLOPT_POSTFIELDS] = http_build_query($data);
        }

        curl_setopt_array($ch, $curlConfig);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return curl_error($ch);
        }

        return $response;
    }
}
