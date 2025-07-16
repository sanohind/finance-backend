<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\InvHeader;

class TestInvHeaderVerifikasi extends TestCase
{

    public function test_update_invoice_status_to_in_process()
    {
        // 1. Login sebagai finance
        $responseLogin = $this->postJson('/api/login', [
            'username' => 'finance',
            'password' => '1234abcd',
        ]);
        $responseLogin->assertStatus(200);
        $token = $responseLogin->json('access_token');
        $this->assertNotEmpty($token);

        // 2. Pastikan ada invoice dengan status 'New'
        $invHeader = InvHeader::where('status', 'New')->first();
        $this->assertNotNull($invHeader);

        // 3. Kirim request PUT ke /in_process/{inv_id}
        $response = $this->putJson("/api/finance/inv-header/in-process/{$invHeader->inv_id}", [], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        // 4. Validasi hasil
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'message' => "Invoice {$invHeader->inv_no} status updated to In Process"
        ]);

        // 5. Validasi di database
        $this->assertEquals('In Process', $invHeader->fresh()->status);
    }
}
