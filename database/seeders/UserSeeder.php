<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('user')->insert([
            [
                'bp_code' => 'SLDUMMY',
                'name' => 'admin',
                'role' => '1', // Assuming role is stored as string, adjust if it's integer
                'status' => 1,
                'username' => 'superadmin',
                'password' => '$2y$12$cNy.7CxsF5vldwhk6DKQu.CoEJEcPkR2d/LJRWAyEcrQMl.6rUYge',
                'email' => 'superadmin@example.com', // Added a dummy email as it's in the table schema
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        ]);
    }
}
