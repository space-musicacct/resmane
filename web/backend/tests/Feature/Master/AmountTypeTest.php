<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Models\User;
use Database\Seeders\AmountTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 結合テスト仕様書 7.2 GET /api/v1/amountTypes（収支区分一覧）
 */
class AmountTypeTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/amountTypes';

    protected function setUp(): void
    {
        parent::setUp();

        \DB::statement('ALTER TABLE amount_types AUTO_INCREMENT = 1');
        $this->seed(AmountTypeSeeder::class);
    }

    /** @test FMA-001 正常: 全収支区分取得 */
    public function test_can_get_all_amount_types(): void
    {
        $user = User::create([
            'login_id' => 'taro',
            'email' => 'taro@example.com',
            'name' => '太郎',
            'password_hash' => Hash::make('password123'),
        ]);

        $this->actingAs($user);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['typeName' => '支出'])
            ->assertJsonFragment(['typeName' => '収入']);
    }

    /** @test FMA-002 正常: レスポンス構造 */
    public function test_response_structure(): void
    {
        $user = User::create([
            'login_id' => 'taro',
            'email' => 'taro@example.com',
            'name' => '太郎',
            'password_hash' => Hash::make('password123'),
        ]);

        $this->actingAs($user);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'typeName'],
                ],
            ]);
    }

    /** @test FMA-003 異常: 未認証 */
    public function test_requires_authentication(): void
    {
        $this->getJson(self::ENDPOINT)
            ->assertStatus(401);
    }
}
