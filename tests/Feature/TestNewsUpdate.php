<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\News;

class TestNewsUpdate extends TestCase
{

    public function test_update_finance_news_with_new_document()
    {
        // 1. Login sebagai finance
        $responseLogin = $this->postJson('/api/login', [
            'username' => 'finance',
            'password' => '1234abcd',
        ]);
        $responseLogin->assertStatus(200);
        $token = $responseLogin->json('access_token');
        $this->assertNotEmpty($token);

        // 2. Ambil satu berita yang sudah ada
        $news = News::first();
        $this->assertNotNull($news);

        // 3. Siapkan data baru dan dokumen baru
        $newTitle = 'Update Pengumuman Vendor Juli 2025';
        $newDescription = 'Revisi jadwal pembayaran vendor ke tanggal 18 Juli 2025.';
        $newStartDate = '2025-07-15';
        $newEndDate = '2025-07-18';
        $file = \Illuminate\Http\UploadedFile::fake()->create('revisi_pengumuman.pdf', 120, 'application/pdf');

        // 4. Kirim request PUT ke /api/finance/news/update/{id} dengan dokumen baru
        $response = $this->putJson("/api/finance/news/update/{$news->id}", [
            'title' => $newTitle,
            'description' => $newDescription,
            'start_date' => $newStartDate,
            'end_date' => $newEndDate,
            'document' => $file,
        ], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        // 5. Validasi hasil
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'title' => $newTitle,
            'description' => $newDescription,
            'document' => "news_documents/NEWS_update_pengumuman_vendor_juli_2025.pdf",
        ]);

        // 6. Pastikan file dokumen baru tersimpan di disk public
        \Storage::disk('public')->assertExists("news_documents/NEWS_update_pengumuman_vendor_juli_2025.pdf");
    }

}
