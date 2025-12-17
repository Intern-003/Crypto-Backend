<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{

    public function store(LoginRequest $request)
    {
        $request->authenticate(); // Validates email/password
    
        $user = $request->user(); // Get the authenticated user

        // Check if account is inactive
        if (!$user->account_status) {
            return response()->json([
                'message' => 'Your account is inactive. Please contact support.',
            ], 403); // 403 Forbidden
        }
    
        // Generate token for API
        $token = $user->createToken('api_token')->plainTextToken;
        
        // Set token expiration (1 hour)
        $accessToken = $user->tokens()->latest()->first();
        $accessToken->expires_at = now()->addWeek();
        $accessToken->save();
    
        // Return JSON response
        return response()->json([
            'message' => 'Login successful for ' . ucfirst($user->role_type),
            'token' => $token,
            'auth' => auth()->id(),
            'user' => $user,
        ]);
    }

    public function destroy(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();
    
        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
    // Authorization: `Bearer ${localStorage.getItem('api_token')}`,
}
