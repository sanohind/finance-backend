<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Local\Partner;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Requests\UserUpdatePersonalRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function getBusinessPartner()
    {
        $partners = Partner::all();
        return response()->json($partners);
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

    // Get authenticated user profile data
    public function profile()
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        return new UserResource($user);
    }

    // Update all data
    public function update(UserUpdateRequest $request, $id)
    {
        $request->validated();

        $user = User::where('user_id', $id)->first();

        // Prepare update data
        $updateData = [
            'bp_code' => $request->bp_code ?? $user->bp_code,
            'name' => $request->name ?? $user->name,
            'role' => $request->role ?? $user->role,
            'username' => $request->username ?? $user->username,
            'email' => $request->email ?? $user->email,
        ];

        // Only update password if provided
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return response()->json(['message' => 'User updated']);
    }

    // Update user personal data
    public function updatePersonal(UserUpdatePersonalRequest $request)
    {
        $request->validated();

        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        // Update all personal data fields
        $updateData = [
            'name' => $request->name ?? $user->name,
            'username' => $request->username ?? $user->username,
            'email' => $request->email ?? $user->email,
        ];

        // Only update password if provided
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return response()->json([
            'message' => 'Personal data updated successfully',
            'user' => new UserResource($user)
        ]);
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
