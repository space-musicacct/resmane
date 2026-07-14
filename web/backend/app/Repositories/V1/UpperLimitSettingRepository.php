<?php

namespace App\Repositories\V1;

use App\Models\UpperLimitSetting;
use App\Repositories\V1\Contracts\UpperLimitSettingRepositoryInterface;

/**
 * 基準値設定テーブルへのデータアクセスを担当する
 */
class UpperLimitSettingRepository implements UpperLimitSettingRepositoryInterface
{
    /**
     * ユーザーの基準値設定を取得する
     *
     * @param int $userId ユーザーID
     * @return UpperLimitSetting|null 未設定の場合はnull
     */
    public function findByUserId(int $userId): ?UpperLimitSetting
    {
        /** @var UpperLimitSetting|null */
        return UpperLimitSetting::with('upperLimitType')
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * 基準値設定を作成または更新する（upsert）
     *
     * @param int $userId ユーザーID
     * @param array $data 更新データ（snake_caseカラム名）
     * @return UpperLimitSetting upperLimitTypeリレーションをロード済み
     */
    public function upsert(int $userId, array $data): UpperLimitSetting
    {
        $setting = UpperLimitSetting::updateOrCreate(
            ['user_id' => $userId],
            $data,
        );

        $setting->load('upperLimitType');

        return $setting;
    }

    /**
     * ユーザーの基準値設定を論理削除する
     *
     * @param int $userId ユーザーID
     * @return void
     */
    public function deleteByUserId(int $userId): void
    {
        UpperLimitSetting::where('user_id', $userId)->delete();
    }
}
