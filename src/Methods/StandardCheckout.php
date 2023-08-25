<?php

namespace PhonePe\Methods;

use PhonePe\BaseClass;

class StandardCheckout extends BaseClass
{
    private $transaction;
    private $transactionResponse;
    const TRANSACTION_STATUS_SUCCESS = "PAYMENT_SUCCESS";

    /**
     * Create a new transaction for the user as per https://developer.phonepe.com/v1/reference/pay-api-1
     * 
     * @param int $amountInPaisa
     * @param string $mobile
     * @param string $merchentTransactionID
     * @param string $redirectUrl
     * @param string $callbackUrl
     * @param string $redirectMode // REDIRECT or POST
     * 
     * @return StandardCheckout
     */
    public function createTransaction($amountInPaisa, $mobile, $merchentTransactionID, $redirectUrl = NULL, $callbackUrl = NULL, $redirectMode = "REDIRECT"){
        // Check if the amount is valid
        if(!is_numeric($amountInPaisa)){
            throw new \Exception("Amount should be numeric");
        }

        // Check if the mobile number is valid
        if(!is_numeric($mobile)){
            throw new \Exception("Mobile number should be numeric");
        }

        // According to the api, the redirect mode should be REDIRECT or POST
        if($redirectMode != "REDIRECT" && $redirectMode != "POST"){
            throw new \Exception("Invalid redirect mode, should be REDIRECT or POST");
        }

        
        if(!$redirectUrl){
            $redirectUrl = $this->config['REDIRECT_URL'];
        }
        if(!$callbackUrl){
            $callbackUrl = $this->config['CALLBACK_URL'];
        }

        $payload = [
            "merchantId" => $this->config['MERCHANT_ID'],
            "merchantTransactionId" => $merchentTransactionID,
            "merchantUserId" => $this->config['MERCHANT_USER_ID'],
            "amount" => $amountInPaisa,
            "redirectUrl" => $redirectUrl,
            "redirectMode" => $redirectMode,
            "callbackUrl" => $callbackUrl,
            "mobileNumber" => $mobile,
            "paymentInstrument" => [
                "type" => "PAY_PAGE"
            ]
        ];

        $response = $this->executeRequest("POST", "/pg/v1/pay", $payload);
        $this->transaction = $response;
        return $this;
    }

    /**
     * If the transaction is created, this will return the full transaction response from the server
     * 
     * @return array
     */
    public function getCreatedTransaction(){
        if(!$this->transaction){
            throw new \Exception("Transaction not created");
        }

        return $this->transaction;
    }

    /**
     * If the transaction is created, this will return the transaction URL from the transaction response from the server
     * 
     * @return string
     */
    public function getTransactionUrl(){
        if(!$this->transaction){
            throw new \Exception("Transaction not created");
        }

        return $this->transaction['data']['instrumentResponse']['redirectInfo']['url'];
    }

    /**
     * Verifies the response from the server using phonepe's algorithm
     * 
     * @param string $payload
     * @param string $xVerify
     * 
     * @return bool
     */
    public function verifyResponse($payload, $xVerify){
        return parent::verifyResponse($payload, $xVerify);
    }

    /**
     * Verifies the response from the server using phonepe's algorithm and returns the decoded response
     * 
     * @param string $payload
     * @param string $xVerify
     * 
     * @return array|bool
     */
    public function verifyAndGetResponse($payload, $xVerify){
        if($this->verifyResponse($payload, $xVerify)){
            return json_decode(base64_decode($payload), true);
        }
        return false;
    }

    /**
     * Verifies the response from the server directly using the global variables
     * 
     * @return array
     */
    public function verifyPaymentFromCallback(){
        // Check if the request method is not POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new \Exception("Invalid request method");
        }

        // Check if X-VERIFY is set
        if (!isset($_SERVER['HTTP_X_VERIFY'])) {
            throw new \Exception("X-VERIFY not set");
        }
        $xVerify = $_SERVER['HTTP_X_VERIFY'];

        // Get Content
        $content = trim(file_get_contents("php://input"));
        $payload = json_decode($content, true);
        $base64Payload = $payload['response'];

        // Validate X-VERIFY
        if(!$this->verifyResponse($base64Payload, $xVerify)){
            throw new \Exception("Invalid X-VERIFY");
        }

        // Decode Payload
        $payload = json_decode(base64_decode($base64Payload), true);

        // store transaction response
        $this->transactionResponse = $payload;

        return $payload;
    }

    /**
     * Verifies the response from the server directly using the global variables and returns the decoded response
     * 
     * @return array
     */
    public function getTransactionResponse(){
        if(!$this->transactionResponse){
            $this->verifyPaymentFromCallback();
        }

        return $this->transactionResponse;
    }

    /**
     * Verifies the response from the server directly using the global variables and returns the transaction ID
     * 
     * @return string
     */
    public function getTransactionId(){
        if(!$this->transactionResponse){
            $this->verifyPaymentFromCallback();
        }

        return $this->transactionResponse['data']['transactionId'];
    }

    /**
     * Verifies the response from the server directly using the global variables and returns the transaction status
     * 
     * @return string
     */
    public function getTransactionStatus(){
        if(!$this->transactionResponse){
            $this->verifyPaymentFromCallback();
        }

        return $this->transactionResponse['code'];
    }

    /**
     * Verifies the response from the server directly using the global variables and returns if the transaction is success or not
     * 
     * @return bool
     */
    public function isTransactionSuccess(){
        if($this->getTransactionStatus() == self::TRANSACTION_STATUS_SUCCESS){
            return true;
        }
        return false;
    }

    /**
     * Verifies the response from the server directly using the global variables and returns the transaction amount
     * 
     * @return int
     */
    public function getTransactionAmount(){
        if(!$this->transactionResponse){
            $this->verifyPaymentFromCallback();
        }

        return $this->transactionResponse['data']['amount'];
    }

    /**
     * Verifies the response from the server directly using the global variables and returns the transaction payment instrument
     * 
     * @return array
     */
    public function getTransactionPaymentInstrument(){
        if(!$this->transactionResponse){
            $this->verifyPaymentFromCallback();
        }

        return $this->transactionResponse['data']['paymentInstrument'];
    }

    /**
     * Verifies the response from the server directly using the global variables and returns the transaction payment instrument type
     * 
     * @return string
     */
    public function getTransactionPaymentInstrumentType(){
        if(!$this->transactionResponse){
            $this->verifyPaymentFromCallback();
        }

        return $this->transactionResponse['data']['paymentInstrument']['type'];
    }

    /**
     * Get the transaction response from the server using the transaction ID
     * 
     * @param string $transactionId
     * 
     * @return array
     */
    public function getTransactionResponseByTransactionId($transactionId){
        $response = $this->executeRequest("GET", "/pg/v1/status/".$this->config['MERCHANT_ID']."/".$transactionId, NULL, [
            'X-MERCHANT-ID' => $this->config['MERCHANT_ID']
        ]);
        return $response;
    }

    /**
     * Get the transaction status from the server using the transaction ID
     * 
     * @param string $transactionId
     * 
     * @return string
     */
    public function getTransactionStatusByTransactionId($transactionId){
        $response = $this->getTransactionResponseByTransactionId($transactionId);
        return $response['code'];
    }

    /**
     * Get if the transaction is success or not from the server using the transaction ID
     * 
     * @param string $transactionId
     * 
     * @return bool
     */
    public function isTransactionSuccessByTransactionId($transactionId){
        if($this->getTransactionStatusByTransactionId($transactionId) == self::TRANSACTION_STATUS_SUCCESS){
            return true;
        }
        return false;
    }

    /**
     * Get the transaction amount from the server using the transaction ID
     * 
     * @param string $transactionId
     * 
     * @return int
     */
    public function getTransactionAmountByTransactionId($transactionId){
        $response = $this->getTransactionResponseByTransactionId($transactionId);
        return $response['data']['amount'];
    }

    /**
     * Get the transaction payment instrument from the server using the transaction ID
     * 
     * @param string $transactionId
     * 
     * @return array
     */
    public function getTransactionPaymentInstrumentByTransactionId($transactionId){
        $response = $this->getTransactionResponseByTransactionId($transactionId);
        return $response['data']['paymentInstrument'];
    }

    /**
     * Get the transaction payment instrument type from the server using the transaction ID
     * 
     * @param string $transactionId
     * 
     * @return string
     */
    public function getTransactionPaymentInstrumentTypeByTransactionId($transactionId){
        $response = $this->getTransactionResponseByTransactionId($transactionId);
        return $response['data']['paymentInstrument']['type'];
    }
}