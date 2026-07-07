<?php

namespace App\Services\V1;

use App\Models\SelfReview;
use App\Repositories\V1\Contracts\SelfReviewRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * 自己レビューのCRUDに関するビジネスロジックを担当する
 */
readonly class SelfReviewService
{
    /**
     * @param SelfReviewRepositoryInterface $repository
     * @param KakeiboRecordService $kakeiboRecordService 親レコードの所有権検証に使用
     */
    public function __construct(
        private SelfReviewRepositoryInterface $repository,
        private KakeiboRecordService $kakeiboRecordService,
    ) {}

    /**
     * 家計簿レコードに紐づく自己レビュー一覧を取得する
     *
     * @param int $recordId 家計簿レコードID
     * @param int $userId 認証ユーザーID
     * @return LengthAwarePaginator
     */
    public function list(int $recordId, int $userId): LengthAwarePaginator
    {
        $this->kakeiboRecordService->findOrFail($recordId, $userId);

        return $this->repository->paginateByRecordId($recordId);
    }

    /**
     * 自己レビューを新規投稿する
     *
     * 排他ロック付きトランザクション内で処理する。
     *
     * @param int $recordId 家計簿レコードID
     * @param int $userId 認証ユーザーID
     * @param array $validated バリデーション済みリクエストデータ（camelCase）
     * @return SelfReview
     * @throws QueryException DB操作に失敗した場合
     */
    public function create(int $recordId, int $userId, array $validated): SelfReview
    {
        return DB::transaction(function () use ($recordId, $userId, $validated) {
            $this->kakeiboRecordService->findOrFailForUpdate($recordId, $userId);

            return $this->repository->create([
                'kakeibo_record_id' => $recordId,
                'review_comment' => $validated['reviewComment'],
            ]);
        });
    }

    /**
     * 自己レビューを更新する
     *
     * 排他ロック付きトランザクション内で処理する。
     *
     * @param int $recordId 家計簿レコードID
     * @param int $id 自己レビューID
     * @param int $userId 認証ユーザーID
     * @param array $validated バリデーション済みリクエストデータ（camelCase）
     * @return SelfReview
     * @throws QueryException DB操作に失敗した場合
     */
    public function update(int $recordId, int $id, int $userId, array $validated): SelfReview
    {
        return DB::transaction(function () use ($recordId, $id, $userId, $validated) {
            $this->kakeiboRecordService->findOrFailForUpdate($recordId, $userId);

            $review = $this->repository->findByIdForUpdate($id, $recordId);

            if (!$review) {
                abort(404, '指定された自己レビューが見つかりません');
            }

            return $this->repository->update($review, [
                'review_comment' => $validated['reviewComment'],
            ]);
        });
    }

    /**
     * 自己レビューを論理削除する
     *
     * 排他ロック付きトランザクション内で処理する。
     *
     * @param int $recordId 家計簿レコードID
     * @param int $id 自己レビューID
     * @param int $userId 認証ユーザーID
     * @return void
     * @throws QueryException DB操作に失敗した場合
     */
    public function delete(int $recordId, int $id, int $userId): void
    {
        DB::transaction(function () use ($recordId, $id, $userId) {
            $this->kakeiboRecordService->findOrFailForUpdate($recordId, $userId);

            $review = $this->repository->findByIdForUpdate($id, $recordId);

            if (!$review) {
                abort(404, '指定された自己レビューが見つかりません');
            }

            $this->repository->delete($review);
        });
    }
}
