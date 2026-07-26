<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 結合テスト仕様書 7.1 GET /api/v1/categories（カテゴリ一覧）
 */
class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/categories';

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        \DB::table('amount_types')->insert([
            ['id' => 1, 'type_name' => '支出'],
            ['id' => 2, 'type_name' => '収入'],
        ]);

        \DB::table('kakeibo_default_categories')->insert([
            ['id' => 1, 'amount_type_id' => 1, 'category_name' => '飲食'],
            ['id' => 2, 'amount_type_id' => 1, 'category_name' => '交通費'],
            ['id' => 3, 'amount_type_id' => 1, 'category_name' => '趣味'],
            ['id' => 4, 'amount_type_id' => 1, 'category_name' => '交際費'],
            ['id' => 5, 'amount_type_id' => 1, 'category_name' => 'サブスク'],
            ['id' => 6, 'amount_type_id' => 1, 'category_name' => '固定費（家賃など）'],
            ['id' => 7, 'amount_type_id' => 1, 'category_name' => 'その他'],
            ['id' => 8, 'amount_type_id' => 2, 'category_name' => '給与'],
            ['id' => 9, 'amount_type_id' => 2, 'category_name' => 'アルバイト'],
            ['id' => 10, 'amount_type_id' => 2, 'category_name' => 'お小遣い'],
            ['id' => 11, 'amount_type_id' => 2, 'category_name' => 'その他'],
        ]);

        $this->user = User::create([
            'login_id' => 'taro',
            'email' => 'taro@example.com',
            'name' => '太郎',
            'password_hash' => Hash::make('password123'),
        ]);
    }

    /** @test FMC-001 正常: 全カテゴリ取得 */
    public function test_can_get_all_categories(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertStatus(200)
            ->assertJsonCount(11, 'data');
    }

    /** @test FMC-002 正常: amountTypeId で絞り込み（支出） */
    public function test_can_filter_by_expense_type(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson(self::ENDPOINT . '?amountTypeId=1');

        $response->assertStatus(200)
            ->assertJsonCount(7, 'data');

        $data = $response->json('data');
        foreach ($data as $category) {
            $this->assertEquals(1, $category['amountTypeId']);
        }
    }

    /** @test FMC-003 正常: amountTypeId で絞り込み（収入） */
    public function test_can_filter_by_income_type(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson(self::ENDPOINT . '?amountTypeId=2');

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data');

        $data = $response->json('data');
        foreach ($data as $category) {
            $this->assertEquals(2, $category['amountTypeId']);
        }
    }

    /** @test FMC-004 正常: レスポンスに amountTypeId を含む */
    public function test_response_contains_amount_type_id(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson(self::ENDPOINT);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'amountTypeId', 'categoryName'],
                ],
            ]);
    }

    /** @test FMC-005 異常: 未認証 */
    public function test_requires_authentication(): void
    {
        $this->getJson(self::ENDPOINT)
            ->assertStatus(401);
    }
}
