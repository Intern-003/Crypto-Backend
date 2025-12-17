<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuthToken;
use Illuminate\Support\Str;

class AuthTokenController extends Controller
{
    
    public function getTokens(Request $request)
    {
        try {
            $userId = auth()->id();
    
            $tokens = AuthToken::where('user_id', $userId)->get();
    
            return response()->json([
                'success' => true,
                'user_id' => $userId,
                'data' => $tokens
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
}
    
    public function generateAuthToken(Request $request)
    {
        try {
            
            $userId = auth()->id();
            if (!$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Generate a random 30-character token
            $token = Str::random(30);

            // Save the token with user_id and request IP
            $authToken = AuthToken::create([
                'user_id' => $userId,
                'token'   => $token,
                'ip'      => $request->ip(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Auth token generated successfully',
                'data' => $authToken
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error generating auth token: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to generate auth token',
                'error' => 'An unexpected error occurred. Please try again later.'
            ], 500);
        }
    }
    
    public function deleteAuthToken($id)
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
    
            $authToken = \App\Models\AuthToken::find($id);
    
            if (!$authToken) {
                return response()->json([
                    'status' => false,
                    'message' => 'Token not found'
                ], 404);
            }
    
            // Ensure the token belongs to the logged-in user
            if ($authToken->user_id !== $userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to delete this token'
                ], 403);
            }
    
            $authToken->delete();
    
            return response()->json([
                'status' => true,
                'message' => 'Auth token deleted successfully'
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Error deleting auth token ID ' . $id . ': ' . $e->getMessage());
    
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete auth token',
                'error' => 'An unexpected error occurred. Please try again later.'
            ], 500);
        }
}

}
