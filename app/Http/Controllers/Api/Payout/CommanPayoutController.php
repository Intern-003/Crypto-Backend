<?php

namespace App\Http\Controllers\Api\Payout;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Mahaagent;
use App\Models\Company;
use App\Models\Mahastate;
use App\Models\Report;
use App\Models\Commission;
use App\Models\Aepsreport;
use App\Models\Provider;
use App\Models\Api;
use App\Models\Cosmosmerchant;
use App\Models\AuthToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Microatmreport;
use App\Models\UserPermission;
use App\Models\Apilog;
use App\Models\Scheme;
use App\Models\Utiid;
use App\Models\Packagecommission;
use App\Models\Package;

use App\Services\PayoutProviders\PayoutProviderFactory;
use Illuminate\Support\Facades\Validator;

// use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CommanPayoutController extends Controller
{

public function payout_request(Request $request)
{
    try {
        // ------------------- VALIDATION -------------------
        $rules = [
            'token'                     => 'required',
            'orderid'                   => 'required|alpha_num|min:8|max:20|unique:reports,mytxnid',
            'beneficiary_email'         => 'required|email',
            'beneficiary_phone'         => 'required|digits_between:10,15',
            'beneficiary_account_number'=> 'required|string',
            'beneficiary_ifsc'          => 'required|string',
            'beneficiary_name'          => 'required|string',
            'amount'                    => 'required|numeric|min:1'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 422,
                'message'    => $validator->errors()->first(),
            ], 422);
        }

        // ------------------- TOKEN + IP CHECK -------------------
        $AuthToken = AuthToken::where('ip', $request->ip())
            ->where('token', $request->token)
            ->first(['user_id']);

        if (!$AuthToken) {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 401,
                'message'    => "IP or Token mismatch",
            ], 401);
        }

        // ------------------- USER WITH LOCK -------------------
        $user = User::where('id', $AuthToken->user_id)->lockForUpdate()->first();
        if (!$user) {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 404,
                'message'    => 'User not found',
            ], 404);
        }

        // ------------------- COMMISSION & WALLET -------------------
        $amount = $request->amount;

        if ($amount <= 700) {
            $commission = 15;
        } elseif (in_array($user->id, [114, 156]) && $amount <= 1000000) {
            $commission = round(($amount * 2.5) / 100, 2);
        } elseif (in_array($user->id, [153,154,157,158,159,160,161,164,166,167]) && $amount <= 1000000) {
            $commission = round(($amount * 2) / 100, 2);
        } elseif (in_array($user->id, [163]) && $amount <= 1000000) {
            $commission = round(($amount * 1.68) / 100, 2);
        } elseif ($amount <= 1000000) {
            $commission = round(($amount * 3) / 100, 2);
        } else {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 400,
                'message'    => 'Maximum payout limit ₹10,00,000',
            ], 400);
        }

        $gst = ($user->total_charges / 100) * $commission;
        $charge = $commission + $gst;
        $totalDebit = $amount + $charge;

        if ($user->payout_wallet < $totalDebit) {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 402,
                'message'    => "Insufficient Balance. Available: ₹{$user->payout_wallet}, Required: ₹{$totalDebit}"
            ], 402);
        }

        $openingBalance = $user->payout_wallet;
        $user->decrement('payout_wallet', $totalDebit);
        $closingBalance = $user->payout_wallet;

        // ------------------- CREATE REPORT -------------------
        $txnid = 'TXN' . now()->format('YmdHis') . rand(111111,999999);

        $report = Report::create([
            'user_id'                => $user->id,
            'mobile'                 => $request->beneficiary_phone,
            'amount'                 => $amount,
            'charge'                 => $charge,
            'profit'                 => $commission,
            'gst'                    => $gst,
            'txnid'                  => $txnid,
            'apitxnid'               => $request->orderid,
            'mytxnid'                => $request->orderid,
            'product'                => 'payout',

            'payout_amount'          => $amount,
            'payout_opening_balance' => $openingBalance,
            'payout_closing_balance' => $closingBalance,

            'payer_acc_no'           => $request->beneficiary_account_number,
            'payer_ifsc'             => $request->beneficiary_ifsc,
            'payer_mobile'           => $request->beneficiary_phone,
            'payer_name'             => $request->beneficiary_name,
            'payer_email'            => $request->beneficiary_email,

            'commission_inc_gst'     => $charge,
            'transaction_type'       => 'debit',
            'payment_platform'       => 'api',
            'status'                 => 'pending',
            'bank_other_charges'     => 0,
        ]);

        // ------------------- PROVIDER FACTORY -------------------
        $providerName = $user->payout_at_onboard;
        $provider = PayoutProviderFactory::make($providerName);

        if (!$provider) {
            return response()->json([
                'status'  => 'failed',
                'message' => "Invalid Provider"
            ], 400);
        }

        // ------------------- PAYLOAD -------------------
        $payload = [
            'orderid'             => $request->orderid,
            'amount'              => $amount,
            'bank_account_number' => $request->beneficiary_account_number,
            'bank_ifsc'           => $request->beneficiary_ifsc,
            'beneficiary_name'    => $request->beneficiary_name,
            'beneficiary_email'   => $request->beneficiary_email,
            'beneficiary_phone'   => $request->beneficiary_phone,
        ];

        // ------------------- EXECUTE PROVIDER -------------------
        $apiResponse = $provider->send($payload);

        Log::debug("Payout Provider Response", [
            'provider' => $providerName,
            'payload'  => $payload,
            'response' => $apiResponse
        ]);

        if (!is_array($apiResponse)) {
            $report->update(['status' => 'failed']);
            return response()->json([
                'status'  => 'failed',
                'message' => 'Invalid Provider Response'
            ], 500);
        }

        if (($apiResponse['status'] ?? '') == 'failed') {
            $report->update([
                'status' => 'failed',
                'remark' => $apiResponse['error'] ?? "Provider Error"
            ]);

            return response()->json([
                'status'  => 'failed',
                'message' => $apiResponse['error'] ?? "Provider Error"
            ], 500);
        }

        // ------------------- MAP STATUS -------------------
        $providerStatus = strtolower($apiResponse['status'] ?? 'pending');
        switch ($providerStatus) {
            case 'received':
            case 'processed':
            case 'success':
                $status = 'success';
                break;
            case 'failed':
                $status = 'failed';
                break;
            default:
                $status = 'pending';
        }

        // ------------------- UPDATE REPORT -------------------
        $report->update([
            'status' => $status,
            'refno'  => $apiResponse['refno'] ?? null,
            'payid'  => $apiResponse['refno'] ?? null,
        ]);

        return response()->json([
            'status'     => 'success',
            'statuscode' => 200,
            'message'    => "Payout Successfully Initiated",
            'data'       => $apiResponse
        ], 200);

    } catch (\Exception $e) {

        Log::error("PAYOUT ERROR", [
            'line'  => $e->getLine(),
            'file'  => $e->getFile(),
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'status'  => 'failed',
            'message' => 'Internal Server Error'
        ], 500);
    }
}


    // ---------------- STATUS CHECK ----------------


    
    public function payout_status(Request $request)
    {
    
    $report = \App\Models\Report::where('product', 'payout')
        ->where('mytxnid', $request->orderid)   
        ->first();

    if (!$report) {
        return response()->json([
            'success' => false,
            'message' => 'Transaction not found',
        ]);
    }

    $txnid  = $report->txnid;
    $amount = $report->amount;
    $refno =  $report->refno ?? null;
    $status = strtoupper($report->status ?? 'UNKNOWN'); // ensure uppercase

    switch ($status) {
        case 'SUCCESS':
            return response()->json([
                'message' => 'Transaction Successfully Done.',
                'success' => true,
                'status'  => 'SUCCESS',
                'amount'  => $amount,
                'txnid'   => $txnid,
                'UTR' => $refno,
            ]);

        case 'PENDING':
            return response()->json([
                'message' => 'Transaction Pending',
                'success' => true,
                'status'  => 'PENDING',
                'amount'  => $amount,
                'txnid'   => $txnid,
                'UTR' => $refno,
            ]);

        case 'FAILED':
            return response()->json([
                'message' => 'Transaction Failed',
                'success' => false,
                'status'  => 'FAILED',
                'amount'  => $amount,
                'txnid'   => $txnid,
                'UTR' => $refno,
            ]);

        default:
            return response()->json([
                'success' => false,
                'status'  => 'UNKNOWN',
                'amount'  => $amount,
                'txnid'   => $txnid,
            ]);
    }
} 
    
    public static function getCommission($amount, $scheme, $slab, $role)
    {
        $commission = 0;
    
        try {
            $schememanager = \DB::table('portal_settings')->where('code', 'schememanager')->first(['value']);
            if ($schememanager->value != "all") {
                $myscheme = \App\Models\Scheme::find($scheme);
                if ($myscheme && $myscheme->status == "1") {
                    $comdata = \App\Models\Commission::where('scheme_id', $scheme)->where('slab', $slab)->first();
                    if ($comdata) {
                        $commission = $comdata->type == "percent"
                            ? ($amount * ($comdata[$role] ?? 0) / 100)
                            : ($comdata[$role] ?? 0);
                    }
                }
            } else {
                $myscheme = \App\Models\Package::find($scheme);
                if ($myscheme && $myscheme->status == "1") {
                    $comdata = \App\Models\Packagecommission::where('scheme_id', $scheme)->where('slab', $slab)->first();
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
    
    
    
}