<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PayinOnboardedBank;

class PayinOnboardedBankController extends Controller
{
    public function OnboardPayInBank(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'onboard_payin_bank'          => 'required|string|max:255',
                'onboarded_payin_bank_status' => 'boolean', // 1 = active, 0 = inactive
            ]);
            
            \Log::info('Pay-In Bank Onboarding - Validated Data', [
            'validated_data' => $validatedData,
            'timestamp' => now()->toISOString()
            ]);
    
            $payInBank = PayinOnboardedBank::create([
                'onboard_payin_bank'          => $validatedData['onboard_payin_bank'],
                'onboarded_payin_bank_status' => $validatedData['onboarded_payin_bank_status'] ?? true,
            ]);
            
            \Log::info('Pay-In Bank Onboarding - Record Created', [
            'pay_in_bank_id' => $payInBank->id,
            'bank_name' => $payInBank->onboard_payin_bank,
            'status' => $payInBank->onboarded_payin_bank_status,
            'created_at' => $payInBank->created_at,
            'timestamp' => now()->toISOString()
            ]);
    
            return response()->json([
                'message' => 'Pay-In bank added successfully',
                'report'  => $payInBank
            ], 201);
    
        } 
        catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    
    public function UpdatePayInBank(Request $request, $id)
    {
        try {
            $validatedData = $request->validate([
                // 'onboard_payin_bank'          => 'required|string|max:255',
                'onboarded_payin_bank_status' => 'boolean',
            ]);
    
            $payInBank = PayinOnboardedBank::findOrFail($id);
    
            $payInBank->update([
                // 'onboard_payin_bank'          => $validatedData['onboard_payin_bank'],
                'onboarded_payin_bank_status' => $validatedData['onboarded_payin_bank_status'] ?? $payInBank->onboarded_payin_bank_status,
            ]);
    
            return response()->json([
                'message' => 'Pay-In bank updated successfully',
                'report'  => $payInBank
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    
    public function DestroyPayInBank($id)
    {
        // dd('hello');
        try {
            $payInBank = PayinOnboardedBank::findOrFail($id);
            $payInBank->delete();
    
            return response()->json([
                'message' => 'Pay-In bank deleted successfully'
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function ListPayInBanks(Request $request)
    {
        $status = $request->query('status'); // optional filter: active/inactive
    
        $query = PayinOnboardedBank::query();
    
        if (!is_null($status)) {
            $query->where('onboarded_payin_bank_status', $status);
        }
    
        $banks = $query->get();
    
        return response()->json([
            'message' => 'Pay-In banks fetched successfully',
            'data'    => $banks
        ], 200);
    }
    
    public function updatePayinBankStatus(Request $request)
    {
        $validated = $request->validate([
            'id'     => 'required|integer|exists:payin_onboarded_banks,id',
            'onboarded_payin_bank_status' => 'required|boolean',
        ]);
    
        $bank = PayinOnboardedBank::findOrFail($validated['id']);
        $bank->update([
            'onboarded_payin_bank_status' => $validated['onboarded_payin_bank_status'],
        ]);
    
        return response()->json([
            'status'  => true,
            'message' => 'Pay-In bank status updated successfully',
            'data'    => $bank,
        ]);
    }
}
