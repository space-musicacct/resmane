<?php

namespace Tests\Feature\KakeiboRecord;

use App\Models\AmountType;
use App\Models\KakeiboDefaultCategory;
use App\Models\KakeiboRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

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


    public function test_FKI001_正常一覧取得(): void
    {
        // 一覧取得対象となる複数レコードを作成
        $this->createRecords(5);

        $response = $this->getJson('/api/v1/records');

        // 一覧データ、メタ情報、集計情報が返却されることを確認
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data',
                'meta',
                'summary',
            ]);
    }


    public function test_FKI002_正常レコード0件(): void
    {
        // レコードが存在しない場合のレスポンスを確認
        $response = $this->getJson('/api/v1/records');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
                'meta' => [
                    'total' => 0,
                ],
            ]);
    }


    public function test_FKI003_正常ページネーション(): void
    {
        // ページング確認用に上限を超えるレコードを作成
        $this->createRecords(25);

        $response = $this->getJson('/api/v1/records?perPage=20&page=2');

        // 2ページ目の取得結果を確認
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJson([
                'meta' => [
                    'currentPage' => 2,
                ],
            ]);
    }


    public function test_FKI004_正常新しい順(): void
    {
        // 日付が異なるレコードを作成
        $this->createRecord([
            'purchase_date' => '2026-07-01',
        ]);

        $this->createRecord([
            'purchase_date' => '2026-07-10',
        ]);

        // 新しい日付順で取得できることを確認
        $response = $this->getJson('/api/v1/records?sort=desc');

        $response->assertStatus(200);

        $this->assertEquals(
            '2026-07-10',
            $response->json('data.0.purchaseDate')
        );
    }


    public function test_FKI005_正常古い順(): void
    {
        // 日付が異なるレコードを作成
        $this->createRecord([
            'purchase_date' => '2026-07-01',
        ]);

        $this->createRecord([
            'purchase_date' => '2026-07-10',
        ]);

        // 古い日付順で取得できることを確認
        $response = $this->getJson('/api/v1/records?sort=asc');

        $response->assertStatus(200);

        $this->assertEquals(
            '2026-07-01',
            $response->json('data.0.purchaseDate')
        );
    }


    public function test_FKI006_正常期間フィルタ(): void
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
            '/api/v1/records?from=2026-07-01&to=2026-07-31'
        );

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
    public function test_FKI007_正常収支区分フィルタ(): void
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
            '/api/v1/records?amountTypeId=' . $this->expense->id
        );

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }


    public function test_FKI008_正常カテゴリフィルタ(): void
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
            '/api/v1/records?categoryId=' . $this->category1->id
        );

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }


    public function test_FKI009_summary確認(): void
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
        $response = $this->getJson('/api/v1/records');

        $response->assertStatus(200)
            ->assertJson([
                'summary' => [
                    'totalExpense' => 5000,
                    'totalIncome' => 10000,
                ],
            ]);
    }


    public function test_FKI010_他ユーザー除外(): void
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
        $response = $this->getJson('/api/v1/records');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }


    public function test_FKI011_未認証(): void
    {
        // 認証状態を解除
        $this->app['auth']->forgetGuards();

        // 未認証ユーザーではアクセスできないことを確認
        $response = $this->getJson('/api/v1/records');

        $response->assertStatus(401);
    }


    public function test_FKI012_perPage101(): void
    {
        // perPageの上限値を超えた場合、バリデーションエラーになることを確認
        $response = $this->getJson('/api/v1/records?perPage=101');

        $response->assertStatus(422);
    }
}
