<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PostController extends Controller
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
        return response()->json(['message' => 'success'], Response::HTTP_CREATED);
    }
}
