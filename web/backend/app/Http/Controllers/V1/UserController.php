<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /**
     * @param FormRequest $request
     * @return JsonResponse
     */
    public function show(FormRequest $request): JsonResponse
    {
        return response()->json(['message' => 'success']);
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
