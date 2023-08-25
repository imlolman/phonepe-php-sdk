<?php

namespace PhonePe;

use GuzzleHttp\Client;
use PhonePe\PhonePe;

class BaseClass
{
    protected $config;
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->config = PhonePe::getInstance()->getConfig();
    }

    /**
     * Calculate the X-VERIFY header using the phonepe's algorithm
     * 
     * @param string $payload
     * @param string $path
     * @param string $saltKey
     * @param string $saltIndex
     * 
     * @return string
     */
    protected function calculateHeaders($payload, $path, $saltKey, $saltIndex)
    {
        $xVerify = hash('sha256', $payload . $path . $saltKey) . "###" . $saltIndex;

        return $xVerify;
    }

    /**
     * Executes the request and returns the response
     * 
     * @param string $method
     * @param string $path
     * @param array|null $payload
     * @param array $headers
     * 
     * @return array
     */
    protected function executeRequest($method, $path, $payload = NULL, $headers = [])
    {
        if ($payload) {
            // Making Original Payload
            $base64Payload = base64_encode(json_encode($payload));
            $requestBody = json_encode(['request' => $base64Payload]);

            // Calculate X-VERIFY
            $xVerify = $this->calculateHeaders($base64Payload, $path, $this->config['SALT_KEY'], $this->config['SALT_INDEX']);
        }else{
            $requestBody = NULL;
            $xVerify = $this->calculateHeaders("", $path, $this->config['SALT_KEY'], $this->config['SALT_INDEX']);
        }
        
        // Set the headers for PhonePe's Algorithm
        $headers['Content-Type'] = 'application/json';
        $headers['X-VERIFY'] = $xVerify;

        // Define the options
        $options = [
            'headers' => $headers,
            'body' => $requestBody
        ];

        $response = $this->client->request($method, $this->config['HOST'] . $path, $options);
        return json_decode($response->getBody(), true);
    }

    /**
     * Verifies the response from the server using phonepe's algorithm
     * 
     * @param string $payload
     * @param string $xVerify
     * 
     * @return bool
     */
    protected function verifyResponse($payload, $xVerify)
    {
        $xVerify = explode("###", $xVerify);
        $xVerify = $xVerify[0];
        $xVerify = $xVerify . "###" . $this->config['SALT_INDEX'];

        $xVerifyCalculated = $this->calculateHeaders($payload, "", $this->config['SALT_KEY'], $this->config['SALT_INDEX']);

        if ($xVerify == $xVerifyCalculated) {
            return true;
        }

        return false;
    }
}
