<?php

declare(strict_types=1);

namespace Tests\Feature\Master;

use App\Models\User;
use Database\Seeders\AmountTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Support\ApiEndpoint;
use Tests\Support\V1ApiEndpoint;

/**
 * 結合テスト仕様書 7.2 GET /api/v1/amountTypes（収支区分一覧）
 */
class AmountTypeTest extends TestCase
{
    use RefreshDatabase;

    private ApiEndpoint $endpoint;


    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = new V1ApiEndpoint();

        DB::statement('ALTER TABLE amount_types AUTO_INCREMENT = 1');
        $this->seed(AmountTypeSeeder::class);
    }

    /** FMA-001 正常: 全収支区分取得 */
    #[Test]
    public function test_can_get_all_amount_types(): void
    {
        $user = User::create([
            'login_id' => 'taro',
            'email' => 'taro@example.com',
            'name' => '太郎',
            'password_hash' => Hash::make('password123'),
        ]);

        $this->actingAs($user);

        $response = $this->getJson($this->endpoint->amountTypes());

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['typeName' => '支出'])
            ->assertJsonFragment(['typeName' => '収入']);
    }

    /** FMA-002 正常: レスポンス構造 */
    #[Test]
    public function test_response_structure(): void
    {
        $user = User::create([
            'login_id' => 'taro',
            'email' => 'taro@example.com',
            'name' => '太郎',
            'password_hash' => Hash::make('password123'),
        ]);

        $this->actingAs($user);

        $response = $this->getJson($this->endpoint->amountTypes());

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'typeName'],
                ],
            ]);
    }

    /** FMA-003 異常: 未認証 */
    #[Test]
    public function test_requires_authentication(): void
    {
        $this->getJson($this->endpoint->amountTypes())
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
}
