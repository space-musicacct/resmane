<?php

namespace App\Repositories\V1\Contracts;

use Illuminate\Support\Collection;

/**
 * 投稿（AIメッセージ）テーブルへのデータアクセスの契約
 */
interface PostRepositoryInterface
{
    /**
     * 指定の家計簿レコードIDに紐づく投稿を一括論理削除する
     *
     * @param Collection<int, int> $recordIds 家計簿レコードIDのコレクション
     * @return void
     */
    public function deleteByRecordIds(Collection $recordIds): void;
}
