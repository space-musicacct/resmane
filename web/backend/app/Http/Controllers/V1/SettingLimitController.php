<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateSettingLimitRequest;
use App\Http\Resources\V1\UpperLimitSettingResource;
use App\Models\UpperLimitSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingLimitController extends Controller
{
    /**
     * 基準値設定取得
     */
    public function show(Request $request): JsonResponse
    {
        $setting = UpperLimitSetting::with('upperLimitType')
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$setting) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => new UpperLimitSettingResource($setting)]);
    }

    /**
     * 基準値設定更新（upsert）
     */
    public function update(UpdateSettingLimitRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $setting = UpperLimitSetting::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'upper_limit_type_id' => $validated['upperLimitTypeId'],
                'max_value' => $validated['maxValue'],
                'ave_monthly_income' => $validated['aveMonthlyIncome'] ?? null,
            ]
        );

        $setting->load('upperLimitType');

        return response()->json(['data' => new UpperLimitSettingResource($setting)]);
    }
}
