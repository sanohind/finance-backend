<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AuthController
{
    // Login function
    public function login(Request $request)
    {
        // Define validation rules
        $rules = [
            'username' => 'required',
            'password' => 'required',
        ];

        // Validator instance
        $validator = Validator::make($request->all(), $rules);

        // Check validation fails
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Login validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Attempt authentication using Laravel's built-in Auth::attempt()
            if (!Auth::attempt($request->only(['username', 'password']))) {
                Log::warning('Failed login attempt', [
                    'username' => $request->username,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Username or Password Invalid'
                ], 401);
            }

            // Retrieve the authenticated user
            $user = Auth::user();

            // Check if user status is inactive
            if ($user->status == 0) {
                Auth::logout();
                
                Log::warning('Inactive user login attempt', [
                    'username' => $user->username,
                    'user_id' => $user->id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Account is inactive'
                ], 403);
            }

            // Generate a token
            $token = $user->createToken('auth_token')->plainTextToken;

            Log::info('Successful login', [
                'username' => $user->username,
                'user_id' => $user->id,
                'role' => $user->role
            ]);

            // Return token response
            return response()->json([
                'success' => true,
                'access_token' => $token,
                'role' => $user->role,
                'bp_code' => $user->bp_code,
                'name' => $user->name,
                'token_type' => 'Bearer',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage(), [
                'username' => $request->username,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during login. Please try again.'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            // Revoke token
            $request->user()->currentAccessToken()->delete();

            Log::info('User logged out successfully', [
                'user_id' => $request->user()->id,
                'username' => $request->user()->username
            ]);

            // logout success respond
            return response()->json([
                'success' => true,
                'message' => 'User successfully logged out'
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout'
            ], 500);
        }
    }
}
/**
 * Note:
 * 1. Last used token masih null belum ada history lognya
 * 2. expires at token masih null belum ada timeoutnya
 */
