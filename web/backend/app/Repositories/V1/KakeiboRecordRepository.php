<?php

namespace App\Repositories\V1;

use App\Models\KakeiboRecord;
use App\Repositories\V1\Contracts\KakeiboRecordRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * 家計簿レコードテーブルへのデータアクセスを担当する
 */
class KakeiboRecordRepository implements KakeiboRecordRepositoryInterface
{
    /**
     * 家計簿レコードを取得する
     *
     * @param int $id 家計簿レコードID
     * @return KakeiboRecord|null
     */
    public function findById(int $id): ?KakeiboRecord
    {
        return KakeiboRecord::find($id);
    }

    /**
     * 排他ロック付きで家計簿レコードを取得する
     *
     * @param int $id 家計簿レコードID
     * @return KakeiboRecord|null
     */
    public function findByIdForUpdate(int $id): ?KakeiboRecord
    {
        return KakeiboRecord::where('id', $id)
            ->lockForUpdate()
            ->first();
    }

    /**
     * ユーザーの家計簿レコードをページネーション付きで取得する
     *
     * @param int $userId ユーザーID
     * @param string $sortOrder ソート順（'asc' または 'desc'）
     * @return LengthAwarePaginator
     */
    public function paginateByUserId(int $userId, string $sortOrder): LengthAwarePaginator
    {
        return KakeiboRecord::where('user_id', $userId)
            ->with(['amountType', 'category'])
            ->orderBy('purchase_date', $sortOrder)
            ->orderBy('id', $sortOrder)
            ->paginate(20);
    }

    /**
     * ユーザーの指定収支区分の合計金額を取得する
     *
     * @param int $userId ユーザーID
     * @param int $amountTypeId 収支区分ID（1: 支出, 2: 収入）
     * @return int
     */
    public function sumByType(int $userId, int $amountTypeId): int
    {
        return (int) KakeiboRecord::where('user_id', $userId)
            ->where('amount_type_id', $amountTypeId)
            ->sum('amount');
    }

    /**
     * 家計簿レコードを新規作成する
     *
     * @param array $data 作成データ（snake_caseカラム名）
     * @return KakeiboRecord リレーション（amountType, category）をロード済み
     */
    public function create(array $data): KakeiboRecord
    {
        $record = KakeiboRecord::create($data);
        $record->load(['amountType', 'category']);

        return $record;
    }

    /**
     * 家計簿レコードを更新する
     *
     * @param KakeiboRecord $record 更新対象のレコード
     * @param array $data 更新データ（snake_caseカラム名）
     * @return KakeiboRecord リレーション（amountType, category）を再ロード済み
     */
    public function update(KakeiboRecord $record, array $data): KakeiboRecord
    {
        $record->update($data);
        $record->load(['amountType', 'category']);

        return $record;
    }

    /**
     * 家計簿レコードを論理削除する
     *
     * @param KakeiboRecord $record 削除対象のレコード
     * @return void
     */
    public function delete(KakeiboRecord $record): void
    {
        $record->delete();
    }

    /**
     * ユーザーに紐づく家計簿レコードのID一覧を取得する
     *
     * @param int $userId ユーザーID
     * @return Collection<int, int>
     */
    public function pluckIdsByUserId(int $userId): Collection
    {
        return KakeiboRecord::where('user_id', $userId)->pluck('id');
    }

    /**
     * 指定IDの家計簿レコードを一括論理削除する
     *
     * @param Collection<int, int> $ids 削除対象のレコードID
     * @return void
     */
    public function deleteByIds(Collection $ids): void
    {
        KakeiboRecord::whereIn('id', $ids)->delete();
    }
}
