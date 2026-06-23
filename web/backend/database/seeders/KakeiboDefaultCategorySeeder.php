<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KakeiboDefaultCategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('kakeibo_default_categories')->insert([
            // 支出 (amount_type_id = 1)
            ['amount_type_id' => 1, 'category_name' => '飲食'],
            ['amount_type_id' => 1, 'category_name' => '交通費'],
            ['amount_type_id' => 1, 'category_name' => '趣味'],
            ['amount_type_id' => 1, 'category_name' => '交際費'],
            ['amount_type_id' => 1, 'category_name' => 'サブスク'],
            ['amount_type_id' => 1, 'category_name' => '固定費（家賃など）'],
            ['amount_type_id' => 1, 'category_name' => 'その他'],
            // 収入 (amount_type_id = 2)
            ['amount_type_id' => 2, 'category_name' => '給与'],
            ['amount_type_id' => 2, 'category_name' => 'アルバイト'],
            ['amount_type_id' => 2, 'category_name' => 'お小遣い'],
            ['amount_type_id' => 2, 'category_name' => 'その他'],
        ]);
    }
}
