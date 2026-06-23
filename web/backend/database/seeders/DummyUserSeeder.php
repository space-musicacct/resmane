<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyUserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'login_id' => 'taro_yamada',
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password_hash' => Hash::make('hogehoge'),
        ]);
    }
}
