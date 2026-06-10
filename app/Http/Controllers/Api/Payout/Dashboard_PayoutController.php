<?php

namespace App\Http\Controllers\Api\Payout;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Scheme;
use App\Models\AuthToken;
use App\Models\Report;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class Dashboard_PayoutController extends Controller
{
    
    public function setCommercial($request, $apiToken)
    {
        // dump("details");
        // DB::beginTransaction();
    
        try {
            $payoutAmount = $request->amount;
            //dump($payoutAmount);
            // --------
            // Get User
            // --------
            $user= User::find($apiToken);
            // dd($user);
            if (!$user) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'User not found.'
                ], 404);
            }
            //dump($user->id);
            //dump($user->scheme_id);
            
            // -------------
            // Scheme Status
            // -------------
            // $schemeInfo = $user->schemes()->where('status', 1)->first();
            $schemeInfo = Scheme::where('id', $user->scheme_id)
            ->where('status', true)
            ->first();

            if (!$schemeInfo) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'The selected scheme is discontinued or inactive.'
                ], 404);
            }
            // dump($schemeInfo);
            // dump($schemeInfo->payout_commision_type_below);
            // dump($schemeInfo->payout_commision_amount_below);
            // dump($schemeInfo->payout_commision_type_above);
            // dump($schemeInfo->payout_commision_amount_above);
            
            // ----------------------
            // Payout Mode Validation
            // ----------------------
            // $mode = $request->mode;
            $mode = strtoupper($request->mode ?? '');
            $inputMode = null;
    
            if($payoutAmount <= 100000 && in_array($mode, ["UPI","IMPS", "RTGS", "NEFT", "FT"])) {
                $inputMode = $mode;
            } elseif ($payoutAmount <= 500000 && in_array($mode, ["IMPS", "RTGS", "NEFT", "FT"])) {
                $inputMode = $mode;
            } elseif ($payoutAmount > 500000 && $payoutAmount <= 1000000 && in_array($mode, ["RTGS", "NEFT"])) {
                $inputMode = $mode;
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Payout Max limit is UPI ₹1 lakh, IMPS ₹5 lakh, NEFT/RTGS ₹10 lakh',
                    'amount' => $payoutAmount,
                    'mode' => $mode
                ], 400);
            }
            // dump($mode);
            // dump($inputMode);
    
            // ----------------------
            // Commission Calculation
            // ----------------------
            // $payoutCommissionType   = $schemeInfo->payout_commision_type;
            // $payoutCommissionAmount = $schemeInfo->payout_commision_amount;
            $calculatedCommission = 0;
    
            if ($payoutAmount <= 700) {
                // $payoutCommissionType = 'flat';
                $payoutCommissionType   = $schemeInfo->payout_commision_type_below;
                $payoutCommissionAmount = $schemeInfo->payout_commision_amount_below;
                $calculatedCommission = $payoutCommissionAmount;
            } elseif ($payoutAmount > 700 && $payoutAmount <= 1000000) {
                // $payoutCommissionType = 'percent';
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
            // dump($payoutAmount);
            // dump($payoutCommissionType);
            // dump($payoutCommissionAmount);
            // dump($calculatedCommission);
            
            // -----------------
            // GST on commission
            // -----------------
            $gst = ($calculatedCommission * 18) / 100;
            // dump($gst);    
    
            // ------------------
            // Final Calculations
            // ------------------
            $totalCommissionWithGst = $calculatedCommission + $gst;
            $mainAmount = $payoutAmount + $totalCommissionWithGst;
            // dump($totalCommissionWithGst);
            // dump($mainAmount);
            
            // -----------------------------------
            // Check balance with atomic operation
            // -----------------------------------
            $openingbalance = $user->payout_wallet;
             if ($openingbalance < $mainAmount) {
                // DB::rollBack();
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
            // $user->refresh();
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
                // "payid"             => $orderId,
                "mytxnid"           => $request->orderid,
                "apitxnid"          => $request->orderid,
                "amount"            => $payoutAmount,
                "user_id"           => $apiToken,
                "payout_amount"     => $totaldeduct,
                "payout_opening_balance" => "$openingbalance",
                "payout_closing_balance" => "$closingBalance",
                "transaction_type"  => "Debit",
                "status"            => "pending",
                "product"           => "payout",
                "description"       => "Debit ₹{$mainAmount} to Payout Wallet",
                "remark"            => "Payout pending",
                "payment_platform"  => "portal",
                "payer_name"        => $request->name,
                "payer_email"       => $request->email,
                "payer_mobile"      => $request->mobile,
                "payer_acc_no"      => $request->account,
                "payer_ifsc"        => $request->ifsc,
                "payer_vpa"         => $request->upi_id,
                "payout_mode"       => $inputMode,

            ];



            $report = Report::create($data);
            // dump("set commercial data",$report);
            // $report = \App\Models\Report::create($data);
    
            DB::commit();
    
            //return $report;
            // return both report and the chosen inputMode so caller doesn't have to recompute
            return (object) ['report' => $report, 'inputMode' => $inputMode];
    
        } catch (\Exception $e) {
            // DB::rollBack();
            Log::error('Set Commercial Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'failed',
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
          
    public function Dashboard_payoutRequest(Request $request)
    {
        // dd('hello');
             
        DB::beginTransaction();
        
        try {
            
            $rules = [
                'orderid'         => 'required|alpha_num|min:8|max:20|unique:reports,mytxnid',
                'email'            => 'required|email',
                'mobile'           => 'required|digits_between:10,15',
                'amount'           => 'required|numeric|min:1',
                'account'          => 'required|string',
                'ifsc'             => 'required|string',
                'name'             => 'required|string',
                'mode'             => 'required|string|in:NEFT,IMPS,FT,RTGS',
            ];
        
        
            // ----------------
            // Check Validation
            // ----------------
            $validator = \Validator::make($request->all(), $rules);
    
            if ($validator->fails()) {
                return response()->json([
                    'statuscode' => 422,
                    'message'    => $validator->errors()->first(),
                ], 422);
            }
        
            // ---------------
            // Check API token
            // ---------------
            $apiToken = auth()->id();
            if (!$apiToken) {
                return response()->json([
                    'statuscode' => 401,
                    'message'    => "IP or Token mismatch. Your IP is " . $request->ip(),
                ], 401);
            }
        
            // -----------
            // User Status
            // -----------
            $user = User::find($apiToken);
            if (!$user) {
                DB::rollBack();
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 404,
                    'message'    => 'User not found.',
                ], 404);
            }
            // dd($user->id);
                // dd($apiToken);
            //  $provider = User::pluck('payout_at_onboard')->first();
            $providerName = $user->payout_at_onboard;
            //  dump($providerName);
            // ------------------------
            // Check User Payout Status
            // ------------------------
            if (trim((string) $user->payout_status) != "1") {
                DB::rollBack();
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 403,
                    'message'    => 'Your Payout account is deactivated. Please contact the administrator.',
                ], 403);
            }
            //dump($user-> payout_status);

            // ----------------------------------
            // Check for duplicate transaction ID
            // ----------------------------------
            $existingTransactionid = Report::where('mytxnid', $request->orderid)->first();
            if ($existingTransactionid) {
                dump("$existingTransactionid");
                DB::rollBack();
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 409,
                    'message'    => 'Duplicate transaction ID.',
                ], 409);
            }
            //dump($request->apitxnid);
            //dump($existingTransactionid);
        
            // ------------------------------------------------
            // Create commercial (debit wallet & create report)
            // ------------------------------------------------
            $setCommercialResp = $this->setCommercial($request, $apiToken);
    
            // If setCommercial returned a JsonResponse (error) forward it
            if ($setCommercialResp instanceof \Illuminate\Http\JsonResponse) {
                return $setCommercialResp;
            }
    
            if (!is_object($setCommercialResp) || !isset($setCommercialResp->report)) {
                DB::rollBack();
                // dump("set rollback");
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Internal error creating payout record.'
                ], 500);
            }
            
    
            
            // --------------
            // Report $report
            // --------------
            $report = $setCommercialResp->report;
            // dump("dashboard coomitt",$report);
            $inputMode = $setCommercialResp->inputMode ?? strtoupper($request->mode);
            
                
            // ---------------------

         $commonPayload = [
            'orderid'   => $request->orderid,
            'amount'    => $request->amount,
            'account'   => $request->account,
            'ifsc'      => $request->ifsc,
            'email'     => $request->email,
            'phone'     => $request->mobile,
            'name'      => $request->name,
        ];


        // =============================================================
        // SWITCH PROVIDER
        // =============================================================
        switch ($providerName) {

            // ------------------------------------------------------------------
            // CASHFREE
            // ------------------------------------------------------------------
            case 'cashfree':
                //  dump("bulkpe case called");
                $payload = [
                    'bank_account_number' => $commonPayload['account'],
                    'bank_ifsc'           => $commonPayload['ifsc'],
                    'amount'              => $commonPayload['amount'],
                    'beneficiary_email'   => $commonPayload['email'],
                    'beneficiary_phone'   => $commonPayload['phone'],
                    'beneficiary_name'    => $commonPayload['name'],
                    'orderid'             => $commonPayload['orderid'],
                ];
                // dd($payload);
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL            => 'https://soulfuloverseas.com/Cashfree/CFPayout/',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_TIMEOUT        => 60,
                ]);
 
                $response = curl_exec($curl);
                // dump($response);
                $curlError = curl_error($curl);
                curl_close($curl);

                if ($response === false) {
                    $report->update(['status' => 'failed', 'remark' => $curlError]);
                    return response()->json([
                        'status' => 'failed',
                        'statuscode' => 500,
                        'message' => $curlError,
                    ], 500);
                }

                // return response()->json(json_decode($response, true));
                $jsonDecode = json_decode($response, true);
            //   dump($jsonDecode);
        // Map fields
        $cf_transfer_id = $jsonDecode['cf_transfer_id'] ?? null;
        $amount         = $jsonDecode['transfer_amount'] ?? null;
        $timestamp      = $jsonDecode['added_on'] ?? null;
        
        // RRN not available at this stage
        $refno          = $jsonDecode['rrn'] ?? '';
        
        $bene_id        = $jsonDecode['beneficiary_details']['beneficiary_id'] ?? null;
        $bene_acc       = $jsonDecode['beneficiary_details']['beneficiary_instrument_details']['bank_account_number'] ?? null;
        
        $bene_name = $jsonDecode['beneficiary_details']['beneficiary_name'] 
                     ?? ($data['beneficiary_name'] ?? 'Not Available');
        
        $masked_acc = $bene_acc
            ? str_repeat('X', strlen($bene_acc) - 4) . substr($bene_acc, -4)
            : null;
        
        // Convert RECEIVED → pending
        $status = strtolower($jsonDecode['status'] ?? 'pending');
        if ($status === 'received') {
            $status = 'pending';
        }
        
        // DB update data
        $updateData = [
            'status'  => $status,
            'payid'   => $refno,
            'refno'   => $refno,
            'option1' => $bene_id,
            // 'remark'  => 'Transfer received'
        ];
        
        $report->update($updateData);
        DB::commit();
        // dd("after updation",$report);
        // API response to client
        $filteredData = [
            'message'          => "Transfer Initiated",
            'bene_name'        => $request->name,
            'customer_account' => $masked_acc,
            'amount'           => $amount,
            'client_ref_no'    => $cf_transfer_id,
            'order id'         => $request->orderid,
            'txn_date'         => $timestamp,
        ];
        // dd($filteredData);
        return response()->json([
            'status'     => $status,
            'statuscode' => 200,
            'message'    => "Payout status: " . ucfirst($status),
            'data'       => $filteredData
        ], 200);


            // ------------------------------------------------------------------
            // SPAY
            // ------------------------------------------------------------------
            case 'spay':

                   $upi_id = "test444-1@oksbi";
                    $upiLink = "upi://pay?"
                             . "pa=" . urlencode($upi_id)
                             . "&pn=" . urlencode($request->name)
                             . "&tn=" . urlencode($request->orderid)
                             . "&am=" . urlencode($request->amount)
                             . "&cu=INR"
                             . "#Intent;scheme=upi;package=com.google.android.apps.nbu.paisa.user;end;";
                        
                            return response()->json([
                                'status' => 'success',
                                'txnid'  => $report->txnid,
                                'upi_link' => $upiLink,
                            ], 200);
                  break;
            default:
                return response()->json([
                    'status' => false,
                    'message' => "Select Valid Provider"
                ],401);
        }         
 
        }catch(\Exception $e){
                return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
        
    }
    
    
    
    
    
}