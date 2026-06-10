<?php

namespace App\Http\Controllers\Api\Payin\Crypto\Glide;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\GlideSession;
use Illuminate\Support\Facades\Crypt;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GlideTransactionService;
//use App\Models\Credential;
use App\Models\Scheme;
use App\Models\AuthToken;
use App\Models\Report;
use Validator;
use Illuminate\Support\Facades\Log;

class GlidePaymentGatewayController extends Controller
{

    protected $service;

    public function __construct(GlideTransactionService $service)
    {
        $this->service = $service;
    }

    private function createGlideWidgetSession($params)
    {

        // Create metadata as JSON string
        $metadata = [
            'orderId' => $params["orderid"],
            'userId' => $params["user_id"],
            'token' => $params["token"]
        ];

        $metaTokenHash = $this->service->processMetaData('encrypt', $metadata);
        $metaToken = $metaTokenHash["encrypted"];
        //$metaToken = $metaTokenHash;

        Log::info('Glide Widget Request - PayIn', [
            'mode' => 'pay',
            'amount' => $params["amount"],
            'metadata' => $metaToken,
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('GLIDE_API_KEY'),
            'X-Glide-Project-ID' => env('GLIDE_PROJECT_ID')
        ])->post(env('GLIDE_API_CREATESESSION_URL'), [
                    'mode' => 'pay',
                    'amount' => $params["amount"],
                    'metadata' => $metaToken, //$metaTokenHash,  // string (correct)
                ]);

        if ($response->successful()) {

            try {
                // DB::table('glide_sessions')->insert([
                //     'glide_request' => json_encode([
                //         'mode' => 'pay',
                //         'amount' => $params["amount"],
                //         'metadata' => $metaToken,
                //     ]),
                //     'glide_response' => $response->body(),
                //     'stages' => 'Glide-Session-Initialization',
                //     'created_at' => now(),
                //     'updated_at' => now(),
                // ]);

                Log::info('Glide-Session-Initialization', [
                    'glide_request' => json_encode([
                        'mode' => 'pay',
                        'amount' => $params["amount"],
                        'metadata' => $metaToken,
                    ]),
                    'glide_response' => $response->body(),
                ]);

                return $response->json(); // return session data

            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store Session Request failed: ' . $e->getMessage()
                ], 500);
            }
        }

        return null;
    }

    public function generateRandomIds()
    {
        // Generate 10 IDs of length 20

        // $metadata = [
        //     'orderId' => "6cwBmsyHS7Mo1AevyGVU",
        //     'userId'  => 5,
        //     'token'   => "L3szdVgxEHYqq433GIvwcaQOszSx5J"
        // ];

        // $metaTokenHash = $this->service->processMetaData('encrypt', $metadata);

        // $hash = base_convert(substr(hash('sha256', $metaTokenHash["encrypted"]), 0, 30), 16, 36);
        // $shorterHash = substr($hash, 0, 35) ?? null;

        // dd($metaTokenHash, $shorterHash);

        $ids = $this->service->generateRandomIds(10, 20);

        dd($ids);

        // $encrypted = "eNodkFtvgjAARv9SKdPEhz24cbHV1tEr7dukbi0UR5BM5NeP7PUk5yTfd33iwQW0RT0AEsxbnrDMHVhm5HR3xdtEIIWXtNl9cHRHN-svBxVR-xN07wFPlDdpcdPaswp2M8sJVCL2Tg3T56IEkbtHlRZ3mbkfV-Oje9-V4rB_WBBPto6cL3vAs3xWbcxooYKNFDTKKiOjNmm1sWqgVLpPkftJ1EPepNaz0qUXiX6tZiNVbCtgMx4hvlFgRiLIcknwJLXveKrO19KNPHrsCrr6rHa9fVxLtrZZUnXFeFZDrkt1MjUjrJt4tRTB3ZS_HuLMW2__N_eJN8_1n1Y-bIn7c4k9aaO3ggYCWU9g_kIy9DS6aKn4Ts3yvaFL9BTSaNt85Wg2WoLVbSnME6txZ0SX2KzZ2KwLp3cMjG7COaDwVb2-_gFD-oaF";

        // $length = 35;
        // // 2. Hash it (SHA256) and convert to base62
        // $hash = base_convert(substr(hash('sha256', $encrypted), 0, 30), 16, 36);
        // $shorterHash = substr($hash, 0, $length);

        // dd($shorterHash);

        // 3. Trim to required length
        // return ["encrypted" => $encrypted, "shorter" => substr($hash, 0, $length)];
    }


    public function generateGlidePgWidgetUrl(Request $request)
    {
        // Validate incoming request
        $rules = [
            'orderid' => 'required|alpha_num|min:8|max:20|unique:reports,mytxnid',
            'buyer_email' => 'required|email',
            'buyer_phone' => 'required|digits_between:10,15',
            'amount' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'statuscode' => 422,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $apiToken = $request->token; //auth()->id(); If we are fetching auth id, then this should be UserId or MerchantId not apiToken

        if (!$apiToken) {
            return response()->json([
                'statuscode' => 401,
                'message' => "User not authenticated",
            ], 401);
        }

        //AuthToken
        $token = AuthToken::where('token', $apiToken)->first();
        if (!$token) {
            return response()->json([
                'status' => 'failed',
                'statuscode' => 403,
                'message' => 'Invalid Token found.',
            ], 403);
        }
        $user = $token->user;
        $merchatId = $user->id;

        if (!$user || (int) $user->payin_status !== 1) {
            return response()->json([
                'status' => 'failed',
                'statuscode' => 403,
                'message' => 'Your PayIN account is deactivated. Please contact admin.',
            ], 403);
        }
        $report = $this->generateGlidePayOrder($request, $merchatId);
        //dd($report);

        if (!($report instanceof Report)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Unable to create report entry',
            ], 500);
        }

        //Convert Paying amount from INR to USD
        $convertedAmount = $this->numberFormat($this->convertAmount('INR', 'USD', $request->amount));

        // Prepare payload
        // $payload = [
        //     'orderid'     => $request->orderid,
        //     'amount'      => $convertedAmount,//$request->amount,
        //     'buyer_email' => $request->buyer_email,
        //     'buyer_phone' => $request->buyer_phone,
        //     'merchant_info' => json_encode($user),
        // ];

        $createWidgetPayload = [
            'orderid' => $request->orderid,
            'amount' => $convertedAmount,//$request->amount,
            'user_id' => $user->id,
            'token' => $apiToken
        ];

        // Generate widget session ID 
        $sessionData = $this->createGlideWidgetSession($createWidgetPayload);

        // Extract only required fields
        if (!$sessionData || $sessionData === null) {
            return response()->json([
                'status' => 'failed',
                'statuscode' => 500,
                'message' => 'Cannot generate Payment Session'
            ], 500);
        } else {
            //$statusCode = 200;
            //$status    = "success";
            if (!isset($sessionData['id'])) {
                return response()->json([
                    'status' => 'failed',
                    'statuscode' => 500,
                    'message' => 'API did not return Session Id',
                ], 500);
            }
            $sessionId = $sessionData['id'];
            $encryptedSessionId = Crypt::encryptString($sessionId);
            $shortMeta = $this->service->getShortMeta($sessionData['metadata']);

            $filteredData = [
                'session_id' => $sessionId,
                'qrcode_string' => env('SPAY_GLIDE_APP_PAYIN_URL') . "/?session_id=$encryptedSessionId" ?? null,
                'orderid' => $request->orderid ?? null,
                'txnid' => $report->txnid,
                'metadata' => $sessionData['metadata'],
                'short-metadata' => $shortMeta
            ];

            $report->update([
                'glide_uiwidget_sessionid' => $sessionData['id'] ?? null,
                'apitxnid' => $shortMeta ?? null, //$sessionData['metadata'] ?? null,
            ]);

            /// Return clean JSON response
            return response()->json([
                'status_code' => 200,
                'status' => "success",
                'data' => $filteredData
            ], 200);
        }
    }

    private function generateGlidePayOrder($requestParams, $user_id)
    {

        $transactionAmount = $requestParams->amount;
        $user = User::find($user_id);

        $schemeInfo = Scheme::where('id', $user->scheme_id)
            ->where('status', true)
            ->first();
        //dd("request", $requestParams, "user", $user, "Scheme Info", $schemeInfo);

        if (!$schemeInfo) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Scheme not defined for this user',
            ], 400);
        }

        // Commission Calculation
        $payinCommissionType = $schemeInfo->payin_commision_type;
        $payinCommissionAmount = $schemeInfo->payin_commision_amount;

        $calculatedCommission = 0;
        if ($payinCommissionType === 'percent') {
            $calculatedCommission = ($transactionAmount * $payinCommissionAmount) / 100;
        } elseif ($payinCommissionType === 'flat') {
            $calculatedCommission = $payinCommissionAmount;
        }
        //echo number_format($calculatedCommission, 10, '.', '');
        $calculatedCommission = $this->numberFormat($calculatedCommission);
        // GST on commission
        $gst = ($calculatedCommission * env('GST_FOR_GLIDE')) / 100;

        // Rolling Charge
        $rollingPayinAmount = $schemeInfo->rolling_payin_amount;
        $rollingFixedAmount = $schemeInfo->rolling_fixed_amount;

        $rollingCharge = 0;
        $rolling_amount = 0;

        if (!empty($rollingPayinAmount)) {
            $rollingCharge = ($transactionAmount * $rollingPayinAmount) / 100;
            $rolling_amount = $rollingCharge;
        } elseif (!empty($rollingFixedAmount)) {
            $rollingCharge = 0;
            $rolling_amount = $rollingFixedAmount;
        }

        $totalCommissionWithGst = $calculatedCommission + $gst;
        $totalCommissionWithGst = $this->numberFormat($totalCommissionWithGst);
        $remainingAmount = $this->numberFormat($transactionAmount - ($totalCommissionWithGst + $rollingCharge));

        $orderId = 'SPAY-GLIDE-' . now()->format('YmdHis') . rand(11111111, 99999999);

        $data = [
            "gst" => $gst,
            "charge" => $calculatedCommission,
            "mobile" => $requestParams->buyer_phone,
            "txnid" => $orderId,
            "payid" => $orderId,
            "mytxnid" => $requestParams->orderid,
            "amount" => $transactionAmount,
            "user_id" => $user_id,
            "profit" => $totalCommissionWithGst,
            "payin_amount" => $remainingAmount,
            "payin_rolling_amount" => $rolling_amount,
            "transaction_type" => "credit",
            "status" => "initiated",
            "remark" => "glide",
            "product" => "CRYPTO",
            "payment_platform" => "portal",
            "description" => "Payment initiated",
            "payer_email" => $requestParams->buyer_email,
            "payer_name" => $requestParams->buyer_name,
            "option1" => 'payin calculation is pending',
        ];

        return Report::create($data);
    }

    public function numberFormat($amount)
    {
        return number_format($amount, 20, '.', '');
    }

    public function convertAmount(string $from, string $to, ?float $amount = null)
    {
        $url = "https://free.ratesdb.com/v1/rates";

        try {
            $response = Http::get($url, [
                'from' => strtoupper($from),
                'to' => strtoupper($to),
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            // Check if rate exists in 'rates' array
            $rate = $data['data']['rates'][strtoupper($to)] ?? null;

            if ($rate === null) {
                return null; // rate not available
            }

            // Convert amount if provided
            return $amount !== null ? $amount * $rate : $rate;

        } catch (\Exception $e) {
            \Log::error("Currency conversion error: " . $e->getMessage());
            return null;
        }
    }

    public function reviewGlideWidgetPayments(Request $request)
    {
        // // Validate incoming request
        $rules = [
            'session_id' => 'required|string',
        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'statuscode' => 422,
                'message' => $validator->errors()->first(),
            ], 422);
        }
        try {
            //$sessionId = $request->input('session_id');
            $sessionId = Crypt::decryptString($request->session_id);
            if (!$sessionId) {
                return response()->json([
                    'statuscode' => 500,
                    'message' => "Invalid Payment Session",
                ], 500);
            }
            //Fetch merchant and amount from DB
            // Fetch order + merchant
            $payOrderDetails = Report::where('glide_uiwidget_sessionid', $sessionId)
                ->first();
            if (!$payOrderDetails) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid Order Details',
                ], 401);
            } else {
                $convertedAmount = $this->numberFormat($this->convertAmount('INR', 'USD', $payOrderDetails->getRawOriginal('amount')));
                $user_id = $payOrderDetails->user_id;
                $user = User::find($user_id);

                return response()->json([
                    'status' => 'success',
                    'status_code' => 200,
                    'message' => 'Payment order fetched successfully',
                    'data' => [
                        'session_id' => $sessionId,
                        'amount' => $payOrderDetails->getRawOriginal('amount'),//$payOrderDetails->payin_amount,
                        'amountUSD' => $convertedAmount,
                        'merchant' => [
                            'name' => isset($user->name) ? $user->name : null,
                            //'logo' => $order->merchant->logo,
                        ],
                    ]
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Request failed: ' . $e->getMessage()], 500);
        }
    }

    public function successGlideWidgetPayments(Request $request)
    {
        // // Validate incoming request
        $rules = [
            'session_id' => 'required|string',
            'tx_hash' => 'required|string'
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'statuscode' => 422,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $sessionId = $request->session_id;
            $txHashId = $request->tx_hash;

            if (!$sessionId) {
                Log::warning('sessionId missing in Glide response', ['response' => $request->getContent()]);
                return response()->json(['status' => false, 'message' => 'Session ID missing']);
            }

            if (!$txHashId) {
                Log::warning('transactionId missing in Glide response', ['response' => $request->getContent()]);
                return response()->json(['status' => false, 'message' => 'Transaction ID missing']);
            }

            $report = Report::where('glide_uiwidget_sessionid', $sessionId)
                ->where('status', 'initiated')
                ->where('product', 'CRYPTO')
                ->first();

            if (!$report) {
                Log::warning('No report found for SessionId', ['SessionId' => $sessionId]);
                return response()->json(['status' => false, 'message' => 'Report not found']);
            }

            $user = User::find($report->user_id);

            // Prepare report update
            $updateOrder = [
                'option2' => null,
                'option3' => $txHashId ?? null,
            ];

            if (!$txHashId) {
                $updateOrder['option2'] = "No Transaction Has Received" ?? null;
            }

            $updateOrder['status'] = ($txHashId) ? 'pending' : 'failed';

            $report->update($updateOrder);

            Log::info("Report updated", [
                'report_id' => $report->id,
                'update' => $updateOrder
            ]);

            Log::info('Glide Payment Completed');

            return response()->json(['status' => true, 'message' => 'Glide Payment Successful']);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Request failed: ' . $e->getMessage()], 500);
        }
    }


    public function errorGlideWidgetPayments(Request $request)
    {
        // // Validate incoming request
        // $rules = [
        //     'session_id' => 'required|string',
        //     'error' => 'required|string'
        // ];

        // $validator = Validator::make($request->all(), $rules);

        // if ($validator->fails()) {
        //     return response()->json([
        //         'statuscode' => 422,
        //         'message'    => $validator->errors()->first(),
        //     ], 422);
        // }

        try {
            $sessionId = $request->session_id;
            $error = $request->error;

            if (!$sessionId) {
                Log::warning('sessionId missing in Glide response', ['response' => $request->getContent()]);
                return response()->json(['status' => false, 'message' => 'Session ID missing']);
            }

            $report = Report::where('glide_uiwidget_sessionid', $sessionId)
                ->where('status', 'initiated')
                ->where('product', 'CRYPTO')
                ->first();

            if (!$report) {
                Log::warning('No report found for SessionId', ['SessionId' => $sessionId]);
                return response()->json(['status' => false, 'message' => 'Report not found']);
            }

            $user = User::find($report->user_id);

            // Prepare report update
            $updateOrder = [
                'option2' => "Error Occured",
                'option3' => null,
            ];

            $updateOrder['status'] = 'failed';

            $report->update($updateOrder);

            Log::info("Report updated", [
                'report_id' => $report->id,
                'update' => $updateOrder
            ]);

            Log::info('Glide Payment Error occured');

            return response()->json(['status' => true, 'message' => 'Glide Payment Error']);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Request failed: ' . $e->getMessage()], 500);
        }
    }

    public function cancelGlideWidgetPayments(Request $request)
    {
        // // Validate incoming request
        // $rules = [
        //     'session_id' => 'required|string',
        //     'error' => 'required|string'
        // ];

        // $validator = Validator::make($request->all(), $rules);

        // if ($validator->fails()) {
        //     return response()->json([
        //         'statuscode' => 422,
        //         'message'    => $validator->errors()->first(),
        //     ], 422);
        // }

        try {
            $sessionId = $request->session_id;

            if (!$sessionId) {
                Log::warning('sessionId missing in Glide response', ['response' => $request->getContent()]);
                return response()->json(['status' => false, 'message' => 'Session ID missing']);
            }

            $report = Report::where('glide_uiwidget_sessionid', $sessionId)
                ->where('status', 'initiated')
                ->where('product', 'CRYPTO')
                ->first();

            if (!$report) {
                Log::warning('No report found for SessionId', ['SessionId' => $sessionId]);
                return response()->json(['status' => false, 'message' => 'Report not found']);
            }

            $user = User::find($report->user_id);

            // Prepare report update
            $updateOrder = [
                'option2' => "User Cancelled Payment",
                'option3' => null,
            ];

            $updateOrder['status'] = 'failed';

            $report->update($updateOrder);

            Log::info("Report updated", [
                'report_id' => $report->id,
                'update' => $updateOrder
            ]);

            Log::info('Glide Payment Error occured');

            return response()->json(['status' => true, 'message' => 'Glide Payment Error']);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Request failed: ' . $e->getMessage()], 500);
        }
    }
}