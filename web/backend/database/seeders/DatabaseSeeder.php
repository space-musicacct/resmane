<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AmountTypeSeeder::class,
            KakeiboDefaultCategorySeeder::class,
            UpperLimitTypeSeeder::class,
            AiStatusSeeder::class,
            DummyUserSeeder::class,
            DummyKakeiboRecordSeeder::class,
        ]);
    }
}
