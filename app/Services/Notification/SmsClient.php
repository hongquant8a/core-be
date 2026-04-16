<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsClient
{
    /**
     * Call PSC sendSMS endpoint via raw SOAP XML.
     *
     * @return array{result: int, message: string}
     *
     * @throws RuntimeException on transport failure (caller is expected to catch).
     */
    public function sendSms(string $url, string $user, string $pass, string $phone, string $content): array
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
<sendSMS xmlns="http://tempuri.org/">
<userID>'.htmlspecialchars($user, ENT_XML1).'</userID>
<password>'.htmlspecialchars($pass, ENT_XML1).'</password>
<phoneNo>'.htmlspecialchars($phone, ENT_XML1).'</phoneNo>
<content>'.htmlspecialchars($content, ENT_XML1).'</content>
</sendSMS>
</soap:Body>
</soap:Envelope>';

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => 'http://tempuri.org/sendSMS',
        ])->withBody($xml, 'text/xml')->post($url);

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status().': '.$response->body());
        }

        $body = $response->body();

        // Parse response XML
        preg_match('/<result[^>]*>([^<]*)<\/result>/i', $body, $resultMatch);
        preg_match('/<message[^>]*>([^<]*)<\/message>/i', $body, $messageMatch);

        return [
            'result' => (int) ($resultMatch[1] ?? -999),
            'message' => (string) ($messageMatch[1] ?? ''),
        ];
    }
}
