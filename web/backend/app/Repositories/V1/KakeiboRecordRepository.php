<?php

namespace App\Repositories\V1;

use App\Models\KakeiboRecord;
use App\Repositories\V1\Contracts\KakeiboRecordRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * 家計簿レコードテーブルへのデータアクセスを担当する
 */
class KakeiboRecordRepository implements KakeiboRecordRepositoryInterface
{
    /**
     * 家計簿レコードを取得する
     *
     * @param  int  $id  家計簿レコードID
     */
    public function findById(int $id): ?KakeiboRecord
    {
        return KakeiboRecord::find($id);
    }

    /**
     * 排他ロック付きで家計簿レコードを取得する
     *
     * @param  int  $id  家計簿レコードID
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
     * @param  int  $userId  ユーザーID
     * @param  string  $sortOrder  ソート順（'asc' または 'desc'）
     * @param  array  $filters  絞り込み条件（from, to, amountTypeId, categoryId）
     * @param  int  $perPage  1ページあたりの件数
     */
    public function paginateByUserId(int $userId, string $sortOrder, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = KakeiboRecord::where('user_id', $userId);
        $this->applyFilters($query, $filters);

        return $query
            ->with(['amountType', 'category'])
            ->orderBy('purchase_date', $sortOrder)
            ->orderBy('id', $sortOrder)
            ->paginate($perPage);
    }

    /**
     * ユーザーの指定収支区分の合計金額を取得する
     *
     * @param  int  $userId  ユーザーID
     * @param  int  $amountTypeId  収支区分ID（1: 支出, 2: 収入）
     * @param  array  $filters  絞り込み条件（from, to, amountTypeId, categoryId）
     */
    public function sumByType(int $userId, int $amountTypeId, array $filters = []): int
    {
        $query = KakeiboRecord::where('user_id', $userId)
            ->where('amount_type_id', $amountTypeId);
        $this->applyFilters($query, $filters);

        return (int) $query->sum('amount');
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['from'])) {
            $query->where('purchase_date', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->where('purchase_date', '<=', $filters['to']);
        }
        if (isset($filters['amountTypeId'])) {
            $query->where('amount_type_id', $filters['amountTypeId']);
        }
        if (isset($filters['categoryId'])) {
            $query->where('kakeibo_default_category_id', $filters['categoryId']);
        }
    }

    /**
     * 家計簿レコードを新規作成する
     *
     * @param  array  $data  作成データ（snake_caseカラム名）
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
     * @param  KakeiboRecord  $record  更新対象のレコード
     * @param  array  $data  更新データ（snake_caseカラム名）
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
     * @param  KakeiboRecord  $record  削除対象のレコード
     */
    public function delete(KakeiboRecord $record): void
    {
        $record->delete();
    }

    /**
     * ユーザーに紐づく家計簿レコードのID一覧を取得する
     *
     * @param  int  $userId  ユーザーID
     * @return Collection<int, int>
     */
    public function pluckIdsByUserId(int $userId): Collection
    {
        return KakeiboRecord::where('user_id', $userId)->pluck('id');
    }

    /**
     * 指定IDの家計簿レコードを一括論理削除する
     *
     * @param  Collection<int, int>  $ids  削除対象のレコードID
     */
    public function deleteByIds(Collection $ids): void
    {
        KakeiboRecord::whereIn('id', $ids)->delete();
    }
}
