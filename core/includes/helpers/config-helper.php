<?php

namespace AICLINIC\Core\Includes\Helpers;

/**
 * A helper object for string operations.
 */
class Config_Helper
{

    private $method = 'AES-256-CBC';
    private $key;
    private $iv;

    public function __construct()
    {
        $this->key = AICLINIC_SEARCH_KEY;
        $this->iv  = AICLINIC_SEARCH_IV;
    }

    /**
     * Decode the expected RUL from the encoded string.
     *
     * @return string
     */
    public function aiclinic_get_expected_url()
    {
        $encrypted = 'p48oImjx8EUCATM3hpe+56d2/Fjw5evPBQtmZaojYt4='; //local
        return $this->aiclinic_decrypt_value($encrypted);
    }

    /**
     * $value encoding function.
     *
     * @param array $value
     * @return string
     */
    public function aiclinic_encrypt_value($value)
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return base64_encode(
            openssl_encrypt($value, $this->method, $this->key, OPENSSL_RAW_DATA, $this->iv)
        );
    }

    /**
     * Decode $encrypted from encoded string.
     *
     * @param string $encrypted
     * @return array
     */
    public function aiclinic_decrypt_value($encrypted)
    {
        $decrypted = openssl_decrypt(
            base64_decode($encrypted),
            $this->method,
            $this->key,
            OPENSSL_RAW_DATA,
            $this->iv
        );

        $json = json_decode($decrypted, true);
        return $json !== null ? $json : $decrypted;
    }
}
