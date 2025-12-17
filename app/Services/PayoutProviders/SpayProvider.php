<?php

namespace App\Services\PayoutProviders;

class SpayProvider implements PayoutProviderInterface
{
    public function send(array $payload)
    {
        $url = "https://live.spay.live/api/payout/request";

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            return [
                'status' => 'failed',
                'error'  => $error ?: 'Unknown cURL error'
            ];
        }

        $decoded = json_decode($response, true);

        // Ensure we return an array even if JSON fails
        if (!is_array($decoded)) {
            return [
                'status'       => 'failed',
                'raw_response' => $response,
                'error'        => 'Invalid JSON response from Spay'
            ];
        }

        return $decoded;
    }
}
