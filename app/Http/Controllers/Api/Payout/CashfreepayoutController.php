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

class CashfreepayoutController extends Controller
{
    public function payment_request(Request $request)
    {
        try {
            
            // 1. Validate Input
            $rules = [
                'token'               => 'required',
                'orderid'             => 'required|alpha_num|min:8|max:20|unique:reports,mytxnid',
                'beneficiary_email'   => 'required|email',
                'beneficiary_phone'   => 'required|digits_between:10,15',
                'amount'              => 'required|numeric|min:1',
                'beneficiary_account_number'   => 'required|string',
                'beneficiary_ifsc'    => 'required|string',
                'beneficiary_name'    => 'required|string',
            ];
            
                $validator = \Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 422,
                    'message'    => $validator->errors()->first(),
                ], 422);
            }
    
            // 2. Verify API Token & IP
            $apiToken = Apitoken::where('ip', $request->ip())
                ->where('token', $request->token)
                ->first(['user_id']);
                
            if (!$apiToken) {
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 401,
                    'message'    => "IP or Token mismatch. Your IP is " . $request->ip(),
                ], 401);
            }
    
            // 3. Fetch user with row lock to prevent concurrent access
            $user = User::where('id', $apiToken->user_id)
                ->lockForUpdate() // This is crucial for concurrency control
                ->first();
                
            if (!$user) {
                DB::rollBack();
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 404,
                    'message'    => 'User not found.',
                ], 404);
            }
    
            if ($user->pay_out_status !== 'active') {
                DB::rollBack();
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 403,
                    'message'    => 'Your PayOUT account is deactivated. Please contact the administrator.',
                ], 403);
            }
    
            $provider = Provider::where('recharge1', 'upi1')->first();
            if (!$provider) {
                DB::rollBack();
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 503,
                    'message'    => 'No active provider available. Try again later.',
                ], 503);
            }

      
            // 5. Commission Calculation
            $amt = $request->amount;
            
            if ($amt <= 700) {
                // if (in_array($user->id, [168, 11])) {
                //     $commission = 15;
                // } else {
                    $commission = 15;
                // }
            } elseif (in_array($user->id, [114, 156])&& ($amt > 700 && $amt <= 1000000)) {
                $commission = round(($amt * 2.5) / 100, 2);
            } elseif (in_array($user->id, [153, 154, 157,158,159,160,161,164,166,167]) && ($amt > 700 && $amt <= 1000000)) {
                $commission = round(($amt * 2) / 100, 2);
            }elseif (in_array($user->id, [163])&& ($amt > 700 && $amt <= 1000000)) {
                $commission = round(($amt * 1.68) / 100, 2);
            } elseif ($amt <= 1000000) {
                $commission = round(($amt * 3) / 100, 2);
            } else {
                DB::rollBack();
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 400,
                    'message'    => 'Payout Max limit is ₹10 lakh',
                ], 400);
            }

            $gst = $this->getGst($commission, $user->gstrate);
            $commission_inc_gst = $commission + $gst;
            $mainamt = $amt + $commission_inc_gst;
            $openingbalance = $user->mainwallet;

            // 7. Deduct balance atomically
            $user->decrement('mainwallet', $mainamt);
            $closingBalance = $user->mainwallet; // Get updated balance
            
            // 8. Create transaction record immediately
            $orderId = 'SPAY' . now()->format('YmdHis') . rand(11111111, 99999999);
            $reportData = [
                "gst"           => $gst,
                "profit"        => $commission,
                "charge"        => $commission_inc_gst,
                "payerMobile"   => $request->beneficiary_phone,
                "payeeVPA"      => $request->beneficiary_name,
                "txnid"         => $orderId,
                "mytxnid"       => $request->orderid,
                "apitxnid"      => $request->orderid,
                "amount"        => $request->amount,
                "api_id"        => $provider->api->id,
                "user_id"       => $apiToken->user_id,
                "openingbalance"=> $openingbalance,
                "balance"       => $closingBalance,
                "aepstype"      => "MS",
                "trans_type"    => "debit",
                "status"        => "pending",
                "credited_by"   => $apiToken->user_id,
                "provider_id"   => $provider->id,
                "product"       => "payout",
                "payer_vpa"     => $request->beneficiary_account_number,
                "payerIFSC"     => $request->beneficiary_ifsc,
                "remark"        => "Debit {$mainamt} to Payout Wallet",
                "transtype"     => "fund",
                "option2"       => $request->beneficiary_email,
                "created_at"    => now(),
                "updated_at"    => now(),
            ];
            // dd($reportData);
            $report = \App\Model\Report::create($reportData);
    
    
            // 10. Prepare and send to Busybox API
            $payload = [
            'bank_account_number' => $request->beneficiary_account_number,
            'bank_ifsc'           => $request->beneficiary_ifsc,
            'amount'              => $request->amount,
            'beneficiary_email'   => $request->beneficiary_email,
            'beneficiary_phone'   => $request->beneficiary_phone,
            'beneficiary_name'    => $request->beneficiary_name,
            'orderid'             => $request->orderid,
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
            $curlError = curl_error($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            // 11. Process API response
            if ($response === false) {
                $report->update(['status' => 'failed', 'remark' => 'cURL Error: ' . $curlError]);
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 500,
                    'message'    => 'cURL Error: ' . $curlError,
                ], 500);
            }

        $jsonDecode = json_decode($response, true);
        
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
        
        // API response to client
        $filteredData = [
            'message'          => "Transfer Initiated",
            'bene_name'        => $request->beneficiary_name,
            'customer_account' => $masked_acc,
            'amount'           => $amount,
            'client_ref_no'    => $cf_transfer_id,
            'order id'         => $request->orderid,
            'txn_date'         => $timestamp,
            'rrn'              => $refno,
            
            
        ];
        
        return response()->json([
            'status'     => $status,
            'statuscode' => 200,
            'message'    => "Payout status: " . ucfirst($status),
            'data'       => $filteredData
        ], 200);

       // old code
            // $jsonDecode = json_decode($response, true);
            // dd($jsonDecode);
            // $payout       = $jsonDecode['Response']['payout'] ?? [];
            // $bene_detail  = $jsonDecode['Response']['beneficiary_get'] ?? [];
            // $bene_acc_det = $bene_detail['beneficiary_instrument_details'] ?? [];
            
            // $status = strtolower($payout['status'] ?? 'pending');
            // //$status = strtolower($jsonDecode['Response']['payout']['status'] ?? 'pending');
            // if ($status === 'received') {
            //     $status = "pending";
            // }
          
            // $cf_transfer_id = $payout['transfer_id'] ?? null;
            // $amount         = $payout['transfer_amount'] ?? null;
            // $timestamp      = $payout['added_on'] ?? null;
            // $refno          = $payout['rrn'] ?? null;
            // $bene_id        = $bene_detail['beneficiary_id'] ?? null;
            // $bene_name      = $bene_detail['beneficiary_name'] ?? null;
            // $bene_acc       = $bene_acc_det['bank_account_number'] ?? null;

            // // Mask account number
            // $masked_acc = $bene_acc
            //     ? str_repeat('X', strlen($bene_acc) - 4) . substr($bene_acc, -4)
            //     : null;

            // // 12. Update transaction status based on API response
            // $updateData = [
            //     'status'  => $status,
            //     'payid'   => $refno,
            //     'refno'   => $refno,
            //     'option1' => $bene_id,
            // ];

            // if ($status === 'success') {
            //     $updateData['remark'] = 'Payment successful';
            // } elseif ($status === 'pending') {
            //     $updateData['remark'] = 'Payment pending';
            // } else {
            //     $updateData['remark'] = $jsonDecode['message'] ?? 'busybox given null value';
            // }
            
            // $report->update($updateData);

            // // 13. Prepare response
            // $filteredData = [
            //     'message'          => "Transfer Initiated",
            //     'bene_name'        => $bene_name,
            //     'customer_account' => $masked_acc,
            //     'amount'           => $amount,
            //     'client_ref_no'    => $cf_transfer_id,
            //     'txn_date'         => $timestamp,
            //     'rrn'              => $refno,
            // ];
    
            // return response()->json([
            //     'status'     => $status,
            //     'statuscode' => $httpCode == 200 ? 200 : 400,
            //     'message'    => "Payout status: " . ucfirst($status),
            //     'data'       => $filteredData
            // ], $httpCode == 200 ? 200 : 400);
    
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Payment Request Error: ' . $e->getMessage());
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 500,
                'message'    => 'Internal server error',
            ], 500);
        }
    }
    
    
    public static function getCommission($amount, $scheme, $slab, $role)
    {
        $commission = 0;
    
        try {
            $schememanager = \DB::table('portal_settings')->where('code', 'schememanager')->first(['value']);
            if ($schememanager->value != "all") {
                $myscheme = \App\Model\Scheme::find($scheme);
                if ($myscheme && $myscheme->status == "1") {
                    $comdata = \App\Model\Commission::where('scheme_id', $scheme)->where('slab', $slab)->first();
                    if ($comdata) {
                        $commission = $comdata->type == "percent"
                            ? ($amount * ($comdata[$role] ?? 0) / 100)
                            : ($comdata[$role] ?? 0);
                    }
                }
            } else {
                $myscheme = \App\Model\Package::find($scheme);
                if ($myscheme && $myscheme->status == "1") {
                    $comdata = \App\Model\Packagecommission::where('scheme_id', $scheme)->where('slab', $slab)->first();
                    if ($comdata) {
                        $commission = $comdata->type == "percent"
                            ? ($amount * ($comdata->value ?? 0) / 100)
                            : ($comdata->value ?? 0);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("Commission Calculation Error", ['message' => $e->getMessage()]);
        }
    
        \Log::debug("Final Commission Returned", ['commission' => $commission]);
    
        return $commission;
    }

    public function getGst($amount,$gstrate){
        return ($gstrate/100)*$amount;
    }
    
    public function status(Request $request)
    {
        // dd("api called");
        // 1. Validate required fields
        $rules = [
            'token'     => 'required|string',
            'orderid'  => 'required|string'
        ];
    
        $validator = \Validator::make($request->all(), $rules);
    
        if ($validator->fails()) {
            return response()->json([
                'statuscode' => 422,
                'message'    => $validator->errors()->first(),
            ], 422);
        }
    
        try {
            
    
            // 3. Prepare BusyBox API call
            $txnId = $request->orderid;
         
    
            $curl = curl_init();
    
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://soulfuloverseas.com/Cashfree/CFStatus/?transfer_id={$txnId}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [],
            ]);
    
            $response = curl_exec($curl);
    //   dump($response);
            if ($response === false) {
                throw new \Exception('cURL Error: ' . curl_error($curl));
            }
    
            curl_close($curl);
    
            $jsonDecode = json_decode($response, true);
        //   dd($jsonDecode);
             $apiStatus = strtoupper($jsonDecode['status'] ?? $jsonDecode['data']['status'] ?? '');
               
            $bene_acc = $jsonDecode['beneficiary_details']['beneficiary_instrument_details']['bank_account_number']?? null;
                // dd($bene_acc);
            $masked_acc = $bene_acc
            ? str_repeat('X', strlen($bene_acc) - 4) . substr($bene_acc, -4)
            : null;

            $bene_ifsc = $jsonDecode['beneficiary_details']['beneficiary_instrument_details']['bank_ifsc']?? null;
                // dd($bene_acc);
            $masked_ifsc = $bene_ifsc
            ? str_repeat('X', strlen($bene_ifsc) - 3) . substr($bene_ifsc, -3)
            : null;
            
        //   dd($apiStatus);
                if ($apiStatus === 'SUCCESS') {
                    return response()->json([
                        // 'status'     => strtolower($apiStatus),
                        'statuscode' => 200,
            
                        'data'       => [
                            'message'        => "Transaction Status Fetched",
                            'status'         => $jsonDecode['status'] ?? null,
                            'amount'         => $jsonDecode['transfer_amount'] ?? null,
                            'rrn'            => $jsonDecode['transfer_utr'] ?? null,
                            'account_number' => $masked_acc ?? null,
                            'ifsc_code'      =>  $masked_ifsc ?? null,
                        ]
                    ]);
                }
                return response()->json([
                    'statuscode' => 400,
                    'data'       => [
                        'message'        => "Transaction Status failed",
                        'status'         => $jsonDecode['status'] ?? 'FAILED',
                         'amount'         => $jsonDecode['transfer_amount'] ?? null,
                          'rrn'            => $jsonDecode['transfer_utr'] ?? null,
                          'account_number' => $masked_acc ?? null,
                          'ifsc_code'      =>  $masked_ifsc ?? null,                       
                    ]
                ], 400);
            // 4. Return response
            // return response()->json([
            //     'statuscode'   => isset($jsonDecode['status']) && $jsonDecode['status'] === 'success' ? 'SUCCESS' : 'ERR',
            //     'message'      => $jsonDecode['message'] ?? 'Status retrieval completed.',
            //     'raw_response' => $jsonDecode,
            // ]);
    
        } catch (\Exception $e) {
            \Log::error('Status Check Error: ' . $e->getMessage());
            return response()->json([
                'statuscode' => 500,
                'message'    => 'An unexpected error occurred while fetching payment status.',
            ], 500);
        }
    }  
    
    
    
    
}
    
