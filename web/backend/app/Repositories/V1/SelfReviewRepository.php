<?php

namespace App\Repositories\V1;

use App\Models\SelfReview;
use App\Repositories\V1\Contracts\SelfReviewRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * 自己レビューテーブルへのデータアクセスを担当する
 */
class SelfReviewRepository implements SelfReviewRepositoryInterface
{
    /**
     * 排他ロック付きで自己レビューを取得する
     *
     * @param int $id 自己レビューID
     * @param int $recordId 紐づく家計簿レコードID
     * @return SelfReview|null
     */
    public function findByIdForUpdate(int $id, int $recordId): ?SelfReview
    {
        return SelfReview::where('id', $id)
            ->where('kakeibo_record_id', $recordId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * 家計簿レコードに紐づく自己レビューをページネーション付きで取得する
     *
     * @param int $recordId 家計簿レコードID
     * @return LengthAwarePaginator
     */
    public function paginateByRecordId(int $recordId): LengthAwarePaginator
    {
        return SelfReview::where('kakeibo_record_id', $recordId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    /**
     * 自己レビューを新規作成する
     *
     * @param array $data 作成データ（snake_caseカラム名）
     * @return SelfReview
     */
    public function create(array $data): SelfReview
    {
        return SelfReview::create($data);
    }

    /**
     * 自己レビューを更新する
     *
     * @param SelfReview $review 更新対象のレビュー
     * @param array $data 更新データ（snake_caseカラム名）
     * @return SelfReview
     */
    public function update(SelfReview $review, array $data): SelfReview
    {
        $review->update($data);

        return $review;
    }

    /**
     * 自己レビューを論理削除する
     *
     * @param SelfReview $review 削除対象のレビュー
     * @return void
     */
    public function delete(SelfReview $review): void
    {
        $review->delete();
    }

    /**
     * 指定の家計簿レコードIDに紐づく自己レビューを一括論理削除する
     *
     * @param Collection<int, int> $recordIds 家計簿レコードIDのコレクション
     * @return void
     */
    public function deleteByRecordIds(Collection $recordIds): void
    {
        SelfReview::whereIn('kakeibo_record_id', $recordIds)->delete();
    }
}
