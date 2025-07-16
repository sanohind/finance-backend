<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\News;

class TestNewsGet extends TestCase
{

    public function test_finance_news_index_returns_news_list()
    {
        // 1. Login sebagai finance
        $responseLogin = $this->postJson('/api/login', [
            'username' => 'finance',
            'password' => '1234abcd',
        ]);
        $responseLogin->assertStatus(200);
        $token = $responseLogin->json('access_token');
        $this->assertNotEmpty($token);

        $newsList = News::orderBy('created_at', 'desc')->get();
        $this->assertNotEmpty($newsList);

        $response = $this->getJson('/api/finance/news', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);

    }

}
