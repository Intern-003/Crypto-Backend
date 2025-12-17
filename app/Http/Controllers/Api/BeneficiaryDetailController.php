<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BeneficiaryDetail;

class BeneficiaryDetailController extends Controller
{
    public function storeBeneficiaryDetails(Request $request)
    {
        $user = auth()->id();
        $validated = $request->validate([
            'bank_name'               => 'required|string|max:255',
            'account_no'              => 'required|string|max:50',
            'ifsc_code'               => 'required|string|max:20',
            'upi_number'              => 'nullable|string|max:50',
            'beneficiary_name'        => 'required|string|max:255',
            'beneficiary_mobile_no'   => 'nullable|string|max:15',
            'beneficiary_email_id'    => 'nullable|email|max:255',
            'beneficiary_address'     => 'nullable|string|max:255',
        ]);

        $validated['user_id'] = $user;
        
        $beneficiary = BeneficiaryDetail::create($validated);
        
        return response()->json([
            'status' => true,
            'message' => 'Beneficiary added successfully',
            'data' => $beneficiary,
        ], 201);
    }
    
    public function BeneficiaryDetailsList(Request $request)
    {
    try {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'Unauthorized access',
            ], 401);
        }

        $beneficiaryDetails = BeneficiaryDetail::where('user_id', $user->id)->get();

        return response()->json([
            'status'  => true,
            'message' => 'Beneficiary list fetched successfully',
            'data'    => $beneficiaryDetails,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Something went wrong',
            'error'   => $e->getMessage(),
        ], 500);
    }
}
    
    public function deleteBeneficiaryDetails(Request $request, $id)
    {
        try {
            $deleteBeneficiaryDetails = BeneficiaryDetail::findOrFail($id);
            $deleteBeneficiaryDetails->delete();
    
            return response()->json([
                'status' => true,
                'message' => 'Beneficiary deleted successfully'
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

}
