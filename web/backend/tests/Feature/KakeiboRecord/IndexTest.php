<?php

namespace Tests\Feature\KakeiboRecord;

use App\Models\AmountType;
use App\Models\KakeiboDefaultCategory;
use App\Models\KakeiboRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Support\ApiEndpoint;
use Tests\Support\V1ApiEndpoint;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    private ApiEndpoint $endpoint;


    private User $user;
    private AmountType $expense;
    private AmountType $income;
    private KakeiboDefaultCategory $category1;
    private KakeiboDefaultCategory $category2;

    /**
     * 一覧取得テストで使用するユーザー、収支区分、カテゴリを準備する
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = new V1ApiEndpoint();

        // API実行用の認証済みユーザーを作成
        $this->user = User::forceCreate([
            'login_id' => 'test001',
            'email' => 'test@example.com',
            'name' => 'test',
            'password_hash' => bcrypt('password'),
        ]);

        // 支出区分を作成
        $this->expense = AmountType::forceCreate([
            'id' => 1,
            'type_name' => '支出',
        ]);

        // 収入区分を作成
        $this->income = AmountType::forceCreate([
            'id' => 2,
            'type_name' => '収入',
        ]);

        // テストで使用するカテゴリを作成
        $this->category1 = KakeiboDefaultCategory::forceCreate([
            'amount_type_id' => $this->expense->id,
            'category_name' => '食費',
        ]);

        $this->category2 = KakeiboDefaultCategory::forceCreate([
            'amount_type_id' => $this->expense->id,
            'category_name' => '交通費',
        ]);

        // 認証済みユーザーとしてAPIを実行
        Sanctum::actingAs($this->user);
    }


    /**
     * 指定条件で家計簿レコードを作成するヘルパーメソッド
     */
    private function createRecord(array $override = []): KakeiboRecord
    {
        return KakeiboRecord::create(array_merge([
            'user_id' => $this->user->id,
            'purchase_date' => '2026-07-01',
            'amount_type_id' => $this->expense->id,
            'amount' => 1000,
            'details' => 'test',
            'kakeibo_default_category_id' => $this->category1->id,
        ], $override));
    }


    /**
     * 指定件数の家計簿レコードを作成するヘルパーメソッド
     */
    private function createRecords(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->createRecord();
        }
    }


    /** FKI-001 正常: 一覧取得 */
    #[Test]
    public function test_FKI001_index_returns_records(): void
    {
        // 一覧取得対象となる複数レコードを作成
        $this->createRecords(5);

        $response = $this->getJson($this->endpoint->records());

        // 一覧データ、メタ情報、集計情報が返却されることを確認
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data',
                'meta',
                'summary',
            ]);
    }


    /** FKI-002 正常: レコード0件 */
    #[Test]
    public function test_FKI002_index_returns_empty_when_no_records(): void
    {
        // レコードが存在しない場合のレスポンスを確認
        $response = $this->getJson($this->endpoint->records());

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'data' => [],
                'meta' => [
                    'total' => 0,
                ],
            ]);
    }


    /** FKI-003 正常: ページネーション */
    #[Test]
    public function test_FKI003_index_paginates_correctly(): void
    {
        // ページング確認用に上限を超えるレコードを作成
        $this->createRecords(25);

        $response = $this->getJson($this->endpoint->records() . '?perPage=20&page=2');

        // 2ページ目の取得結果を確認
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(5, 'data')
            ->assertJson([
                'meta' => [
                    'currentPage' => 2,
                ],
            ]);
    }


    /** FKI-004 正常: ソート（新しい順） */
    #[Test]
    public function test_FKI004_index_sorts_desc(): void
    {
        // 日付が異なるレコードを作成
        $this->createRecord([
            'purchase_date' => '2026-07-01',
        ]);

        $this->createRecord([
            'purchase_date' => '2026-07-10',
        ]);

        // 新しい日付順で取得できることを確認
        $response = $this->getJson($this->endpoint->records() . '?sort=desc');

        $response->assertStatus(Response::HTTP_OK);

        $this->assertEquals(
            '2026-07-10',
            $response->json('data.0.purchaseDate')
        );
    }


    /** FKI-005 正常: ソート（古い順） */
    #[Test]
    public function test_FKI005_index_sorts_asc(): void
    {
        // 日付が異なるレコードを作成
        $this->createRecord([
            'purchase_date' => '2026-07-01',
        ]);

        $this->createRecord([
            'purchase_date' => '2026-07-10',
        ]);

        // 古い日付順で取得できることを確認
        $response = $this->getJson($this->endpoint->records() . '?sort=asc');

        $response->assertStatus(Response::HTTP_OK);

        $this->assertEquals(
            '2026-07-01',
            $response->json('data.0.purchaseDate')
        );
    }


    /** FKI-006 正常: 期間フィルタ */
    #[Test]
    public function test_FKI006_index_filters_by_date_range(): void
    {
        // 期間外と期間内のレコードを作成
        $this->createRecord([
            'purchase_date' => '2026-06-01',
        ]);

        $this->createRecord([
            'purchase_date' => '2026-07-01',
        ]);

        // 指定期間内のみ取得されることを確認
        $response = $this->getJson(
            $this->endpoint->records() . '?from=2026-07-01&to=2026-07-31'
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data');

        $this->assertEquals('2026-07-01', $response->json('data.0.purchaseDate'));
    }
    /** FKI-007 正常: 収支区分フィルタ */
    #[Test]
    public function test_FKI007_index_filters_by_amount_type(): void
    {
        // 収入・支出それぞれのレコードを作成
        $this->createRecord([
            'amount_type_id' => $this->income->id,
        ]);

        $this->createRecord([
            'amount_type_id' => $this->expense->id,
        ]);

        // 指定した収支区分のみ取得されることを確認
        $response = $this->getJson(
            $this->endpoint->records() . '?amountTypeId=' . $this->expense->id
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data');

        $this->assertEquals($this->expense->id, $response->json('data.0.amountTypeId'));
    }


    /** FKI-008 正常: カテゴリフィルタ */
    #[Test]
    public function test_FKI008_index_filters_by_category(): void
    {
        // 異なるカテゴリのレコードを作成
        $this->createRecord([
            'kakeibo_default_category_id' => $this->category2->id,
        ]);

        $this->createRecord([
            'kakeibo_default_category_id' => $this->category1->id,
        ]);

        // 指定したカテゴリのみ取得されることを確認
        $response = $this->getJson(
            $this->endpoint->records() . '?categoryId=' . $this->category1->id
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data');

        $this->assertEquals($this->category1->id, $response->json('data.0.categoryId'));
    }


    /** FKI-009 正常: summary がフィルタ適用後の合計 */
    #[Test]
    public function test_FKI009_index_returns_correct_summary(): void
    {
        // 集計確認用の支出・収入レコードを作成
        $this->createRecord([
            'amount' => 5000,
            'amount_type_id' => $this->expense->id,
        ]);

        $this->createRecord([
            'amount' => 10000,
            'amount_type_id' => $this->income->id,
        ]);

        // summaryの集計結果が正しいことを確認
        $response = $this->getJson($this->endpoint->records());

        $response->assertStatus(Response::HTTP_OK)
            ->assertJson([
                'summary' => [
                    'totalExpense' => 5000,
                    'totalIncome' => 10000,
                ],
            ]);
    }


    /** FKI-010 正常: 他ユーザーのレコードが含まれない */
    #[Test]
    public function test_FKI010_index_excludes_other_users_records(): void
    {
        // 別ユーザーを作成
        $other = User::forceCreate([
            'login_id' => 'test002',
            'email' => 'other@example.com',
            'name' => 'other',
            'password_hash' => bcrypt('password'),
        ]);

        // ログインユーザーのレコードを作成
        $this->createRecords(3);

        // 他ユーザーが所有するレコードを作成
        KakeiboRecord::create([
            'user_id' => $other->id,
            'purchase_date' => '2026-07-01',
            'amount_type_id' => $this->expense->id,
            'amount' => 1000,
            'details' => 'other',
            'kakeibo_default_category_id' => $this->category1->id,
        ]);

        // 他ユーザーのレコードが一覧に含まれないことを確認
        $response = $this->getJson($this->endpoint->records());

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(3, 'data');

        $data = $response->json('data');
        foreach ($data as $record) {
            $this->assertEquals($this->user->id, $record['userId']);
        }
    }


    /** FKI-011 異常: 未認証 */
    #[Test]
    public function test_FKI011_index_requires_authentication(): void
    {
        // 認証状態を解除
        $this->app['auth']->forgetGuards();

        // 未認証ユーザーではアクセスできないことを確認
        $response = $this->getJson($this->endpoint->records());

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }


    /** FKI-012 異常: perPage 101 */
    #[Test]
    public function test_FKI012_index_rejects_perPage_over_100(): void
    {
        // perPageの上限値を超えた場合、バリデーションエラーになることを確認
        $response = $this->getJson($this->endpoint->records() . '?perPage=101');

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
