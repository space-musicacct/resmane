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
     * 指定の家計簿レコードIDに紐づく投稿を一括論理削除する
     *
     * @param Collection<int, int> $recordIds 家計簿レコードIDのコレクション
     * @return void
     */
    public function deleteByRecordIds(Collection $recordIds): void
    {
        Post::whereIn('kakeibo_record_id', $recordIds)->delete();
    }
}
