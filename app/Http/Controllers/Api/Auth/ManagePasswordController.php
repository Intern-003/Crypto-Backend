<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ManagePasswordController extends Controller
{
    public function changePassword(Request $request)
    {   

        $validatedData = $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:8', // expects new_password_confirmation
        ]);
        
        $user = $request->user();
        
        // dd($validatedData);

        // Check if old password is correct
        if (!Hash::check($request->old_password, $user->password)) {
            throw ValidationException::withMessages([
                'old_password' => ['The provided password does not match your current password.'],
            ]);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
    
}
