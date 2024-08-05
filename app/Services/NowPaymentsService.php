<?php

namespace App\Services;

use GuzzleHttp\Client;

class NowPaymentsService
{
    protected $client;
    protected $apiKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = 'BE5A9TP-666MRMH-NKSJZH0-E8XTN4B';
    }

    public function createPayment($price_currency, $price_amount, $orderId, $pay_currency, $email)
    {
        $price_currency . $price_amount .  $orderId .  $pay_currency;
        $response = $this->client->post('https://api.nowpayments.io/v1/payment', [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
            ],
            'json' => [
                'price_currency' => $price_currency,
                'price_amount' => $price_amount,
                'order_id' => $orderId,
                'pay_currency' => $pay_currency,
                'customer_email' => $email,
                'ipn_callback_url' => 'https://pbx.callanalog.com/'
            ],
        ]);

        return json_decode($response->getBody(), true);
    }


    public function getPaymentStatus($paymentId)
    {
        $response = $this->client->get("https://api.nowpayments.io/v1/payment/{$paymentId}", [
            'headers' => [
                'x-api-key' => $this->apiKey,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }


    public function refundPayment($paymentId, $amount)
    {
        $response = $this->client->post("https://api.nowpayments.io/v1/payment/refund", [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
            ],
            'json' => [
                'payment_id' => $paymentId,
                'amount' => $amount,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }
}
