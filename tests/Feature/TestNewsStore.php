<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\InvHeader;

class TestNewsStore extends TestCase
{

    public function test_store_finance_news_with_document()
    {
        // 1. Login sebagai finance
        $responseLogin = $this->postJson('/api/login', [
            'username' => 'finance',
            'password' => '1234abcd',
        ]);
        $responseLogin->assertStatus(200);
        $token = $responseLogin->json('access_token');
        $this->assertNotEmpty($token);

        // 2. Siapkan data berita dan file dokumen
        $title = 'Pengumuman Pembayaran Vendor Juli 2025';
        $description = 'Pembayaran vendor dilakukan mulai tanggal 15 Juli 2025.';
        $startDate = '2025-07-13';
        $endDate = '2025-07-20';

        // Buat file dummy untuk upload
        $file = \Illuminate\Http\UploadedFile::fake()->create('pengumuman.pdf', 100, 'application/pdf');

        // 3. Kirim request POST untuk store berita
        $response = $this->postJson('/api/finance/news/store', [
            'title' => $title,
            'description' => $description,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'document' => $file,
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        // 4. Validasi hasil
        $response->assertStatus(201);
        $response->assertJsonFragment([
            'title' => $title,
            'description' => $description,
            'document' => "news_documents/NEWS_pengumuman_pembayaran_vendor_juli_2025.pdf",
        ]);

        // 5. Pastikan file tersimpan di disk public
        \Storage::disk('public')->assertExists("news_documents/NEWS_pengumuman_pembayaran_vendor_juli_2025.pdf");
    }
}
