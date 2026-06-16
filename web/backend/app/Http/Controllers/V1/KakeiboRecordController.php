<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

class KakeiboRecordController extends Controller
{
    /**
     * @param FormRequest $request
     * @return JsonResponse
     */
    public function index(FormRequest $request): JsonResponse
    {
        return response()->json(['message' => 'success']);
    }

    /**
     * @param FormRequest $request
     * @return JsonResponse
     */
    public function store(FormRequest $request): JsonResponse
    {
        return response()->json(['message' => 'success'], 201);
    }

    /**
     * @param FormRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(FormRequest $request, int $id): JsonResponse
    {
        return response()->json(['message' => 'success']);
    }

    /**
     * @param FormRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(FormRequest $request, int $id): JsonResponse
    {
        return response()->json(['message' => 'success']);
    }

    /**
     * @param FormRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(FormRequest $request, int $id): JsonResponse
    {
        return response()->json(null, 204);
    }
}
