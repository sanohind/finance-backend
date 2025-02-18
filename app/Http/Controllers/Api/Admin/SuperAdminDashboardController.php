<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class SuperAdminDashboardController extends Controller
{
    public function dashboard()
    {
        // Initialize data array
        $data = [];

        // Calculate the timestamp for one hour ago
        $oneHourAgo = now()->subHour();

        // Get the count of tokens created within the last hour (online users)
        $data['online_users'] = PersonalAccessToken::where('created_at', '>=', $oneHourAgo)->count();

        // Get the total count of users
        $data['total_users'] = User::count();

        // Get the count of active users where status is 1
        $data['active_users'] = User::where('status', 1)->count();

        // Get the count of deactivated users where status is 0
        $data['deactive_users'] = User::where('status', 0)->count();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard Data Retrieved Successfully',
            'data' => $data,
        ]);
    }

    public function detailActiveUser()
    {
        // Calculate the timestamp for one hour ago
        $oneHourAgo = now()->subHour();

        // Get the active tokens created within the last hour
        $active_tokens = PersonalAccessToken::where('created_at', '>=', $oneHourAgo)
            ->with('tokenable') // Ensure we load the related user
            ->whereNull('expires_at')
            ->get();

        // Map the active tokens to the required details
        $active_token_details = $active_tokens->map(function ($token) {
            return [
                'username' => $token->tokenable->username,
                'name' => $token->tokenable->name,
                'role' => $token->tokenable->role,
                'last_login' => $token->created_at->format('d/m/Y - H:i:s'),
                'last_update' => $token->last_used_at ? $token->last_used_at->format('d/m/Y - H:i:s') : null,
                'id' => $token->id,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Active Token Details Retrieved Successfully',
            'data' => $active_token_details,
        ]);
    }

    public function logoutByTokenId(Request $request)
    {
        // Validate the request to ensure 'token_id' is provided
        $request->validate([
            'token_id' => 'required|integer',
        ]);

        // Find the token by ID
        $token = PersonalAccessToken::find($request->token_id);

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Token not found',
            ], 404);
        }

        // Revoke the specific token
        $token->delete();

        // Logout success response
        return response()->json([
            'success' => true,
            'message' => 'Token successfully revoked',
        ], 200);
    }
}
