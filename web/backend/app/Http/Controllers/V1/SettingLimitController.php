<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateSettingLimitRequest;
use App\Http\Resources\V1\UpperLimitSettingResource;
use App\Services\V1\SettingLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingLimitController extends Controller
{
    public function __construct(
        private readonly SettingLimitService $service,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $setting = $this->service->find($request->user()->id);

        if (!$setting) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => new UpperLimitSettingResource($setting)]);
    }

    public function update(UpdateSettingLimitRequest $request): JsonResponse
    {
        $setting = $this->service->upsert($request->user()->id, $request->validated());

        return response()->json(['data' => new UpperLimitSettingResource($setting)]);
    }
}
