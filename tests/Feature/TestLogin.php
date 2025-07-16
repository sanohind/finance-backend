<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
// use Illuminate\Foundation\Testing\RefreshDatabase;

class TestLogin extends TestCase
{
    // use RefreshDatabase;

    public function test_login_validation_error()
    {
        $response = $this->postJson('/api/login', [
            'username' => '', // Username kosong
            'password' => '1234abcd'
        ]);
        $response->assertStatus(422)
                ->assertJson(['message' => 'Login validation error']);
    }

    public function test_login_wrong_password()
    {
        $response = $this->postJson('/api/login', [
            'username' => 'finance',
            'password' => 'salah'
        ]);
        $response->assertStatus(401)
                ->assertJson(['message' => 'Username or Password Invalid']);
    }

    // Test sukses login (pastikan user dan password ada di database)
    public function test_login_success()
    {
        $response = $this->postJson('/api/login', [
            'username' => 'finance',
            'password' => '1234abcd' // plain text, not hashed!
        ]);
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'access_token',
                    'role',
                    'bp_code',
                    'name',
                    'token_type'
                ]);
    }

    // Tambahkan test lain sesuai kebutuhan...
}
