<?php
/**
 * Copyright (c) 2025 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace SID\SecureEFT\Helper;

use stdClass;
use GuzzleHttp\Client;

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
        $query    = $this->doAPICall($apiQuery);
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
     *
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
     *
     * @return string
     */
    private function doAPICall($uri, array $data = []): string
    {
        $client = new Client([
                                 'auth' => [$this->username, $this->password], // Basic authentication
                             ]);

        try {
            $options = [];
            if (!empty($data)) {
                $options['form_params'] = $data; // Use form parameters for POST
            }

            $response = $client->request(empty($data) ? 'GET' : 'POST', $uri, $options);

            return $response->getBody()->getContents();
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $e->getMessage(); // Return error message on failure
        }
    }
}
