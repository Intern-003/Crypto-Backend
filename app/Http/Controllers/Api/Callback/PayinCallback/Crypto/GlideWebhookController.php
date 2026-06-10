<?php

namespace App\Http\Controllers\Api\Callback\PayinCallback\Crypto;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreGlideWebhookRequest;
use App\Services\GlideTransactionService;
use App\Models\GlideSession;
use App\Models\User;
use App\Models\Report;


class GlideWebhookController extends Controller
{
    protected $service;
    //protected $logPath;

    public function __construct(GlideTransactionService $service)
    {
        $this->service = $service;
    }

    private function webhookLog($message, $data = []){
         
        $log_path = storage_path('callback/webhook-general');
         
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

    
    private function payoutLog($message, $data = [])
    {
        $log_path = storage_path('callback/payout');

        // Create directory if not exists
        if (!file_exists($log_path)) {
            mkdir($log_path, 0777, true);
        }

        // File name by date
        $file = $log_path . '/' . date('Y-m-d') . '.log';

        // Format log
        $text = "[" . date('Y-m-d H:i:s') . "] " . $message;
        if (!empty($data)) {
            $text .= " " . json_encode($data);
        }
        $text .= "\n";

        file_put_contents($file, $text, FILE_APPEND);
    }

    //public function handleGlideWebhook(StoreGlideWebhookRequest $request)
    public function handleGlideWebhook(Request $request)
    {
        $this->webhookLog('Glide Callback Received', [
            'request_array' => $request->all(),
            'raw_content' => $request->getContent()
        ]);
        
        // Store raw request for debugging
        DB::table('micro_logs')->insert([
            'product_response' => json_encode($request->all()),
            'product_name' => 'Glide',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            $this->webhookLog('Failed to decode Glide Webhook response', ['input' => $request->getContent()]);
            return response()->json(['status' => false, 'message' => 'Invalid response']);
        } else {
            $webHookType = $data['type'];
            $metaDataId = $data['payload']['metadata'] ?? null;
            $webhookId = $data['webhookId'] ?? null;
            if (!$metaDataId) {
                $this->webhookLog('metadata id missing in Glide sessions', ['response' => $data]);
                return response()->json(['status' => false, 'message' => 'Metadata missing']);
            } else {
                $decryptedMetaData = $this->service->processMetaData('decrypt', $metaDataId);
                $webhookFor = $decryptedMetaData["product"] ?? null;

                if($webhookFor !== null)
                {
                    // Determine which log method to call
                    $logMethod = match ($webhookFor) {
                        "CRYPTO" => "payinLog",
                        "payout" => "payoutLog",
                        default => "webhookLog",
                    };
                    
                    // Call the method dynamically
                    $this->{$logMethod}('Glide Callback Received', [
                        'request_array' => $request->all(),
                        'raw_content' => $request->getContent(),
                    ]);
                } 

                $glideStage1Status  = $data['payload']['paymentStatus'] ?? null;
                $glideStage1Txn     = $data['payload']['paymentTransactionHash'] ?? null;
                
                $glideStage2Status  = $data['payload']['sponsoredTransactionStatus'] ?? null;
                $glideStage2Txn     = $data['payload']['sponsoredTransactionHash'] ?? null;

                $shortMetaId = $this->service->getShortMeta($metaDataId);

                // Determine which log method to call
                $reportProuct = match ($webhookFor) {
                    "CRYPTO" => "CRYPTO",
                    "payout" => "payout",
                    default => "CRYPTO",
                };

                $report = Report::where('apitxnid', $shortMetaId)
                                ->where('status', 'pending')
                                ->where('product', $reportProuct)
                                ->first();
                
                if($glideStage1Status === 'paid' && $glideStage2Status === "success") {
                    // $metaDataHash = DB::table('glide_sessions')
                    //             ->where('glide_response', $metaDataId)
                    //             ->where('stages', "Glide-Session-MetaData")
                    //             ->first();
                                
                    // if (!$metaDataHash) {
                    //     Log::warning('metadata id missing in Glide sessions', ['response' => $data]);
                    //     return response()->json(['status' => false, 'message' => 'Metadata missing']);
                    // } else {
                        //$metaDataIdParam = $metaDataHash->glide_request;
                        
                        //$decryptedMetaData = $this->service->processMetaData('decrypt', $metaDataId); //$this->service->decryptEncryptedToken($metaDataIdParam);
                        
                        //$shortMetaId = $this->service->getShortMeta($metaDataId);
                        
                        // $report = Report::where('apitxnid', $shortMetaId)
                        //                 ->where('status', 'pending')
                        //                 ->where('product', 'CRYPTO')
                        //                 ->first();

                        if (!$report) {
                            //dd("---------report", $report, "----shormetaid - ", $shortMetaId, "----status - pending", "----product - ", $reportProuct);

                            // Call the method dynamically
                            $this->{$logMethod}('No report found for metadata_id', ['metadataid' => $shortMetaId]);
                            return response()->json(['status' => false, 'message' => 'Report not found']);
                        }
                        $user = User::find($report->user_id);
                        
                        if($user->id == $decryptedMetaData["userId"]) {
                            $timestamp = $data['payload']['createdAt'] ?? null;
                        
                            // Prepare report update
                            if($webhookFor == "CRYPTO")
                            {
                                $updateOrder = [
                                    'option2' => $data['type'] ?? null,
                                    'option3' => $glideStage2Txn ?? null,
                                ];
                            } else if($webhookFor == "payout")
                            {
                                $updateOrder = [
                                    'option4' => $glideStage2Txn ?? null,
                                ];
                            }
                            
                        
                            $updateOrder['status'] = $glideStage1Status === 'paid' && $glideStage2Status === "success" ? 'success' : ($glideStage1Status === 'unpaid' ? 'failed' : $report->status);
                            
                            $report->update($updateOrder);
                        
                            // Call the method dynamically
                            $this->{$logMethod}("Report updated", [
                                'report_id' => $report->id,
                                'update' => $updateOrder
                            ]);
                        
                            $tx = $this->service->handleWebhook($request->all());
                            
                            // Call the method dynamically
                            $this->{$logMethod}('Glide callback processing finished',[
                                'success' => true,
                                'transaction_id' => $tx->id
                            ]);

                            return response()->json([
                                'success' => true,
                                'transaction_id' => $tx->id
                            ]);
                        } else {
                            $this->{$logMethod}('Userid missmatched in Glide response', ['response' => $data]);
                            return response()->json(['status' => false, 'message' => 'USER ID missing']);
                        }
                    //}    
                }
            }    
        }
        
        return null;
    }

    public function listAllTransactions()
    {
        return response()->json([
            'data' => app(\App\Repositories\GlideTransactionRepository::class)->all()
        ]);
    }
}
