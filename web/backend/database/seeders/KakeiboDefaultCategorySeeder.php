<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB

class KakeiboDefaultCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        DB::table('kakeibo_default_categories')->insert([
            ['category_name' => '食費'],
            ['category_name' => '交通費'],
            ['category_name' => '趣味'],
            ['category_name' => '交際費'],
            ['category_name' => 'サブスク'],
            ['category_name' => '固定費（家賃など）'],
        ]);
    }
}
