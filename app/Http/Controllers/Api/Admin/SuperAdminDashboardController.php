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
}
