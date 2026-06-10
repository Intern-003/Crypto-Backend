<?php

namespace App\Http\Controllers\Api\Payin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Credential;
use App\Models\Scheme;
use App\Models\AuthToken;
use App\Models\Report;
use Validator;

class Dashboard_PayinController extends Controller
{

    public function AIRpay_create($request, $apiToken)
    {
        $transactionAmount = $request->amount;
        $user = User::find($apiToken);

        $schemeInfo = Scheme::where('id', $user->scheme_id)
            ->where('status', true)
            ->first();

        if (!$schemeInfo) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Scheme not defined for this user',
            ], 400);
        }

        // Commission Calculation
        $payinCommissionType   = $schemeInfo->payin_commision_type;
        $payinCommissionAmount = $schemeInfo->payin_commision_amount;
        
        // $Set_GST = $schemeInfo->gst_amount;
        // dd($Set_GST);

        $calculatedCommission = 0;
        if ($payinCommissionType === 'percent') {
            $calculatedCommission = ($transactionAmount * $payinCommissionAmount) / 100;
        } elseif ($payinCommissionType === 'flat') {
            $calculatedCommission = $payinCommissionAmount;
        }

        // GST on commission
        $gst = ($calculatedCommission * 18) / 100;

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
        $remainingAmount = $transactionAmount - ($totalCommissionWithGst + $rollingCharge);

        $orderId = 'SPAY' . now()->format('YmdHis') . rand(11111111, 99999999);

        $data = [
            "gst"                  => $gst,
            "charge"               => $calculatedCommission,
            "mobile"               => $request->buyer_phone,
            "txnid"                => $orderId,
            "payid"                => $orderId,
            "mytxnid"              => $request->orderid,
            "amount"               => $transactionAmount,
            "user_id"              => $apiToken,
            "profit"               => $totalCommissionWithGst,
            "payin_amount"         => $remainingAmount,
            "payin_rolling_amount" => $rolling_amount,
            "transaction_type"     => "credit",
            "status"               => "initiated",
            "remark"               => "airpay",
            "product"              => "UPI",
            "payment_platform"     => "portal",
            "description"          => "Payment initiated",
            "payer_email"          => $request->buyer_email,
            "payer_name"           => $request->buyer_name,
            "option1"              =>'payin calculation is pending',
        ];

        return Report::create($data);
    }

    public function generate_Airpay_UPIQR(Request $request)
    {
        $rules = [
            'orderid'      => 'required|alpha_num|min:8|max:20|unique:reports,mytxnid',
            'buyer_email'  => 'required|email',
            'buyer_phone'  => 'required|digits_between:10,15',
            'amount'       => 'required|numeric|min:10',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'statuscode' => 422,
                'message'    => $validator->errors()->first(),
            ], 422);
        }

        $apiToken = auth()->id();

        if (!$apiToken) {
            return response()->json([
                'statuscode' => 401,
                'message'    => "User not authenticated",
            ], 401);
        }

        $user = User::find($apiToken);
        if (!$user || (int) $user->payin_status !== 1) {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 403,
                'message'    => 'Your PayIN account is deactivated. Please contact admin.',
            ], 403);
        }

        $report = $this->AIRpay_create($request, $apiToken);

        if (!($report instanceof Report)) {
            return response()->json([
                'status'     => 'failed',
                'message'    => 'Unable to create report entry',
            ], 500);
        }
        $credential = Credential::find($user->credentials_id);
        
        $description = $credential->description;
        
        // decode JSON from DB
        if (is_string($description)) {
            $description = json_decode($description, true);
        }

// dump($description);
        // Prepare payload
        $payload = [
            'orderid'     => $request->orderid,
            'amount'      => $request->amount,
            'buyer_email' => $request->buyer_email,
            'buyer_phone' => $request->buyer_phone,
            'individualIdentifier'  => json_encode($description),
        ];
//   dump($payload);
        // Send to QR API
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://soulfuloverseas.com/QR/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,

        ]);

        // Execute cURL request
        $response = curl_exec($curl);
        // dd($response);
            curl_close($curl);
            
            // Remove any text before the first { (start of JSON)
            if (preg_match('/\{.*\}/s', $response, $matches)) {
                $jsonString = $matches[0];
            } else {
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 500,
                    'message'    => 'No valid JSON found in QR API response',
                    'raw'        => $response
                ], 500);
            }
            
            // Decode JSON
            $decodedResponse = json_decode($jsonString, true);
            
            // Check for success
            if (!is_array($decodedResponse) || ($decodedResponse['response']['status'] ?? '') !== 'success') {
                return response()->json([
                    'status'     => 'failed',
                    'statuscode' => 500,
                    'message'    => 'QR API did not return success',
                    'raw'        => $response
                ], 500);
            }
            
            $res = $decodedResponse['response'];
            
            // Extract only required fields
            $statusCode = $res['status_code'] ?? null;
            $status    = $res['status'] ?? null;
            $data      = $res['data'] ?? [];
            
            $filteredData = [
                'qrcode_string'    => $data['qrcode_string'] ?? null,
                'orderid' =>  $request->orderid ?? null,
                'txnid'          => $report->txnid, // from AIRpay_create
            ];
            
            $report->update([
                    'apitxnid' => $data['ap_transactionid'] ?? null,
                ]);
            
            // Return clean JSON response
            return response()->json([
                'status_code' => $statusCode,
                'status'      => $status,
                'data'        => $filteredData
            ], 200);
    }
    
//     public function check_status(Request $request)
//     {
//     // dd($request->orderid);
//     $report = \App\Models\Report::where('product', 'UPI')
//         ->where('remark', 'airpay')
//         ->where('description', 'Payment counted')
//         ->where('mytxnid', $request->orderid)   
//         ->first();

//     if (!$report) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Transaction not found',
//         ]);
//     }

//     $txnid  = $report->txnid;
//     $amount = $report->amount;
//     $status = strtoupper($report->status ?? 'UNKNOWN'); // ensure uppercase

//     switch ($status) {
//         case 'SUCCESS':
//             return response()->json([
//                 'message' => 'Transaction Successfully Done.',
//                 'success' => true,
//                 'status'  => 'SUCCESS',
//                 'amount'  => $amount,
//                 'txnid'   => $txnid,
//             ]);

//         case 'PENDING':
//             return response()->json([
//                 'message' => 'Transaction Pending',
//                 'success' => true,
//                 'status'  => 'PENDING',
//                 'amount'  => $amount,
//                 'txnid'   => $txnid,
//             ]);

//         case 'FAILED':
//             return response()->json([
//                 'message' => 'Transaction Failed',
//                 'success' => false,
//                 'status'  => 'FAILED',
//                 'amount'  => $amount,
//                 'txnid'   => $txnid,
//             ]);

//         default:
//             return response()->json([
//                 'success' => false,
//                 'status'  => 'UNKNOWN',
//                 'amount'  => $amount,
//                 'txnid'   => $txnid,
//             ]);
//     }
// }
}
