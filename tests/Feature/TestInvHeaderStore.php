<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\InvLine;
use App\Models\InvPpn;

class TestInvHeaderStore extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();
        // Jalankan seeder/database dari file SQL Anda secara otomatis jika perlu.
        // Atau silakan import manual sebelum test.
    }

    public function test_supplier_inv_header_store_with_existing_data()
    {
        // Step 1. Login dan dapatkan token
        $responseLogin = $this->postJson('/api/login', [
            'username' => 'SLSDELA-1',
            'password' => '$anoh!nd',
        ]);
        $responseLogin->assertStatus(200); // Pastikan login sukses
        $token = $responseLogin->json('access_token');
        $this->assertNotEmpty($token);

        // 2. Ambil inv_line_id dari database yang bp_id SLSDELA-1
        $invLineIds = InvLine::where('bp_id', 'SLSDELA-1')->pluck('inv_line_id')->take(3)->toArray();
        $this->assertNotEmpty($invLineIds);

        // 3. Ambil PPN id yang valid
        $ppn = InvPpn::first();
        $this->assertNotNull($ppn);

        // 4. Siapkan data request
        $requestData = [
            'inv_no'           => 'INV-TEST-001',
            'inv_date'         => '2025-07-13',
            'inv_faktur'       => '010/FK/2025',
            'inv_faktur_date'  => '2025-07-13',
            'ppn_id'           => 1,
            'inv_line_detail'  => $invLineIds,
            // File uploads (optional, bisa diskip atau pakai fake file)
        ];

        // 5. Kirim request ke function store
        $response = $this->postJson('/api/supplier-finance/inv-header/store', $requestData, [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json', // optional
        ]);

        // 6. Validasi response sesuai function store
        $response->assertStatus(201); // atau 200 jika pakai resource
        $response->assertJsonFragment([
            'inv_no'        => 'INV-TEST-001',
            'status'        => 'New',
            'bp_code'       => 'SLSDELA-1',
        ]);
    }
}
