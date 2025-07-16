<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\InvHeader;

class TestInvHeaderUpdate extends TestCase
{

    public function test_update_invoice_status_to_ready_to_payment()
    {
        // 1. Login sebagai finance
        $responseLogin = $this->postJson('/api/login', [
            'username' => 'finance',
            'password' => '1234abcd',
        ]);
        $responseLogin->assertStatus(200);
        $token = $responseLogin->json('access_token');
        $this->assertNotEmpty($token);

        // 2. Ambil invoice dengan status yang dapat di-update
        $invHeader = InvHeader::where('status', 'In Process')->first();
        $this->assertNotNull($invHeader);

        // 3. Kirim request PUT untuk update menjadi Ready To Payment
        $updateData = [
            'status'      => 'Ready To Payment',
            'plan_date'   => '2025-07-15',
            // Tambahkan field lain sesuai kebutuhan validasi (misal reason, pph_id, pph_base_amount, dsb)
        ];

        $response = $this->putJson("/api/finance/inv-header/{$invHeader->inv_id}", $updateData, [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        // 4. Validasi hasil
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'message' => "Invoice {$invHeader->inv_no} Is Ready To Payment"
        ]);
    }

    // public function test_update_invoice_status_to_rejected()
    // {
    //     $responseLogin = $this->postJson('/api/login', [
    //         'username' => 'finance',
    //         'password' => '1234abcd',
    //     ]);
    //     $token = $responseLogin->json('access_token');

    //     $invHeader = InvHeader::where('status', 'In Process')->first();
    //     $this->assertNotNull($invHeader);

    //     // Kirim request PUT untuk update menjadi Rejected
    //     $updateData = [
    //         'status' => 'Rejected',
    //         'reason' => 'Data tidak valid',
    //     ];

    //     $response = $this->putJson("/api/finance/inv-header/{$invHeader->inv_id}", $updateData, [
    //         'Authorization' => 'Bearer ' . $token,
    //         'Accept' => 'application/json',
    //     ]);

    //     $response->assertStatus(200);
    //     $response->assertJsonFragment([
    //         'message' => "Invoice {$invHeader->inv_no} Rejected: Data tidak valid"
    //     ]);
    // }

    // public function test_update_invoice_status_to_rejected_without_reason()
    // {
    //     $responseLogin = $this->postJson('/api/login', [
    //         'username' => 'finance',
    //         'password' => '1234abcd',
    //     ]);
    //     $token = $responseLogin->json('access_token');

    //     $invHeader = InvHeader::where('status', 'In Process')->first();
    //     $this->assertNotNull($invHeader);

    //     // Kirim request PUT untuk update menjadi Rejected tanpa reason
    //     $updateData = [
    //         'status' => 'Rejected',
    //         // 'reason' => '', // tidak dikirim
    //     ];

    //     $response = $this->putJson("/api/finance/inv-header/{$invHeader->inv_id}", $updateData, [
    //         'Authorization' => 'Bearer ' . $token,
    //         'Accept' => 'application/json',
    //     ]);

    //     $response->assertStatus(422);
    //     $response->assertJsonFragment([
    //         'message' => 'Reason is required when rejecting an invoice'
    //     ]);
    // }
}
