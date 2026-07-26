<?php

namespace App\Services\V1;

use App\Models\UpperLimitSetting;
use App\Repositories\V1\Contracts\UpperLimitSettingRepositoryInterface;
use Illuminate\Database\QueryException;

/**
 * 基準値設定の取得・更新に関するビジネスロジックを担当する
 */
readonly class SettingLimitService
{
    public function __construct(
        private UpperLimitSettingRepositoryInterface $repository,
    ) {}

    /**
     * ユーザーの基準値設定を取得する
     *
     * @param  int  $userId  ユーザーID
     * @return UpperLimitSetting|null 未設定の場合はnull
     */
    public function find(int $userId): ?UpperLimitSetting
    {
        return $this->repository->findByUserId($userId);
    }

    /**
     * 基準値設定を作成または更新する
     *
     * @param  int  $userId  ユーザーID
     * @param  array  $validated  バリデーション済みリクエストデータ（camelCase）
     * @return UpperLimitSetting upperLimitTypeリレーションをロード済み
     *
     * @throws QueryException DB操作に失敗した場合
     */
    public function upsert(int $userId, array $validated): UpperLimitSetting
    {
        return $this->repository->upsert($userId, [
            'upper_limit_type_id' => $validated['upperLimitTypeId'],
            'max_value' => $validated['maxValue'],
            'ave_monthly_income' => $validated['aveMonthlyIncome'] ?? null,
        ]);
    }
}
