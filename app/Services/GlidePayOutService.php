<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\GlideTransactionService;

class GlidePayOutService
{
    protected $apiKey;
    protected $projectId;
    protected $baseUrl;
    protected $tractionService;

    public function __construct(GlideTransactionService $glideTransaction)
    {
        $this->$tractionService = $glideTransaction;
        $this->apiKey = env('GLIDE_API_KEY');
        $this->projectId = env('GLIDE_PROJECT_ID');
        $this->baseUrl = env('GLIDE_API_CREATEPAYMENTSESSION_URL', 'https://api.paywithglide.xyz/widget/payment-sessions');
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
            'orderid'   => $params["amount"],
            'user_id'   => $params["user_id"],
            'token'     => $params["token"],
            'amount'    => $params["amount"],
            'metadata'  => $metaToken,
        ]);

        $paymentCurrency = $this->fetchCurrencies('payment');
        $settleCurrency = $this->fetchCurrencies('settle');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'X-Glide-Project-ID' => $this->projectId,
                'Content-Type' => 'application/json',
            ])->post(env('GLIDE_API_CREATEPAYMENTSESSION_URL'), [
                'recipientWallet' => $params["buyer_wallet"],
                'paymentAmount'   => $params["amount"],
                'paymentCurrency' => $paymentCurrency,
                'settleCurrency'  => $settleCurrency,
                'payerAccount'    => env('GLIDE_PAYER_ACCOUNT'),   
                'walletSecret'    => env('GLIDE_WALLET_SECRET'),   
                'metadata'        => $metaToken
            ]);

            if ($response->successful()) {
                try {
                    

                    Log::info('Glide Payment Session Widget Request - PayOut', [
                        'glide_request' => json_encode([
                            'orderid'   => $params["amount"],
                            'user_id'   => $params["user_id"],
                            'token'     => $params["token"],
                            'amount'    => $params["amount"],
                            'metadata'  => $metaToken
                        ]),
                        'glide_response' => $response->body(),
                    ]);

                    return response()->json([
                        
                    ], 200); // return session data

                } catch (\Exception $e) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Store Session Request failed: ' . $e->getMessage()
                    ], 500);
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Glide Payment Session Error', ['message' => $e->getMessage()]);
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Fetch currencies dynamically from Glide
     */
    private function fetchCurrencies($type = 'payment')
    {
        $currencies = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey
        ])->get('https://api.paywithglide.xyz/v1/currencies')->json();

        // GTT on Base (testing)
        $gttBase = collect($currencies)->first(fn($token) =>
            $token['symbol'] === 'GTT' && strpos($token['caip19'], 'eip155:8453') === 0
        );

        // USDC on Polygon (settle)
        $usdcPolygon = collect($currencies)->first(fn($token) =>
            $token['symbol'] === 'USDC' && strpos($token['caip19'], 'eip155:137') === 0
        );

        // USDC on Ethereum (payment in production)
        $usdcEthereum = collect($currencies)->first(fn($token) =>
            $token['symbol'] === 'USDC' && strpos($token['caip19'], 'eip155:1') === 0
        );

        if ($type === 'payment') {
            // Choose USDC/Ethereum in production or GTT/Base in testing
            return env('APP_ENV') === 'production' 
                ? ['caip19' => $usdcEthereum['caip19']] 
                : ['caip19' => $gttBase['caip19']];
        }

        // settleCurrency is always USDC/Polygon
        return ['caip19' => $usdcPolygon['caip19']];
    }

}
