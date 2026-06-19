<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * ログインユーザー情報取得
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    /**
     * @param FormRequest $request
     * @return JsonResponse
     */
    public function update(FormRequest $request): JsonResponse
    {
        return response()->json(['message' => 'success']);
    }

    /**
     * @param FormRequest $request
     * @return JsonResponse
     */
    public function destroy(FormRequest $request): JsonResponse
    {
        return response()->json(null, 204);
    }
}
