<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;

class UserController extends Controller
{
    // Create new user (bp_code required)
    public function store(UserStoreRequest $request)
    {
        $request->validated();

        User::create([
            'bp_code' => $request->suppleir_code,
            'name' => $request->name,
            'role' => $request->role,
            'status' => $request->status,
            'username' => $request->username,
            'password' => $request->password,
            'email' => $request->email,
        ]);

        return response()->json(['message' => 'User created']);
    }

    // Show edit form (get user data)
    public function edit($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    // Update all data
    public function update(UserUpdateRequest $request, $id)
    {
        $request->validated();

        User::update([
            'bp_code' => $request->suppleir_code,
            'name' => $request->name,
            'role' => $request->role,
            'username' => $request->username,
            'password' => $request->password,
            'email' => $request->email,
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
    public function updateStatus($id, $status)
    {
        $user = User::findOrFail($id);
        $user->status = $status;
        $user->save();
        return response()->json($user);
    }
}
