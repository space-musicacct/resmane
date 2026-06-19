<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

class SelfReviewController extends Controller
{
    /**
     * @param FormRequest $request
     * @param int $recordId
     * @return JsonResponse
     */
    public function index(FormRequest $request, int $recordId): JsonResponse
    {
        return response()->json(['message' => 'success']);
    }

    /**
     * @param FormRequest $request
     * @param int $recordId
     * @return JsonResponse
     */
    public function store(FormRequest $request, int $recordId): JsonResponse
    {
        return response()->json(['message' => 'success'], 201);
    }

    /**
     * @param FormRequest $request
     * @param int $recordId
     * @param int $id
     * @return JsonResponse
     */
    public function update(FormRequest $request, int $recordId, int $id): JsonResponse
    {
        return response()->json(['message' => 'success']);
    }

    /**
     * @param FormRequest $request
     * @param int $recordId
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(FormRequest $request, int $recordId, int $id): JsonResponse
    {
        return response()->json(null, 204);
    }
}
