<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    // Create new user (bp_code required)
    public function store(Request $request)
    {
        $request->validate([
            'bp_code' => 'required|string|max:25',
            'name'    => 'nullable|string|max:255',
            'role'    => 'nullable|string|max:25',
            'status'  => 'nullable|integer',
            'username'=> 'nullable|string|max:25',
            'password'=> 'nullable|string|max:255',
            'email'   => 'nullable|string|max:255',
        ]);

        $user = User::create($request->all());
        return response()->json($user);
    }

    // Show edit form (get user data)
    public function edit($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    // Update all data
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->update($request->all());
        return response()->json($user);
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
