<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Local\Partner;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function getBusinessPartner()
    {
        $partner = Partner::where('bp_code', 'LIKE', 'SL%')->get();
        return response()->json($partner);
    }

    public function index()
    {
        $users = User::all();
        return UserResource::collection($users);
    }

    // Create new user (bp_code required)
    public function store(UserStoreRequest $request)
    {
        $request->validated();

        User::create([
            'bp_code' => $request->bp_code,
            'name' => $request->name,
            'role' => $request->role,
            'status' => $request->status,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'email' => $request->email,
        ]);

        return response()->json(['message' => 'User created']);
    }

    // Show edit form (get user data)
    public function edit($id)
    {
        $user = User::findOrFail($id);
        return new UserResource($user);
    }

    // Update all data
    public function update(UserUpdateRequest $request, $id)
    {
        $request->validated();

        $user = User::where('user_id', $id)->first();

        $user->update([
            'bp_code' => $request->bp_code ?? $user->bp_code,
            'name' => $request->name ?? $user->name,
            'role' => $request->role ?? $user->role,
            'username' => $request->username ?? $user->username,
            'password' => $request->password ?? $user->password,
            'email' => $request->email ?? $user->email,
        ]);

        return response()->json(['message' => 'User updated']);
    }

    // Delete account
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }

    // Update status (0 inactive, 1 active)
    public function updateStatus($id)
    {
        $user = User::where('user_id', $id)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found']);
        }

        if ($user -> status == 1) {
            $user->status = 0;
            $user->save();
        }else if ($user -> status == 0) {
            $user->status = 1;
            $user->save();
        }

        return new UserResource($user);
    }
}
