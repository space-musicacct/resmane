<?php

namespace App\Services\V1;

use App\Models\AiStatus;
use App\Models\Post;
use App\Repositories\V1\Contracts\PostRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * スレッド（投稿・AIメッセージ）に関するビジネスロジックを担当する
 */
readonly class PostService
{
    /**
     * @param PostRepositoryInterface $repository
     * @param KakeiboRecordService $kakeiboRecordService 親レコードの所有権検証に使用
     */
    public function __construct(
        private PostRepositoryInterface $repository,
        private KakeiboRecordService $kakeiboRecordService,
    ) {}

    /**
     * 家計簿レコードに紐づく投稿一覧を取得する
     *
     * @param int $recordId 家計簿レコードID
     * @param int $userId 認証ユーザーID
     * @return Collection<int, Post>
     */
    public function list(int $recordId, int $userId): Collection
    {
        $this->kakeiboRecordService->findOrFail($recordId, $userId);

        return $this->repository->findByRecordId($recordId);
    }

    /**
     * ユーザー投稿の保存とAI返信用レコードの作成を行う
     *
     * content省略時はAIフィードバック生成要求のみ（F-008）。
     * content指定時はユーザー投稿 + AI返信用レコードの両方を作成（F-011）。
     * 排他ロック付きトランザクション内で処理する。
     *
     * @param int $recordId 家計簿レコードID
     * @param int $userId 認証ユーザーID
     * @param array $validated バリデーション済みリクエストデータ（camelCase）
     * @return array{userPost: Post|null, aiPost: Post}
     * @throws QueryException DB操作に失敗した場合
     * @throws Throwable トランザクション内で例外が発生した場合
     */
    public function store(int $recordId, int $userId, array $validated): array
    {
        return DB::transaction(function () use ($recordId, $userId, $validated) {
            $this->kakeiboRecordService->findOrFailForUpdate($recordId, $userId);

            $content = $validated['content'] ?? null;
            $parentId = $validated['parentId'] ?? null;
            $userPost = null;

            if ($parentId !== null && !$this->repository->existsByIdAndRecordId($parentId, $recordId)) {
                abort(Response::HTTP_NOT_FOUND, 'リプライ先の投稿が見つかりません');
            }

            if ($content === null) {
                $hasExisting = $this->repository->existsAiPostWithStatuses($recordId, [
                    AiStatus::PENDING_ID,
                    AiStatus::PROCESSING_ID,
                    AiStatus::COMPLETED_ID,
                ]);

                if ($hasExisting) {
                    return [
                        'error' => 'AIフィードバックは既に生成中または生成済みです',
                        'status' => Response::HTTP_CONFLICT,
                    ];
                }
            } else {
                $userPost = $this->repository->create([
                    'user_id' => $userId,
                    'kakeibo_record_id' => $recordId,
                    'ai_status_id' => null,
                    'parent_id' => $parentId,
                    'is_ai' => 0,
                    'content' => $content,
                ]);
                $parentId = $userPost->id;
            }

            $aiPost = $this->repository->create([
                'user_id' => $userId,
                'kakeibo_record_id' => $recordId,
                'ai_status_id' => AiStatus::PENDING_ID,
                'parent_id' => $parentId,
                'is_ai' => 1,
                'content' => null,
            ]);

            return ['userPost' => $userPost, 'aiPost' => $aiPost];
        });
    }
}
