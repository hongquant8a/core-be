<?php

namespace App\Services\Notification;

use SoapClient;
use SoapFault;

class SmsClient
{
    /**
     * Call PSC sendSMS endpoint.
     *
     * @return array{result: int, message: string}
     *
     * @throws SoapFault on transport failure (caller is expected to catch).
     */
    public function sendSms(string $url, string $user, string $pass, string $phone, string $content): array
    {
        $client = new SoapClient(null, [
            'location' => $url,
            'uri' => 'http://tempuri.org/',
            'trace' => false,
            'exceptions' => true,
        ]);

        $response = $client->__soapCall('sendSMS', [
            'userID' => $user,
            'password' => $pass,
            'phoneNo' => $phone,
            'content' => $content,
        ], ['soapaction' => 'http://tempuri.org/sendSMS']);

        // Response shape: { sendSMSResult: { result: long, message: string } }
        $result = is_object($response) ? (array) $response : $response;
        $inner = $result['sendSMSResult'] ?? $result;
        $inner = is_object($inner) ? (array) $inner : $inner;

        return [
            'result' => (int) ($inner['result'] ?? -999),
            'message' => (string) ($inner['message'] ?? ''),
        ];
    }
}
