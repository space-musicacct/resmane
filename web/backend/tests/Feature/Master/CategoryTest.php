<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Models\User;
use Database\Seeders\AmountTypeSeeder;
use Database\Seeders\KakeiboDefaultCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ApiEndpoint;
use Tests\Support\V1ApiEndpoint;
use Tests\TestCase;

/**
 * 結合テスト仕様書 7.1 GET /api/v1/categories（カテゴリ一覧）
 */
class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private ApiEndpoint $endpoint;

    protected User $user;

    protected int $expenseTypeId;

    protected int $incomeTypeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = new V1ApiEndpoint;

        DB::statement('ALTER TABLE amount_types AUTO_INCREMENT = 1');
        $this->seed(AmountTypeSeeder::class);
        $this->seed(KakeiboDefaultCategorySeeder::class);

        $this->expenseTypeId = DB::table('amount_types')->where('type_name', '支出')->value('id');
        $this->incomeTypeId = DB::table('amount_types')->where('type_name', '収入')->value('id');

        $this->user = User::create([
            'login_id' => 'taro',
            'email' => 'taro@example.com',
            'name' => '太郎',
            'password_hash' => Hash::make('password123'),
        ]);
    }

    /** FMC-001 正常: 全カテゴリ取得 */
    #[Test]
    public function test_can_get_all_categories(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson($this->endpoint->categories());

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(11, 'data');
    }

    /** FMC-002 正常: amountTypeId で絞り込み（支出） */
    #[Test]
    public function test_can_filter_by_expense_type(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson($this->endpoint->categories().'?amountTypeId='.$this->expenseTypeId);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(7, 'data');

        $data = $response->json('data');
        foreach ($data as $category) {
            $this->assertEquals($this->expenseTypeId, $category['amountTypeId']);
        }
    }

    /** FMC-003 正常: amountTypeId で絞り込み（収入） */
    #[Test]
    public function test_can_filter_by_income_type(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson($this->endpoint->categories().'?amountTypeId='.$this->incomeTypeId);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(4, 'data');

        $data = $response->json('data');
        foreach ($data as $category) {
            $this->assertEquals($this->incomeTypeId, $category['amountTypeId']);
        }
    }

    /** FMC-004 正常: レスポンスに amountTypeId を含む */
    #[Test]
    public function test_response_contains_amount_type_id(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson($this->endpoint->categories());

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'amountTypeId', 'categoryName'],
                ],
            ]);
    }

    /** FMC-005 異常: 未認証 */
    #[Test]
    public function test_requires_authentication(): void
    {
        $this->getJson($this->endpoint->categories())
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
}
