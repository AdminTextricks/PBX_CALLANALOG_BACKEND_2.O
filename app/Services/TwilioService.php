<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    protected $client;

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        if (!$sid || !$token) {
            \Log::info(env('TWILIO_ACCOUNT_SID'));
            throw new \Exception('Twilio credentials are missing');
        }
        $this->client = new Client($sid, $token);
       // $this->client = new Client(config('services.twilio.sid'), config('services.twilio.token'));
    }

    public function sendSms($to, $message)
    {
		try {
			return $this->client->messages->create($to, [
				'from' => config('services.twilio.from'),
				'body' => $message,
			]);
		} catch (\Twilio\Exceptions\RestException $e) {
			\Log::error("Twilio error: " . $e->getMessage());
			throw $e;
		}
    }
}
