<?php

namespace App\Repositories\V1\Contracts;

use App\Models\SelfReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * 自己レビューテーブルへのデータアクセスの契約
 */
interface SelfReviewRepositoryInterface
{
    /**
     * 排他ロック付きで自己レビューを取得する
     *
     * @param  int  $id  自己レビューID
     * @param  int  $recordId  紐づく家計簿レコードID
     */
    public function findByIdForUpdate(int $id, int $recordId): ?SelfReview;

    /**
     * 家計簿レコードに紐づく自己レビューをページネーション付きで取得する
     *
     * @param  int  $recordId  家計簿レコードID
     */
    public function paginateByRecordId(int $recordId): LengthAwarePaginator;

    /**
     * 自己レビューを新規作成する
     *
     * @param  array  $data  作成データ（snake_caseカラム名）
     */
    public function create(array $data): SelfReview;

    /**
     * 自己レビューを更新する
     *
     * @param  SelfReview  $review  更新対象のレビュー
     * @param  array  $data  更新データ（snake_caseカラム名）
     */
    public function update(SelfReview $review, array $data): SelfReview;

    /**
     * 自己レビューを論理削除する
     *
     * @param  SelfReview  $review  削除対象のレビュー
     */
    public function delete(SelfReview $review): void;

    /**
     * 指定の家計簿レコードIDに紐づく自己レビューを一括論理削除する
     *
     * @param  Collection<int, int>  $recordIds  家計簿レコードIDのコレクション
     */
    public function deleteByRecordIds(Collection $recordIds): void;
}
