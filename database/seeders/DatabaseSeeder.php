<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\InvPphSeeder; // Add this line
use Database\Seeders\InvPpnSeeder; // Add this line
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            InvPphSeeder::class, // Add this line
            InvPpnSeeder::class, // Added InvPpnSeeder
            InvLineSeeder::class, // Added InvLineSeeder
            // Add other seeders here if needed
        ]);
    }
}
