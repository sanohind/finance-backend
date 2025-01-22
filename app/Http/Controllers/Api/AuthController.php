<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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

        // Find the user by username
        $user = User::where('username', $request->username)->first();

        // Validate user existence and password with
        if (!Auth::attempt($request->only(['username', 'password']))) {
            return response()->json([
                'success' => false,
                'message' => 'Username or Password Invalid'
            ], 401);
        }

        // Retrieve the authenticated user
        $user = Auth::user();

        // if user status inactive
        if ($user->status==0) {
            Auth::logout();

            return response()->json([
                'success' => false,
                'message' => 'Account is inactive'
            ], 403);
        }

        // Generate a token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Return token response
        return response()->json([
            'access_token' => $token,
            'role' => $user->role,
            'bp_code' => $user->bp_code,
            'name' => $user->name,
            'token_type' => 'Bearer',
            // 'supplier_name' => ($user->role != 1||2||3||4) ? $user->partner->adr_line_1:"PT Sanoh Indonesia",
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke token
        $request->user()->currentAccessToken()->delete();

        // logout success respond
        return response()->json([
            'success' => true,
            'message' => 'User successfully logged out'
        ], 200);
    }
}
/**
 * Note:
 * 1. Last used token masih null belum ada history lognya
 * 2. expires at token masih null belum ada timeoutnya
 */
