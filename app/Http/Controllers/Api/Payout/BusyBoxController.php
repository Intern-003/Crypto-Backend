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


class BusyBoxController extends Controller
{
    public function generateToken()
    {
        try {
            $postData = [
                'username' => 'sharad@spay.live',
                'password' => 'aVmkM1EG',
            ];
    
            $curl = curl_init();
    
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.busybox.in/token',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                ],
            ]);
    
            $response = curl_exec($curl);
            if ($response === false) {
                throw new \Exception('cURL Error: ' . curl_error($curl));
            }
    
            curl_close($curl);
    
            $data = json_decode($response, true);
    
            if (!isset($data['token'])) {
                throw new \Exception('Token not found in API response. Response: ' . $response);
            }
    
            return $data['token'];
    
        } catch (\Exception $e) {
            \Log::error('Token Generation Error: ' . $e->getMessage());
            return null;
        }
    }
    
    
    
    public function setCommercial($request, $apiToken)
    {
        DB::beginTransaction();
    
        try {
            $payoutAmount = $request->amount;
            //dump($payoutAmount);
            // --------
            // Get User
            // --------
            $user = User::find($apiToken->user_id);
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
    
            // if ($payoutAmount <= 500000 && in_array($mode, ["IMPS", "RTGS", "NEFT", "FT"])) {
            //     $inputMode = $mode;
            // } elseif ($payoutAmount > 500000 && $payoutAmount <= 1000000 && in_array($mode, ["RTGS", "NEFT"])) {
            //     $inputMode = $mode;
            // } else {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'Payout Max limit is UPI ₹1 lakh, IMPS ₹5 lakh, NEFT/RTGS ₹10 lakh',
            //         'amount' => $payoutAmount,
            //         'mode' => $mode
            //     ], 400);
            // }
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
                // "payid"             => $orderId,
                "mytxnid"           => $request->apitxnid,
                "apitxnid"          => $request->apitxnid,
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
                "payer_name"        => $request->bene_name,
                "payer_email"       => $request->email,
                "payer_mobile"      => $request->mobile,
                "payer_acc_no"      => $request->account_number,
                "payer_ifsc"        => $request->ifsc_code,
                "payer_vpa"         => $request->upi_id,
                "payout_mode"       => $inputMode,

            ];


            // dd($data);
            $report = Report::create($data);
            // $report = \App\Models\Report::create($data);
    
            DB::commit();
    
            //return $report;
            // return both report and the chosen inputMode so caller doesn't have to recompute
            return (object) ['report' => $report, 'inputMode' => $inputMode];
    
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
          
    
    
    
    public function payoutRequest(Request $request)
    {
        // dd("test");
        DB::beginTransaction();
        
        try {
            
            $rules = [
                'token'            => 'required',
                'apitxnid'         => 'required|alpha_num|min:8|max:20|unique:reports,mytxnid',
                'email'            => 'required|email',
                'mobile'           => 'required|digits_between:10,15',
                'amount'           => 'required|numeric|min:1',
                'account_number'   => 'required|string',
                'ifsc_code'        => 'required|string',
                'bene_name'        => 'required|string',
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
            //dump($user->id);
        
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
            $existingTransactionid = Report::where('mytxnid', $request->apitxnid)->first();
            if ($existingTransactionid) {
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
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Internal error creating payout record.'
                ], 500);
            }
            
    
            
            // --------------
            // Report $report
            // --------------
            $report = $setCommercialResp->report;
            $inputMode = $setCommercialResp->inputMode ?? strtoupper($request->mode);
            
                
            // ---------------------
            // get token for BusyBox
            // ---------------------
            $token = $this->generateToken();
            if (!$token) {
                $report->update(['status' => 'failed', 'remark' => 'Failed to obtain BusyBox token']);
                return response()->json([
                    'status' => 'failed',
                    'statuscode' => 500,
                    'message' => 'Failed to obtain BusyBox auth token.'
                ], 500);
            }
        
            // ---------------
            // Prepare payload
            // ---------------
            $payload = [
                "account_number" => $request->account_number,
                "ifsc_code"      => $request->ifsc_code,
                "amount"         => $request->amount,
                "mobile_number"  => $request->mobile,
                "client_ref_no"  => $request->apitxnid,
                "bene_name"      => $request->bene_name,
                "mode"           => $inputMode,
            ];
            dd($payload);
        
            // -------------------------
            // call external payment API
            // -------------------------
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.busybox.in/payment/payment',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $token
                ],
            ]);
            
            $response = curl_exec($curl);
            $responsejson = json_decode($response, true);
            // dd($responsejson['status']);
            $curlError = curl_error($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($responsejson['status'] === "FAILURE") {
                $report->update(['status' => 'failed', 'remark' => 'cURL Error: ' . $curlError]);
                DB::commit();
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 500,
                    'message'    => 'Something Went wrong check your fields',
                ], 500);
            }

            $jsonDecode = json_decode($response, true) ?? [];
            $status = strtolower($jsonDecode['status'] ?? 'failed');
    
            // Update transaction status based on API response
            $updateData = [
                'status'  => $status,
                'payid'   => $jsonDecode['rrn'] ?? null,
                'refno'   => $jsonDecode['rrn'] ?? null,
            ];
    
            if ($status === 'success') {
                $updateData['remark'] = 'Payment successful';
            } elseif ($status === 'pending') {
                $updateData['remark'] = 'Payment pending';
            } else {
                $updateData['remark'] = $jsonDecode['message'] ?? 'Payment failed';
            }
    
            $report->update($updateData);
    
            // Prepare response data
            $filteredData = [
                'message'          => $jsonDecode['message'] ?? null,
                'bene_name'        => $jsonDecode['bene_name'] ?? null,
                'customer_account' => $jsonDecode['customer_account'] ?? null,
                'amount'           => $jsonDecode['amount'] ?? null,
                'client_ref_no'    => $jsonDecode['client_ref_no'] ?? null,
                'txn_date'         => $jsonDecode['txn_date'] ?? null,
                'rrn'              => $jsonDecode['rrn'] ?? null,
            ];
            
            DB::commit();
            return response()->json([
                'status'     => $status,
                'statuscode' => $httpCode == 200 ? 200 : 400,
                'message'    => "Payout status: " . ucfirst($status),
                'data'       => $filteredData
            ], $httpCode == 200 ? 200 : 400);
        

        } catch (\Throwable $e) {
            DB::rollBack();
    
            Log::error('PayoutRequest Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 500,
                'message'    => 'Internal server error occurred.',
                'error'      => $e->getMessage(),
            ], 500);
        }
        
    }
    
    
    public function upiRequest(Request $request)
    {
        DB::beginTransaction();
        
        try {
            
            $rules = [
                'token'            => 'required',
                'apitxnid'         => 'required|alpha_num|min:8|max:20|unique:reports,mytxnid',
                'mobile'           => 'required|digits_between:10,15',
                'amount'           => 'required|numeric|min:1',
                'upi_id'           => 'required|string',
                'mode'             => 'required|string|in:UPI,NEFT,IMPS,FT,RTGS',
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
            //dump($user->id);
        
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
            $existingTransactionid = Report::where('mytxnid', $request->apitxnid)->first();
            if ($existingTransactionid) {
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
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Internal error creating payout record.'
                ], 500);
            }
            
    
            
            // --------------
            // Report $report
            // --------------
            $report = $setCommercialResp->report;
            $inputMode = $setCommercialResp->inputMode ?? strtoupper($request->mode);
            
                
            // ---------------------
            // get token for BusyBox
            // ---------------------
            $token = $this->generateToken();
            if (!$token) {
                $report->update(['status' => 'failed', 'remark' => 'Failed to obtain BusyBox token']);
                return response()->json([
                    'status' => 'failed',
                    'statuscode' => 500,
                    'message' => 'Failed to obtain BusyBox auth token.'
                ], 500);
            }
        
            // ---------------
            // Prepare payload
            // ---------------
            $payload = [
                "account_number" => $request->upi_id,
                "amount"         => $request->amount,
                "mobile_number"  => $request->mobile,
                "client_ref_no"  => $request->apitxnid,
                "mode"           => $inputMode,
            ];
            // dd($payload);
       
            // -------------------------
            // call external payment API
            // -------------------------
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.busybox.in/payment/upi',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $token
                ],
            ]);
            
            $response = curl_exec($curl);
            $responsejson = json_decode($response, true);
            // dd($responsejson['status']);
            $curlError = curl_error($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($responsejson['status'] === "FAILURE") {
                $report->update(['status' => 'failed', 'remark' => 'cURL Error: ' . $curlError]);
                DB::commit();
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 500,
                    'message'    => 'Something Went wrong check your fields',
                ], 500);
            }

            $jsonDecode = json_decode($response, true) ?? [];
            $status = strtolower($jsonDecode['status'] ?? 'failed');
    
            // Update transaction status based on API response
            $updateData = [
                'status'  => $status,
                'payid'   => $jsonDecode['rrn'] ?? null,
                'refno'   => $jsonDecode['rrn'] ?? null,
            ];
    
            if ($status === 'success') {
                $updateData['remark'] = 'Payment successful';
            } elseif ($status === 'pending') {
                $updateData['remark'] = 'Payment pending';
            } else {
                $updateData['remark'] = $jsonDecode['message'] ?? 'Payment failed';
            }
    
            $report->update($updateData);
    
            // Prepare response data
            $filteredData = [
                'message'          => $jsonDecode['message'] ?? null,
                'bene_name'        => $jsonDecode['bene_name'] ?? null,
                'customer_account' => $jsonDecode['customer_account'] ?? null,
                'amount'           => $jsonDecode['amount'] ?? null,
                'client_ref_no'    => $jsonDecode['client_ref_no'] ?? null,
                'txn_date'         => $jsonDecode['txn_date'] ?? null,
                'rrn'              => $jsonDecode['rrn'] ?? null,
            ];
            
            DB::commit();
            return response()->json([
                'status'     => $status,
                'statuscode' => $httpCode == 200 ? 200 : 400,
                'message'    => "UPI Payout status: " . ucfirst($status),
                'data'       => $filteredData
            ], $httpCode == 200 ? 200 : 400);
        

        } catch (\Throwable $e) {
            DB::rollBack();
    
            Log::error('PayoutRequest Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 500,
                'message'    => 'Internal server error occurred.',
                'error'      => $e->getMessage(),
            ], 500);
        }
        
    }
    
    
    
    
}