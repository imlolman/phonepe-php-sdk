<?php

namespace PhonePe;

class PhonePe
{
    private static $instance;
    private $config;

    private function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Get the instance of the PhonePe class, which is initialized using the init() method and stored in the static variable
     * 
     * @return PhonePe
     */
    public static function getInstance()
    {
        if (null === static::$instance) {
            throw new \Exception("PhonePe not initialized");
        }

        return static::$instance;
    }

    /**
     * Initialize the PhonePe class
     * 
     * @param string $merchantId
     * @param string $merchantUserId
     * @param string $salt_key
     * @param string $salt_index
     * @param string $redirectUrl // Can be defined on per transaction basis
     * @param string $callbackUrl // Can be defined on per transaction basis
     * @param string $mode // DEV or PROD
     * @param string $redirectMode // redirect or post
     */
    public static function init($merchantId, $merchantUserId, $salt_key, $salt_index, $redirectUrl = "", $callbackUrl = "", $mode = "DEV", $redirectMode = "redirect")
    {
        if (null === static::$instance) {
            if ($mode == "PROD") {
                $host = "https://api.phonepe.com/apis/hermes";
            } else {
                $host = "https://api-preprod.phonepe.com/apis/pg-sandbox";
            }

            static::$instance = new static([
                "MERCHANT_ID" => $merchantId,
                "MERCHANT_USER_ID" => $merchantUserId,
                "SALT_KEY" => $salt_key,
                "SALT_INDEX" => $salt_index,
                "REDIRECT_URL" => $redirectUrl,
                "CALLBACK_URL" => $callbackUrl,
                "HOST" => $host,
                "REDIRECT_MODE" => $redirectMode,
            ]);
        }

        return static::$instance;
    }

    /**
     * Get the config of the PhonePe class
     * 
     * @param string $key
     * 
     * @return array
     */
    public function getConfig($key = NULL)
    {
        if ($key) {
            return $this->config[$key];
        }

        return $this->config;
    }

    /**
     * Get the standard checkout method
     * 
     * @return StandardCheckout
     */
    public function standardCheckout()
    {
        return new Methods\StandardCheckout();
    }

    /**
     * Get the custom checkout method
     * 
     * @return CustomCheckout
     */
    public function customCheckout()
    {
        return new Methods\CustomCheckout();
    }

    /**
     * Get the recurring checkout method
     * 
     * @return RecurringCheckout
     */
    public function recurringCheckout()
    {
        return new Methods\RecurringCheckout();
    }
}
