<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['login_id' => 'taro_yamada', 'name' => '山田太郎', 'email' => 'taro@example.com'],
            ['login_id' => 'hanako_sato', 'name' => '佐藤花子', 'email' => 'hanako@example.com'],
            ['login_id' => 'yuki_tanaka', 'name' => '田中ゆき', 'email' => 'yuki@example.com'],
        ];

        foreach ($users as $user) {
            User::create([
                'login_id' => $user['login_id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'password_hash' => Hash::make('hogehoge'),
            ]);
        }
    }
}
