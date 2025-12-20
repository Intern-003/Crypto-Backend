<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\GlideTransactionService;

class GlidePayOutService
{
    protected string $apiKey;
    protected string $projectId;
    protected string $baseUrl;
    protected $tractionService;

    public function __construct(GlideTransactionService $glideTransaction)
    {
        $this->$tractionService = $glideTransaction;
        $this->apiKey = env('GLIDE_API_KEY');
        $this->projectId = env('GLIDE_PROJECT_ID');
        $this->baseUrl = 'https://api.paywithglide.xyz/widget/'; // Glide API base
    }

    /**
     * Create a payment session
     *
     * @param array $params [
     *   paymentCurrency => ['symbol' => 'GTT', 'chain' => 'base'],
     *   settleCurrency  => ['symbol' => 'USDC', 'chain' => 'polygon'],
     *   recipientWallet => '0x...',
     *   paymentAmount   => '0.1',
     *   payerAccount    => '0x...',
     *   walletSecret    => '...'
     * ]
     *
     * @return array
     */
    public function createPaymentSession(array $params): array
    {
        $metadata = [
            'orderId' => $params['orderid'],
            'userId'  => $params['user_id'],
            'token'   => $params['token']
        ];

        // Optional: encrypt metadata if you have a service
        $metaTokenHash = $this->$tractionService->processMetaData('encrypt', $metadata);
        $metaToken = $metaTokenHash["encrypted"];

        Log::info('Glide Payment Session Widget Request - PayOut', [
            'mode' => 'pay',
            'amount' =>  $params["amount"],
            'metadata' => $metaToken,
        ]);

        $paymentCurrency = '';
        $settleCurrency = '';

        try {
            //code...
             $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'X-Glide-Project-ID' => $this->projectId,
                'Content-Type' => 'application/json',
            ])->post($this->endpoint, [
                'recipientWallet' => $params["buyer_wallet"],
                'paymentAmount'   => $params["amount"],
                'paymentCurrency' => $paymentCurrency,
                'settleCurrency'  => $settleCurrency,
                'metadata'        => $metaToken
            ]);

        } catch (\Throwable $th) {
            //throw $th;
        }
        

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-Glide-Project-ID' => $this->projectId,
        ])->post($this->baseUrl . '/payment-sessions', $params);

        if ($response->successful()) {
            return $response->json();
        }

        // Optionally handle errors more gracefully
        throw new \Exception('Glide API Error: ' . $response->body());
    }
}
