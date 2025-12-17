<?php

namespace App\Http\Controllers\Api\Callback\PayoutCallback;

use App\Http\Controllers\Controller;
use App\Services\CallbackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Microlog;
use App\Models\User;
use App\Models\Report;

class PayoutCallbackController extends Controller
{
    private function payoutLog($message, $data = [])
{
    $logPath = storage_path('callback/payout');

    // Create directory if not exists
    if (!file_exists($logPath)) {
        mkdir($logPath, 0777, true);
    }

    // File name by date
    $file = $logPath . '/' . date('Y-m-d') . '.log';

    // Format log
    $text = "[" . date('Y-m-d H:i:s') . "] " . $message;
    if (!empty($data)) {
        $text .= " " . json_encode($data);
    }
    $text .= "\n";

    file_put_contents($file, $text, FILE_APPEND);
}

    public function merchantCallBackResponse1($callbackurl, $status, $txnid, $mytxnid, $amount,$referenceId,$timestamp)
    {
        $this->payoutLog("Callback function called", [
        'callbackurl' => $callbackurl,
        'status'      => $status,
        'txnid'       => $txnid,
        'clienttxnid' => $mytxnid,
        'amount'      => $amount,
        'UTR'         => $referenceId,
        'timestamp'   => $timestamp,
        ]);
        $postData = [
            'status'    => $status,
            'txnid'     => $txnid,
            'clienttxnid' => $mytxnid,
            'amount'    => $amount,
            'UTR'       => $referenceId,
            'timestamp' => $timestamp,
        ];
    
        $ch = curl_init($callbackurl);
    
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData)); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // ✅ Follow redirects
        curl_setopt($ch, CURLOPT_USERAGENT, 'SpayWebhookBot/1.0'); // ✅ Set user agent
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // prevent hanging forever
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
    
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            Log::error("Callback Error: $error", [
                'callbackurl' => $callbackurl,
                'data' => $postData
            ]);
        } else {
            $this->payoutLog("Callback sent", [
                'url' => $callbackurl,
                'data' => $postData,
                'http_code' => $httpCode
            ]);
        }
    
        curl_close($ch);
    }
    
    public function busyboxCallback(Request $request)
    {
        try {
            $postData = $request->all();

            $this->payoutLog("Busybox Callback Received Raw:", $postData);

            DB::table("micro_logs")->insert([
                "product_name" => "payout busybox",
                "product_response" => json_encode($postData),
                "created_at" => now(),
                "updated_at" => now(),
            ]);

            // dd($data);

            if (!empty($postData)) {
                $this->payoutLog("Busybox Callback: Payload not empty");

                $data = json_decode($request->getContent(), true);
                $this->payoutLog("Decoded Payload:", $data ?? []);

                $payid = $data["client_ref_no"] ?? null;
                //$timestamp for merchant callback
                $timestamp = $data["txn_date"] ?? null;

                // if (!$payid) {
                //     $this->payoutLog('Busybox Callback Error: Missing client_ref_no');
                //     return response()->json(['status' => false, 'message' => 'Missing client_ref_no'], 400);
                // }

                $reportDb = DB::table("reports")
                    ->where("mytxnid", $payid)
                    ->where("status", "initiated")
                    ->where("product", "payout");

                $this->payoutLog(
                    "Matching pending report count: " . $reportDb->count()
                );

                if ($reportDb->count() == 1) {
                    $report = $reportDb->first();
                    $user = User::where("id", $report->user_id)->first();

                    if (!$user) {
                        Log::error(
                            "Busybox Callback Error: User not found for report ID {$report->id}"
                        );
                    }

                    // Handle SUCCESS
                    if (($data["status"] ?? "") === "SUCCESS") {
                        $this->payoutLog(
                            "Updating report as SUCCESS for payid: {$payid}"
                        );
                        $updateOrder = [
                            "status" => "success",
                            "option4" => $data["txn_id"] ?? null,
                        ];

                        if (!empty($data["rrn"])) {
                            $updateOrder["refno"] = $data["rrn"];
                        }

                        Report::where("id", $report->id)->update($updateOrder);
                    }

                    // Handle FAILED
                    if (($data["status"] ?? "") === "FAILURE") {
                        $this->payoutLog(
                            "Updating report as FAILED for payid: {$payid}"
                        );
                        $updateOrder = [
                            "status" => "failed",
                            "option4" => $data["txn_id"] ?? null,
                        ];

                        if (!empty($data["rrn"])) {
                            $updateOrder["refno"] = $data["rrn"];
                        }

                        Report::where("id", $report->id)->update($updateOrder);
                    }

                    // Merchant callback
                    if (
                        !empty($user->payout_callback) &&
                        $user->role_type == "user"
                    ) {
                        $this->payoutLog("Sending payout merchant callback", [
                            "callbackurl" => $user->payout_callback,
                            "role" => $user->role_type,
                            "status" => $data["status"] ?? null,
                            "txnid" => $report->txnid,
                            "mytxnid" => $payid,
                            "amount" => $report->amount,
                            "remark" => $data["rrn"] ?? null,
                            "date" => $timestamp,
                        ]);

                        CallbackService::merchantCallBackResponse(
                            $user->payout_callback,
                            $data["status"] ?? null,
                            $report->txnid,
                            $payid,
                            $report->amount,
                            $data["rrn"] ?? null,
                            $timestamp
                        );
                    }
                } else {
                    Log::warning(
                        "Busybox Callback Warning: No matching pending report found for payid: {$payid}"
                    );
                }
            } else {
                Log::error("Busybox Callback Error: Empty payload received");
            }

            return response()->json([
                "status" => true,
                "message" => "Payout Ready to work",
            ]);
        } catch (Exception $e) {
            Log::error("Busybox Callback Exception: " . $e->getMessage(), [
                "trace" => $e->getTraceAsString(),
            ]);
            return response()->json(
                ["status" => false, "message" => "Server Error"],
                500
            );
        }
    }
    public function cashfreeCallback(Request $post){
        // dump("hello");
        // dd($post);
            $this->payoutLog('Cashfree Payout Callback Received Raw:', $post->all());

    try {
        // Log request payload to DB
        \DB::table('micro_logs')->insert([
            'product_response' => json_encode($post->all()),
            'product_name'  => 'cashfree payout',
            'created_at' => now(),
            'updated_at' => now(),            
        ]);

        if (empty($post->all())) {
            $this->payoutLog('Cashfree Payout Callback Error: Empty payload received');
            return response()->json(['status' => false, 'message' => 'Empty payload'], 400);
        }

        $this->payoutLog('Cashfree Payout Callback: Payload not empty');

        $data = json_decode($post->getContent(), true);
        $this->payoutLog('Decoded Payload:', $data ?? []);

        $inner = $data['data']['data'] ?? [];

        $payid           = $inner['transfer_id'] ?? null;
        $timestamp       = $inner['added_on'] ?? null;
        $mode            = $inner['transfer_mode'] ?? null;
        $callback_status = $inner['status'] ?? null;
        $utr             = $inner['transfer_utr'] ?? null;
        $cf_transfer_id  = $inner['cf_transfer_id'] ?? null;

        if (!$payid) {
            $this->payoutLog('Cashfree Callback Error: Missing transfer_id');
            return response()->json(['status' => false, 'message' => 'Missing transfer_id'], 400);
        }

        $reportDb = \DB::table('reports')
            ->where('mytxnid', $payid)
            ->where('status', 'pending')
            ->where('product', 'payout');

        $count = $reportDb->count();
        $this->payoutLog("Matching pending report count: {$count}");

        if ($count == 1) {

            $report = $reportDb->first();
            $user   = User::where('id', $report->user_id)->first();

            if (!$user) {
                $this->payoutLog("Cashfree Payout Callback Error: User not found for report ID {$report->id}");
            }

            /**
             * ------------------------------------------------------
             * HANDLE SUCCESS STATUS
             * ------------------------------------------------------
             */
            if ($callback_status === "SUCCESS") {

                $this->payoutLog("Updating report as SUCCESS for payid: {$payid}");

                $updateOrder = [
                    'status'        => 'success',
                    'payout_mode'  => strtoupper($mode),
                    'option4'       => $cf_transfer_id,
                ];

                if (!empty($utr)) {
                    $updateOrder['refno'] = $utr;
                    $updateOrder['payid'] = $utr;
                }

                Report::where('id', $report->id)->update($updateOrder);
            }

            /**
             * ------------------------------------------------------
             * HANDLE FAILED STATUS
             * ------------------------------------------------------
             */
            if ($callback_status === "FAILED" || $callback_status === "FAILURE") {

                $this->payoutLog("Updating report as FAILED for payid: {$payid}");

                // Refund total (amount + charge)
                $total = $report->amount + $report->charge;

                // Refund wallet
                $user->increment('mainwallet', $total);

                $updateOrder = [
                    'status'        => 'refunded',
                    'option4'       => $cf_transfer_id,
                    'remark'        => "Refunded ₹{$total} to wallet",
                    'payout_mode'  => strtoupper($mode),
                ];

                if (!empty($utr)) {
                    $updateOrder['refno'] = $utr;
                    $updateOrder['payid'] = $utr;
                }

                Report::where('id', $report->id)->update($updateOrder);

                $this->payoutLog("Refund of ₹{$total} processed to user {$user->id} for report {$report->id}");
            }

            /**
             * ------------------------------------------------------
             * MERCHANT CALLBACK
             * ------------------------------------------------------
             */
            if ($user->payout_callback) {

                $this->payoutLog("Sending cashfree payout merchant callback", [
                    'callbackurl' => $user->payout_callback,
                    'status'      => $callback_status,
                    'txnid'       => $report->txnid,
                    'mytxnid'     => $payid,
                    'amount'      => $report->amount,
                    'remark'      => $utr,
                    'date'        => $timestamp,
                ]);

                $this->merchantCallBackResponse(
                    $user->payout_callback,
                    $callback_status,
                    $report->txnid,
                    $payid,
                    $report->amount,
                    $utr,
                    $timestamp
                );
            }

        } else {
            $this->payoutLog("Cashfree Payout Callback Warning: No matching pending report found for payid: {$payid}");
        }

        return response()->json(['status' => true, "message" => "Payout Ready to work"]);

    } catch (\Exception $e) {
        $this->payoutLog("Cashfree Payout Callback Exception: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json(['status' => false, 'message' => 'Server Error'], 500);
    }
    }
}
