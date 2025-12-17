<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PayoutOnboardedBank;

class PayoutOnboardedBankController extends Controller
{
    public function OnboardPayOutBank(Request $request)
    {
        try
        {
            $validatedData = $request->validate([
                'onboard_payout_bank'          => 'required|string|max:255',
                'onboarded_payout_bank_status' => 'boolean',
            ]);
    
            $bank = PayoutOnboardedBank::create([
                'onboard_payout_bank'          => $validatedData['onboard_payout_bank'],
                'onboarded_payout_bank_status' => $validatedData['onboarded_payout_bank_status'] ?? true,
            ]);
    
            return response()->json([
                'message' => 'Bank added successfully',
                'report'  => $bank
            ], 201);
        }
        catch (\Exception $e) 
        {
            return response()->json([
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    
    public function updatePayoutBankStatus(Request $request)
    {
        $validated = $request->validate([
            'id'     => 'required|integer|exists:payout_onboarded_banks,id',
            'onboarded_payout_bank_status' => 'required|boolean',
        ]);
    
        $bank = PayoutOnboardedBank::findOrFail($validated['id']);
        $bank->update([
            'onboarded_payout_bank_status' => $validated['onboarded_payout_bank_status'],
        ]);
    
        return response()->json([
            'status'  => true,
            'message' => 'Payout bank status updated successfully',
            'data'    => $bank,
        ]);
    }
    
    public function DestroyPayOutBank($id)
    {
        try {
            $bank = PayoutOnboardedBank::findOrFail($id);
            $bank->delete();
    
            return response()->json([
                'message' => 'Bank deleted successfully'
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    
    public function ListPayOutBanks(Request $request)
    {
        try {
            // Optional filter for active/inactive
            $status = $request->query('status'); // 1 = active, 0 = inactive
    
            // Build the query
            $query = PayoutOnboardedBank::query();
    
            if (!is_null($status)) {
                $query->where('onboarded_payout_bank_status', $status);
            }
    
            // Fetch the records
            $banks = $query->get();
    
            // Return JSON response
            return response()->json([
                'message' => 'Payout banks fetched successfully',
                'data'    => $banks
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong while fetching payout banks',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
