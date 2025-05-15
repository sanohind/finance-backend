<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvPphSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('inv_pph')->insert([
            [
                'pph_description' => 'Pph 23',
                'pph_rate' => 0.02,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'pph_description' => 'Pph 21',
                'pph_rate' => 0.025,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'pph_description' => 'Pasal 4(2) - 10%',
                'pph_rate' => 0.10,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'pph_description' => 'Pasal 4(2) - 1,75%',
                'pph_rate' => 0.0175,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'pph_description' => 'Pasal 26 - 10%',
                'pph_rate' => 0.10,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'pph_description' => 'Pasal 26 - 0%',
                'pph_rate' => 0.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'pph_description' => 'Pasal 26 - 20%',
                'pph_rate' => 0.20,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
