<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class CallbackService{
    public static function merchantCallBackResponse($callbackurl, $status, $txnid, $mytxnid, $amount,$referenceId,$timestamp)
    {
        
        Log::debug("Callback function called", [
        'callbackurl' => $callbackurl,
        'status'      => $status,
        'txnid'       => $txnid,
        'clienttxnid' => $mytxnid,
        'amount'      => $amount,
        'UTR'         => $referenceId,
        'timestamp'   => $timestamp,
        ]);
        $postData = [
            'status'    => $status,
            'txnid'     => $txnid,
            'clienttxnid' => $mytxnid,
            'amount'    => $amount,
            'UTR'       => $referenceId,
            'timestamp' => $timestamp,
        ];
    
        $ch = curl_init($callbackurl);
    
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData)); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // ✅ Follow redirects
        curl_setopt($ch, CURLOPT_USERAGENT, 'SpayWebhookBot/1.0'); // ✅ Set user agent
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // prevent hanging forever
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
    
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            Log::error("Callback Error: $error", [
                'callbackurl' => $callbackurl,
                'data' => $postData
            ]);
        } else {
            Log::info("Callback sent", [
                'url' => $callbackurl,
                'data' => $postData,
                'http_code' => $httpCode
            ]);
        }
    
        curl_close($ch);
    }
}