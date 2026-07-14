<?php

namespace App\Repositories\V1\Contracts;

use App\Models\UpperLimitSetting;

/**
 * 基準値設定テーブルへのデータアクセスの契約
 */
interface UpperLimitSettingRepositoryInterface
{
    /**
     * ユーザーの基準値設定を取得する
     *
     * @param int $userId ユーザーID
     * @return UpperLimitSetting|null 未設定の場合はnull
     */
    public function findByUserId(int $userId): ?UpperLimitSetting;

    /**
     * 基準値設定を作成または更新する（upsert）
     *
     * @param int $userId ユーザーID
     * @param array $data 更新データ（snake_caseカラム名）
     * @return UpperLimitSetting upperLimitTypeリレーションをロード済み
     */
    public function upsert(int $userId, array $data): UpperLimitSetting;

    /**
     * ユーザーの基準値設定を論理削除する
     *
     * @param int $userId ユーザーID
     * @return void
     */
    public function deleteByUserId(int $userId): void;
}
