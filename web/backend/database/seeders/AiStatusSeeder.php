<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AiStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
    DB::table('ai_statuses')->insert([
        ['status_name' => 'pending'],
        ['status_name' => 'procesing'],
        ['status_name' => 'completed'],
        ['status_name' => 'failed'],
    ]);
    }
}
