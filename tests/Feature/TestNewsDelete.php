<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\News;

class TestNewsDelete extends TestCase
{

    public function test_finance_news_destroy_deletes_news_and_document()
    {
        // 1. Login sebagai finance
        $responseLogin = $this->postJson('/api/login', [
            'username' => 'finance',
            'password' => '1234abcd',
        ]);
        $responseLogin->assertStatus(200);
        $token = $responseLogin->json('access_token');
        $this->assertNotEmpty($token);

        // 2. Siapkan data berita dengan dokumen
        $news = News::create([
            'title' => 'Pengumuman Hapus',
            'description' => 'Berita ini akan dihapus.',
            'start_date' => now(),
            'end_date' => now()->addDays(3),
            'document' => 'news_documents/NEWS_pengumuman_hapus.pdf',
            'created_by' => 'Finance',
        ]);
        // Simulasikan file dokumen ada di storage publik
        \Storage::disk('public')->put($news->document, 'dummy content');
        $this->assertTrue(\Storage::disk('public')->exists($news->document));

        // 3. Kirim request DELETE ke /api/finance/news/delete/{id}
        $response = $this->deleteJson("/api/finance/news/delete/{$news->id}", [], [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);

        // 4. Validasi hasil response
        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'News deleted successfully.']);

        // 5. Pastikan berita dan dokumen terhapus
        $this->assertNull(News::find($news->id));
        $this->assertFalse(\Storage::disk('public')->exists($news->document));
    }

}
