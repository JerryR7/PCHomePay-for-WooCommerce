<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Created by PhpStorm.
 * User: Jerry
 * Date: 17/10/18
 * Time: 上午10:36
 */
class PChomePayClient
{
    const BASE_URL = "https://api.pchomepay.com.tw/v1";
    const SB_BASE_URL = "https://sandbox-api.pchomepay.com.tw/v1";

    public function __construct($appID, $secret, $sandBox = false)
    {
        $baseURL = $sandBox ? PChomePayClient::SB_BASE_URL : PChomePayClient::BASE_URL;

        $this->appID = $appID;
        $this->secret = $secret;

        $this->tokenURL = $baseURL . "/token";
        $this->postPaymentURL = $baseURL . "/payment";
        $this->getPaymentURL = $baseURL . "/payment/{order_id}";
        $this->getRefundURL = $baseURL . "/refund/{refund_id}";
        $this->postRefundURL = $baseURL . "/refund";

        $this->userAuth = "{$appID}:{$secret}";
    }

    // 建立訂單
    public function postPayment($data)
    {
        return $this->send_request($this->postPaymentURL, $data);
    }

    // 建立退款
    public function postRefund($data)
    {
        return $this->send_request($this->postRefundURL, $data);
    }

    // 取Token
    protected function getToken()
    {
        $userAuth = "{$this->appID}:{$this->secret}";

        $r = wp_remote_post($this->tokenURL, array(
            'headers' => array(
                'Content-type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($userAuth),
            ),
        ));

        $body = wp_remote_retrieve_body($r);

        return $this->handleResult($body);
    }

    protected function send_request($method, $postdata)
    {
        $token = json_decode($this->getToken());

        $r = wp_remote_post($method, array(
            'headers' => array(
                'Content-type' => 'application/json',
                'pcpay-token' => $token->token,
            ),
            'body' => $postdata,
        ));

        $body = wp_remote_retrieve_body($r);

        return $this->handleResult($body);
    }

    private function handleResult($result)
    {
        $jsonErrMap = [
            JSON_ERROR_NONE => 'No error has occurred',
            JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_SYNTAX => 'Syntax error',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded	PHP 5.3.3',
            JSON_ERROR_RECURSION => 'One or more recursive references in the value to be encoded	PHP 5.5.0',
            JSON_ERROR_INF_OR_NAN => 'One or more NAN or INF values in the value to be encoded	PHP 5.5.0',
            JSON_ERROR_UNSUPPORTED_TYPE => 'A value of a type that cannot be encoded was given	PHP 5.5.0'
        ];

        $obj = json_decode($result);

        $err = json_last_error();

        if ($err) {
            $errStr = "($err)" . $jsonErrMap[$err];
            if (empty($errStr)) {
                $errStr = " - unknow error, error code ({$err})";
            }
            throw new Exception("server result error($err) {$errStr}:$result");
        }

        if (isset($obj->error_type)) {
            throw new Exception("交易失敗，請聯絡網站管理員。錯誤代碼：" . $obj->code);
        }

        return $result;
    }
}