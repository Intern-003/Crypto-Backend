<?php

namespace App\Services\PayoutProviders;

class CashfreeProvider implements PayoutProviderInterface
{
    public function send(array $payload)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://soulfuloverseas.com/Cashfree/CFPayout/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);
        curl_close($curl);

        // ⛔ Debug: Print RAW response
        // dd([
        //     'raw_response' => $response,
        //     'curl_error'   => $error,
        //     'payload_sent' => $payload
        // ]);

        if ($response === false) {
            return [
                'status'  => 'failed',
                'error'   => $error
            ];
        }

        return json_decode($response, true);
    }
}
