<?php

namespace App\Http\Controllers\Api\Callback\PayinCallback;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Report;

class PayinCallbackController extends Controller
{
    /**
     * Send callback to merchant.
     */
     private function payinLog($message, $data = []){
         
         $log_path = storage_path('callback/payin');
         
         if(!file_exists($log_path)){
             mkdir($log_path, 0777,true);
         }
         $file = $log_path . '/' . date('Y-m-d') . 'log';
         
         $text = ' [ '. date('Y-m-d H:i:s') . ' ] '. " : " . $message;
         
         if(!empty($data)){
             $text .= " " .json_encode($data);
         }
         $text .= "\n\n";
         file_put_contents($file, $text, FILE_APPEND);
         
         
     }
    public function merchantCallBackResponse($callbackurl, $status, $txnid, $mytxnid, $amount, $referenceId, $timestamp)
    {
        $this->payinLog("Callback function called", [
            'callbackurl' => $callbackurl,
            'status' => $status,
            'txnid' => $txnid,
            'clienttxnid' => $mytxnid,
            'amount' => $amount,
            'UTR' => $referenceId,
            'timestamp' => $timestamp,
        ]);

        $postData = [
            'status' => $status,
            'txnid' => $txnid,
            'clienttxnid' => $mytxnid,
            'amount' => $amount,
            'UTR' => $referenceId,
            'timestamp' => $timestamp,
        ];

        $ch = curl_init($callbackurl);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'SpayWebhookBot/1.0',
            CURLOPT_TIMEOUT => 300,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
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
            $this->payinLog("Callback sent", [
                'url' => $callbackurl,
                'data' => $postData,
                'http_code' => $httpCode,
                'response' => $response
            ]);
        }

        curl_close($ch);
    }

    /**
     * Handle Airpay callback.
     */
    public function airpaycallbkp(Request $request)
    {
        // dd("hello");
        $this->payinLog('Airpay Callback Received', [
            'request_array' => $request->all(),
            'raw_content' => $request->getContent()
        ]);

        // Store raw request for debugging
        DB::table('micro_logs')->insert([
            'product_response' => json_encode($request->all()),
            'product_name' => 'Airpay',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = json_decode($request->getContent(), true);
        // $data = json_decode($request->input('response'), true);
        if (!$data) {
            $this->payinLog('Failed to decode Airpay response', ['input' => $request->getContent()]);
            return response()->json(['status' => false, 'message' => 'Invalid response']);
        }

        $payid = $data['decodedResponse']['data']['orderid'] ?? null;
        $airpayStatus = $data['decodedResponse']['data']['transaction_payment_status'] ?? null;
        $timestamp = $data['timestamp'] ?? null;

        if (!$payid) {
            $this->payinLog('payid missing in Airpay response', ['response' => $data]);
            return response()->json(['status' => false, 'message' => 'Order ID missing']);
        }

        $report = Report::where('mytxnid', $payid)
            ->where('status', 'initiated')
            ->where('product', 'UPI')
            ->first();

        if (!$report) {
            $this->payinLog('No report found for payid', ['payid' => $payid]);
            return response()->json(['status' => false, 'message' => 'Report not found']);
        }

        $user = User::find($report->user_id);
        $refno = $data['decodedResponse']['data']['rrn'] ?? null;

        // Prepare report update
        $updateOrder = [
            'option2' => $data['decodedResponse']['data']['charge_type'] ?? null,
            'option3' => $data['decodedResponse']['data']['ap_securehash'] ?? null,
        ];

        // Override reason if failed
        if ($airpayStatus === 'FAILED') {
            $updateOrder['option2'] = $data['decodedResponse']['data']['reason'] ?? null;
        }

        if ($refno) {
            $updateOrder['refno'] = $refno;
        }

        $updateOrder['status'] = $airpayStatus === 'SUCCESS' ? 'success' : ($airpayStatus === 'FAILED' ? 'failed' : $report->status);

        $report->update($updateOrder);

        $this->payinLog("Report updated", [
            'report_id' => $report->id,
            'update' => $updateOrder
        ]);

        // Trigger merchant callback if configured and valid
        if ($user->payin_callback) {
            $this->payinLog('Calling merchant callback', [
                'callbackurl' => $user->payin_callback,
                'status' => $airpayStatus,
                'txnid' => $report->txnid,
                'mytxnid' => $report->mytxnid,
                'amount' => $report->amount,
                'refno' => $refno,
                'timestamp' => $timestamp
            ]);

            $this->merchantCallBackResponse(
                $user->payin_callback,
                $airpayStatus,
                $report->txnid,
                $report->mytxnid,
                $report->amount,
                $refno,
                $timestamp
            );
        }

        $this->payinLog('Airpay callback processing finished');

        return response()->json(['status' => true, 'message' => 'AIRPAY Ready to work']);
    }
}
