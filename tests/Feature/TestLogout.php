<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class TestLogout extends TestCase
{
    public function test_logout_success_existing_user()
    {
        // Cari user yang sudah ada di database
        $user = User::where('username', 'finance')->first();

        // Pastikan user ditemukan
        $this->assertNotNull($user);

        // Generate token untuk user tersebut
        $token = $user->createToken('auth_token')->plainTextToken;

        // Lakukan request logout dengan token
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/finance/logout');

        // Validasi response logout sukses
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'User successfully logged out'
                 ]);
    }
}
