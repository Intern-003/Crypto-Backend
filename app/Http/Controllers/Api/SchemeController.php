<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Scheme;

class SchemeController extends Controller
{
    public function getScheme(){
        try {
            
            $userId = auth()->id();
            if (!$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            $schemes = Scheme::with('user')->get();
    
            return response()->json([
                'status' => true,
                'message' => 'Schemes fetched successfully',
                'data' => $schemes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch schemes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function createScheme(Request $request)
    {
        try {
            
            $userId = auth()->id();
            if (!$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            $validatedData = $request->validate([
                'name'                           => 'required|string|max:255',
                'type'                           => 'nullable|string|max:255',
                'status'                         => 'boolean',
                'payin_commision_type'           => 'in:flat,percent',
                'payin_commision_amount'         => 'nullable|numeric|min:0',
                'payout_commision_type_below'    => 'in:flat,percent',
                'payout_commision_amount_below'  => 'nullable|numeric|min:0',
                'payout_commision_type_above'    => 'in:flat,percent',
                'payout_commision_amount_above'  => 'nullable|numeric|min:0',
                'rolling_payin_amount'  => 'nullable|numeric|min:0',
                'rolling_fixed_amount'  => 'nullable|numeric|min:0',
                'gst_amount'  => 'nullable|numeric|min:0',
            ]);
            
            $validatedData['user_id'] = $userId;
    
            $scheme = Scheme::create($validatedData);

            return response()->json([
                'status' => true,
                'message' => 'Scheme created successfully',
                'data' => $scheme
            ], 201);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
    
        } catch (\Exception $e) {
            \Log::error('Error creating scheme: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to create scheme',
                'error' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }
    
    public function showScheme($id)
    {
        
        try {
            $userId = auth()->id();
            if (!$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            $scheme = Scheme::with('user')->find($id);
    
            if (!$scheme) {
                return response()->json([
                    'status' => false,
                    'message' => 'Scheme not found'
                ], 404);
            }
    
            return response()->json([
                'status' => true,
                'message' => 'Scheme fetched successfully',
                'data' => $scheme
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Error fetching scheme ID ' . $id . ': ' . $e->getMessage());
    
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch scheme',
                'error' => 'An unexpected error occurred. Please try again later.'
            ], 500);
        }
    }
    
    public function updateScheme(Request $request, $id)
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            $scheme = Scheme::find($id);
    
            if (!$scheme) {
                return response()->json([
                    'status' => false,
                    'message' => 'Scheme not found'
                ], 404);
            }
    
            $validatedData = $request->validate([
                // 'user_id'                        => 'sometimes|exists:users,id',
                'name'                           => 'sometimes|string|max:255',
                'type'                           => 'nullable|string|max:255',
                'status'                         => 'boolean',
                'payin_commision_type'           => 'in:flat,percent',
                'payin_commision_amount'         => 'nullable|numeric|min:0',
                'payout_commision_type_below'    => 'in:flat,percent',
                'payout_commision_amount_below'  => 'nullable|numeric|min:0',
                'payout_commision_type_above'    => 'in:flat,percent',
                'payout_commision_amount_above'  => 'nullable|numeric|min:0',
                'rolling_payin_amount'  => 'nullable|numeric|min:0',
                'rolling_fixed_amount'  => 'nullable|numeric|min:0',
                'gst_amount'  => 'nullable|numeric|min:0',
            ]);
            
            $scheme->update($validatedData);
    
            return response()->json([
                'status' => true,
                'message' => 'Scheme updated successfully',
                'data' => $scheme
            ]);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
    
        } catch (\Exception $e) {
            \Log::error('Error updating scheme ID ' . $id . ': ' . $e->getMessage());
    
            return response()->json([
                'status' => false,
                'message' => 'Failed to update scheme',
                'error' => 'An unexpected error occurred. Please try again later.'
            ], 500);
        }
}

    public function deleteScheme($id)
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            $scheme = Scheme::find($id);
    
            if (!$scheme) {
                return response()->json([
                    'status' => false,
                    'message' => 'Scheme not found'
                ], 404);
            }
    
            $scheme->delete();
    
            return response()->json([
                'status' => true,
                'message' => 'Scheme deleted successfully'
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Error deleting scheme ID ' . $id . ': ' . $e->getMessage());
    
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete scheme',
                'error' => 'An unexpected error occurred. Please try again later.'
            ], 500);
        }
}

    public function updateSchemeStatus(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'scheme_id' => 'required|integer|exists:schemes,id',
            'status' => 'required|boolean',
        ]);
    
        // Fetch the scheme
        $scheme = Scheme::find($validated['scheme_id']);
    
        // Update status
        $scheme->status = $validated['status'];
        $scheme->save();
    
        return response()->json([
            'message' => 'Scheme status updated successfully.',
            'scheme' => $scheme,
        ]);
    }


}
