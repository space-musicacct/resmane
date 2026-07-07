<?php

namespace App\Services\V1;

use App\Models\KakeiboRecord;
use App\Repositories\V1\Contracts\KakeiboRecordRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * 家計簿レコードのCRUDに関するビジネスロジックを担当する
 */
readonly class KakeiboRecordService
{
    /**
     * @param KakeiboRecordRepositoryInterface $repository
     */
    public function __construct(
        private KakeiboRecordRepositoryInterface $repository,
    ) {}

    /**
     * ユーザーの家計簿一覧を取得する
     *
     * @param int $userId ユーザーID
     * @param string $sortOrder ソート順（'asc' または 'desc'）
     * @return array{records: LengthAwarePaginator, totalIncome: int, totalExpense: int}
     */
    public function list(int $userId, string $sortOrder): array
    {
        return [
            'records' => $this->repository->paginateByUserId($userId, $sortOrder),
            'totalIncome' => $this->repository->sumByType($userId, 2),
            'totalExpense' => $this->repository->sumByType($userId, 1),
        ];
    }

    /**
     * 家計簿レコードを取得し、所有権を検証する（読み取り専用）
     *
     * @param int $id 家計簿レコードID
     * @param int $userId 認証ユーザーID
     * @return KakeiboRecord
     */
    public function findOrFail(int $id, int $userId): KakeiboRecord
    {
        return $this->resolveRecord($this->repository->findById($id), $userId);
    }

    /**
     * 排他ロック付きで家計簿レコードを取得し、所有権を検証する
     *
     * トランザクション内での使用を想定する。
     *
     * @param int $id 家計簿レコードID
     * @param int $userId 認証ユーザーID
     * @return KakeiboRecord
     */
    public function findOrFailForUpdate(int $id, int $userId): KakeiboRecord
    {
        return $this->resolveRecord($this->repository->findByIdForUpdate($id), $userId);
    }

    /**
     * レコードの存在確認と所有権検証を行う
     *
     * @param KakeiboRecord|null $record
     * @param int $userId 認証ユーザーID
     * @return KakeiboRecord
     */
    private function resolveRecord(?KakeiboRecord $record, int $userId): KakeiboRecord
    {
        if (!$record) {
            abort(404, '指定された家計簿レコードが見つかりませんでした');
        }

        if ($record->user_id !== $userId) {
            abort(403, 'このレコードへのアクセス権限がありません');
        }

        return $record;
    }

    /**
     * 家計簿レコードを新規作成する
     *
     * @param int $userId ユーザーID
     * @param array $validated バリデーション済みリクエストデータ（camelCase）
     * @return KakeiboRecord リレーションをロード済み
     * @throws QueryException DB操作に失敗した場合
     */
    public function create(int $userId, array $validated): KakeiboRecord
    {
        return $this->repository->create([
            'user_id' => $userId,
            'purchase_date' => $validated['purchaseDate'] ?? now()->toDateString(),
            'amount_type_id' => $validated['amountTypeId'],
            'amount' => $validated['amount'],
            'details' => $validated['details'] ?? null,
            'kakeibo_default_category_id' => $validated['kakeiboDefaultCategoryId'],
        ]);
    }

    /**
     * 家計簿レコードを更新する
     *
     * 排他ロック付きトランザクション内で処理する。
     *
     * @param int $id 家計簿レコードID
     * @param int $userId 認証ユーザーID
     * @param array $validated バリデーション済みリクエストデータ（camelCase）
     * @return KakeiboRecord リレーションを再ロード済み
     * @throws QueryException DB操作に失敗した場合
     */
    public function update(int $id, int $userId, array $validated): KakeiboRecord
    {
        return DB::transaction(function () use ($id, $userId, $validated) {
            $record = $this->findOrFailForUpdate($id, $userId);

            return $this->repository->update($record, [
                'purchase_date' => $validated['purchaseDate'] ?? $record->purchase_date,
                'amount_type_id' => $validated['amountTypeId'] ?? $record->amount_type_id,
                'amount' => $validated['amount'] ?? $record->amount,
                'details' => array_key_exists('details', $validated) ? $validated['details'] : $record->details,
                'kakeibo_default_category_id' => $validated['kakeiboDefaultCategoryId'] ?? $record->kakeibo_default_category_id,
            ]);
        });
    }

    /**
     * 家計簿レコードを論理削除する
     *
     * 排他ロック付きトランザクション内で処理する。
     *
     * @param int $id 家計簿レコードID
     * @param int $userId 認証ユーザーID
     * @return void
     * @throws QueryException DB操作に失敗した場合
     */
    public function delete(int $id, int $userId): void
    {
        DB::transaction(function () use ($id, $userId) {
            $record = $this->findOrFailForUpdate($id, $userId);
            $this->repository->delete($record);
        });
    }
}
