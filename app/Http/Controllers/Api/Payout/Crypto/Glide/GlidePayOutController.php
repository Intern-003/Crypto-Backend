<?php

namespace App\Http\Controllers\Api\Payout\Crypto\Glide;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\GlideSession;
use Illuminate\Support\Facades\Crypt;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GlidePayOutService;
use App\Models\Scheme;
use App\Models\AuthToken;
use App\Models\Report;
use Validator;
use Illuminate\Support\Facades\Log;

class GlidePayOutController extends Controller
{
    protected $service;

    public function __construct(GlidePayOutService $service)
    {
        $this->service = $service;
    }

    public function generateGlidePgWidgetUrl(Request $request)
    {
        DB::beginTransaction();

        // Validate incoming request
        $rules = [
            'token'        => 'required',
            'orderid'      => 'required|alpha_num|min:8|max:20|unique:reports,mytxnid',
            'buyer_email'  => 'required|email',
            'buyer_phone'  => 'required|digits_between:10,15',
            'amount'       => 'required|numeric|min:0',
            'buyer_wallet' => 'required|alpha_num',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'statuscode' => 422,
                'message'    => $validator->errors()->first(),
            ], 422);
        }

        //$apiToken = $request->token;

        //AuthToken
        $apiToken = AuthToken::where('ip', $request->ip())
                          ->where('token', $request->token)
                          ->first(['user_id']);
        if (!$apiToken) {
            return response()->json([
                'statuscode' => 401,
                'message'    => "IP or Token mismatch. Your IP is " . $request->ip(),
            ], 401);
        }
        
        // -----------
        // User Status
        // -----------
        $user = User::find($apiToken->user_id);
        if (!$user) {
            DB::rollBack();
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 404,
                'message'    => 'User not found.',
            ], 404);
        }
        
        if (trim((string) $user->payout_status) != "1") {
            DB::rollBack();
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 403,
                'message'    => 'Your PayOut account is deactivated. Please contact admin.',
            ], 403);
        }

        $setCommercialResp = $this->setCommercial($request, $apiToken);
        if ($setCommercialResp instanceof \Illuminate\Http\JsonResponse) {
            return $setCommercialResp;
        }

        if (!is_object($setCommercialResp) || !isset($setCommercialResp->report)) {
            DB::rollBack();
            return response()->json([
                'status' => 'failed',
                'message' => 'Internal error creating payout record.'
            ], 500);
        }
        // --------------
        // Report $report
        // --------------
        $report = $setCommercialResp->report;
        
        // ---------------------
        // get token for BusyBox
        // ---------------------
        //Convert Paying amount from INR to USD
        $convertedAmount = $this->numberFormat($this->convertAmount('INR', 'USD', $request->amount));
        // Validate incoming request
       
        $createWidgetPayload = [
            'orderid'   => $request->orderid,
            'amount'    => $convertedAmount,//$request->amount,
            'user_id'   => $user->id,
            'token'     => $request->token,
            'buyer_wallet' => $request->buyer_wallet
        ];
        
        // Generate widget session ID 
        $sessionData = $this->service->createPaymentSession($createWidgetPayload);
        // Extract only required fields
        if(!$sessionData || $sessionData === null) {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 500,
                'message'    => 'Cannot generate Payment Session'
            ], 500);
        } else {
            
        }

    }

    public function setCommercial($request, $apiToken)
    {
        DB::beginTransaction();

        try {
            //code...
            $payoutAmount = $request->amount;
            $user = User::find($apiToken->user_id);
            if (!$user) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'User not found.'
                ], 404);
            }
            // -------------
            // Scheme Status
            // -------------
            $schemeInfo = Scheme::where('id', $user->scheme_id)
                                ->where('status', true)
                                ->first();

            if (!$schemeInfo) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'The selected scheme is discontinued or inactive.'
                ], 404);
            }

            // ----------------------
            // Commission Calculation
            // ----------------------
            $calculatedCommission = 0;
            if ($payoutAmount <= 700) {
                $payoutCommissionType   = $schemeInfo->payout_commision_type_below;
                $payoutCommissionAmount = $schemeInfo->payout_commision_amount_below;
                $calculatedCommission = $payoutCommissionAmount;
            } elseif ($payoutAmount > 700 && $payoutAmount <= 1000000) {
                $payoutCommissionType   = $schemeInfo->payout_commision_type_above;
                $payoutCommissionAmount = $schemeInfo->payout_commision_amount_above;
                $calculatedCommission = ($payoutAmount * $payoutCommissionAmount) / 100;
            } else {
                return response()->json([
                    'status' => 'failed',
                    'statuscode' => 400,
                    'message' => 'Payout Max limit is ₹10 lakh',
                ], 400);
            }

            // -----------------
            // GST on commission
            // -----------------
            $gst = ($calculatedCommission * 18) / 100;
            // ------------------
            // Final Calculations
            // ------------------
            $totalCommissionWithGst = $calculatedCommission + $gst;
            $mainAmount = $payoutAmount + $totalCommissionWithGst;
            // -----------------------------------
            // Check balance with atomic operation
            // -----------------------------------
            $openingbalance = $user->payout_wallet;
            if ($openingbalance < $mainAmount) {
                DB::rollBack();
                $shortage = $openingbalance - $totalCommissionWithGst;
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 402,
                    'message'    => "Insufficient balance. Available: ₹{$openingbalance}, Required: ₹{$mainAmount}. You can enter up to ₹{$shortage}"
                ], 402);
            }

            // -------------------------
            // Deduct balance atomically
            // -------------------------
            $user->decrement('payout_wallet', $mainAmount);
            $user->refresh();
            $closingBalance = $user->payout_wallet;

            // ----------------------
            // Create Unique Order Id
            // ----------------------
            $orderId = 'SPAY' . now()->format('YmdHis') . rand(11111111, 99999999);
            $totaldeduct = $payoutAmount + $totalCommissionWithGst;

            $data = [
                "gst"               => $gst,
                "charge"            => $calculatedCommission,
                "profit"            => $totalCommissionWithGst,
                "txnid"             => $orderId,
                "mytxnid"           => $request->orderid,
                "apitxnid"          => $request->orderid,
                "amount"            => $payoutAmount,
                "user_id"           => $apiToken->user_id,
                "payout_amount"     => $totaldeduct,
                "payout_opening_balance" => "$openingbalance",
                "payout_closing_balance" => "$closingBalance",
                "transaction_type"  => "Debit",
                "status"            => "pending",
                "product"           => "payout",
                "description"       => "Debit ₹{$mainAmount} to Payout Wallet",
                "remark"            => "Payout pending",
                "payment_platform"  => "api",
                "payer_name"        => $request->buyer_name,
                "payer_email"       => $request->buyer_email,
                "payer_mobile"      => $request->buyer_phone
            ];

            $report = Report::create($data);
            DB::commit();
            
            // return both report
            return (object) ['report' => $report];

         } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Set Commercial Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'failed',
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
