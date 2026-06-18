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
        ['status_name' => '未処理'],
        ['status_name' => '処理中'],
        ['status_name' => '完了'],
        ['status_name' => 'エラー'],
    ]);
    }
}
