<?php

namespace App\Repositories\V1;

use App\Models\Post;
use App\Repositories\V1\Contracts\PostRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * 投稿（AIメッセージ）テーブルへのデータアクセスを担当する
 */
class PostRepository implements PostRepositoryInterface
{
    /**
     * 家計簿レコードに紐づく投稿一覧を取得する
     *
     * @param  int  $recordId  家計簿レコードID
     * @return Collection<int, Post> aiStatusリレーションをロード済み
     */
    public function findByRecordId(int $recordId): Collection
    {
        return Post::with('aiStatus')
            ->where('kakeibo_record_id', $recordId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * 投稿を新規作成する
     *
     * @param  array  $data  作成データ（snake_caseカラム名）
     * @return Post aiStatusリレーションをロード済み
     */
    public function create(array $data): Post
    {
        $post = Post::create($data);
        $post->load('aiStatus');

        return $post;
    }

    /**
     * 指定の家計簿レコード内に投稿が存在するか確認する
     *
     * @param  int  $id  投稿ID
     * @param  int  $recordId  家計簿レコードID
     */
    public function existsByIdAndRecordId(int $id, int $recordId): bool
    {
        return Post::where('id', $id)
            ->where('kakeibo_record_id', $recordId)
            ->exists();
    }

    /**
     * 指定の家計簿レコードに紐づくAI投稿のうち、指定ステータスのものが存在するか確認する
     *
     * @param  int  $recordId  家計簿レコードID
     * @param  array<int>  $statusIds  確認対象のai_status_id
     */
    public function existsAiPostWithStatuses(int $recordId, array $statusIds): bool
    {
        return Post::where('kakeibo_record_id', $recordId)
            ->where('is_ai', 1)
            ->whereIn('ai_status_id', $statusIds)
            ->exists();
    }

    /**
     * 指定の家計簿レコードIDに紐づく投稿を一括論理削除する
     *
     * @param  Collection<int, int>  $recordIds  家計簿レコードIDのコレクション
     */
    public function deleteByRecordIds(Collection $recordIds): void
    {
        Post::whereIn('kakeibo_record_id', $recordIds)->delete();
    }
}
