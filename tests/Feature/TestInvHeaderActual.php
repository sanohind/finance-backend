<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\InvHeader;

class TestInvHeaderActual extends TestCase
{

    public function test_upload_payment_documents_marks_invoices_paid()
    {
        // 1. Login sebagai finance
        $responseLogin = $this->postJson('/api/login', [
            'username' => 'finance',
            'password' => '1234abcd',
        ]);
        $responseLogin->assertStatus(200);
        $token = $responseLogin->json('access_token');
        $this->assertNotEmpty($token);

        // 2. Ambil beberapa invoice dengan status 'Ready To Payment'
        $invHeaders = InvHeader::where('status', 'Ready To Payment')->take(2)->get();
        $this->assertNotEmpty($invHeaders);

        $invIds = $invHeaders->pluck('inv_id')->toArray();

        // 3. Kirim request POST untuk upload payment documents
        $actualDate = '2025-07-13';
        $payload = [
            'inv_ids'     => $invIds,
            'actual_date' => $actualDate,
        ];

        $response = $this->postJson('/api/finance/inv-header/upload-payment', $payload, [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        // 4. Validasi hasil
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'success' => true,
            'actual_date' => $actualDate,
        ]);
        $response->assertJsonFragment([
            'message' => count($invIds) . ' invoices marked as Paid'
        ]);

        // 5. Pastikan invoice di database berubah status jadi Paid
        foreach ($invIds as $invId) {
            $this->assertEquals('Paid', InvHeader::find($invId)->status);
        }
    }
}
