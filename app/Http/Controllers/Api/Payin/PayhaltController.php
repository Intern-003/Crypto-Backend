<?php

namespace App\Http\Controllers\Api\Payin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Scheme;
use App\Models\AuthToken;
use App\Models\Report;


class PayhaltController extends Controller
{
    
    public function PayHalt_create($request, $apiToken)
{
    $transactionAmount = $request->amount;
    
    $user = User::find($apiToken->user_id);
    
    $schemeInfo = Scheme::where('id', $user->scheme_id)
        ->where('status', true)
        ->first();

    if (!$schemeInfo) {
        return response()->json([
            'status' => 'failed',
            'message' => 'The selected scheme is discontinued or inactive.'
        ], 404);
    }

    // ---------------------
    // Commission Calculation
    // ---------------------
    $payinCommissionType   = $schemeInfo->payin_commision_type;
    $payinCommissionAmount = $schemeInfo->payin_commision_amount;

    $calculatedCommission = 0;
    if ($payinCommissionType === 'percent') {
        $calculatedCommission = ($transactionAmount * $payinCommissionAmount) / 100;
    } elseif ($payinCommissionType === 'flat') {
        $calculatedCommission = $payinCommissionAmount;
    }

    // ---------------------
    // GST on commission
    // ---------------------
    $gst = ($calculatedCommission * 18) / 100;

    // ---------------------
    // Rolling Charge
    // ---------------------
    $rollingPayinAmount = $schemeInfo->rolling_payin_amount;   // percent per txn
    $rollingFixedAmount = $schemeInfo->rolling_fixed_amount;   // flat, store only

    $rollingCharge = 0;  // applied to this transaction
    $rolling_amount = 0; // stored in report

    if (!empty($rollingPayinAmount)) {
        $rollingCharge = ($transactionAmount * $rollingPayinAmount) / 100;
        $rolling_amount = $rollingCharge; // also store in report
    } elseif (!empty($rollingFixedAmount)) {
        $rollingCharge = 0;              // do not deduct from txn
        $rolling_amount = $rollingFixedAmount; // store in report
    }

    // ---------------------
    // Final Calculations
    // ---------------------
    $totalCommissionWithGst = $calculatedCommission + $gst;
    $remainingAmount = $transactionAmount - ($totalCommissionWithGst + $rollingCharge);

    $orderId = 'SPAY' . now()->format('YmdHis') . rand(11111111, 99999999);

    $data = [
        "gst"                   => $gst,
        "charge"                => $calculatedCommission,
        "mobile"                => $request->mobile,
        "option1"               => $request->mid,
        "txnid"                 => $orderId,
        "payid"                 => $orderId,
        "mytxnid"               => $request->apitxnid,
        "amount"                => $transactionAmount,
        "user_id"               => $apiToken->user_id,
        "profit"                => $totalCommissionWithGst,
        "payin_amount"          => $remainingAmount,
        "payin_rolling_amount"  => $rolling_amount,
        "transaction_type"            => "credit",
        'status'               => 'initiated',
        'remark'               => 'payhalt',
        'product'              => "UPI",
        "payment_platform"     => 'api',
        "description"          => 'Payment initiated',
        "payer_name"           => $request->name,
        "payer_email"          => $request->email
    ];

    //  dd($data); // remove in production
    $report = \App\Models\Report::create($data);

    return $report;
}

    
    public function Generate_request(Request $request)
    {
        // dd('heloo');
        $rules = [
            'token'    => 'required',
            'apitxnid' => 'required|alpha_num|min:8|max:20|unique:reports,mytxnid',
            'email'    => 'required|email',
            'mobile'   => 'required|digits_between:10,15',
            'amount'   => 'required|numeric|min:1',
            'name'     => 'required|string',
            'mid'      => 'required|string',
        ];
    
        $validator = \Validator::make($request->all(), $rules);
    
        // ðŸ”¹ Validation failed
        if ($validator->fails()) {
            return response()->json([
                'statuscode' => 422,
                'message'    => $validator->errors()->first(),
            ], 422);
        }
    
        // ðŸ”¹ Check API token
        $apiToken = AuthToken::where('ip', $request->ip())
            ->where('token', $request->token)
            ->first(['user_id']);

        if (!$apiToken) {
            return response()->json([
                'statuscode' => 401,
                'message'    => "IP or Token mismatch. Your IP is " . $request->ip(),
            ], 401);
        }
    
        // ðŸ”¹ Check User PayIN status
        $user = User::find($apiToken->user_id);
        if (!$user || (int) $user->payin_status !== 1) {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 403,
                'message'    => 'Your PayIN account is deactivated. Please contact the administrator.',
            ], 403);
        }

        // ðŸ”¹ Create report
        $response = $this->PayHalt_create($request, $apiToken);
    
        if ($response instanceof \App\Models\Report) {

            $report = $response;
            // âœ… Prepare payload
            $payload = http_build_query([
                'mid'    => $request->mid,
                'amount' => $request->amount,
                'udf1'   => 'Exam Payment',
                'phone'  => $request->mobile,
                'email'  => $request->email,
                'return_url' =>$request->return_url,
            ]);
    
            // âœ… Call external API using cURL
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://seagreen-panther-562760.hostingersite.com/merchant/api/auth/api.php',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/x-www-form-urlencoded',
                ],
            ]);
    
            $curlResponse = curl_exec($curl);
    
            // ðŸ”¹ Handle cURL error
            if (curl_errno($curl)) {
                $curlError = curl_error($curl);
                curl_close($curl);
    
                return response()->json([
                    'statuscode' => 502,
                    'message'    => 'Curl Error: ' . $curlError,
                ], 502);
            }
    
            curl_close($curl);
    
            // âœ… Decode JSON response
            $decodedResponse = json_decode($curlResponse, true);
    //dd($decodedResponse);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedResponse)) {
                // Filter required fields
                $filteredResponse = [
                    'success'      => $decodedResponse['success'] ?? false,
                    'amount'       => $decodedResponse['amount'] ?? null,
                    'orderId'      => $decodedResponse['orderId'] ?? null,
                    'Key'          => $decodedResponse['token'] ?? null,
                    'payment_link' => $decodedResponse['payment_link'] ?? null,
                ];
    
                // Save key in report
                $report->update([
                    'option4' => $filteredResponse['Key'],
                    'option1' => $filteredResponse['orderId'],
                ]);
    
                return response()->json([
                    'statuscode' => 200,
                    'message'    => 'Payment request processed',
                    'apiResponse'=> $filteredResponse,
                ], 200);
            } else {
                // ðŸ”¹ If response is not valid JSON
                return response()->json([
                    'statuscode' => 200,
                    'message'    => 'Payment request processed (raw)',
                    'apiResponse'=> $curlResponse,
                ], 200);
            }
        }
    }
}
