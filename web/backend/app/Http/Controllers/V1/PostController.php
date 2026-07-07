<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\PostStoreRequest;
use App\Http\Resources\V1\PostResource;
use App\Services\V1\PostService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * スレッド・AIメッセージ API コントローラー
 *
 * 2026/7/7 現在、worker 未実装のため、実際の動作チェックおよび結合テストは不可。
 * 現時点ではAPI設計書に基づく実装をしている。
 * TODO: worker の実装 (担当: 小川)
 */
class PostController extends Controller
{
    public function __construct(
        private readonly PostService $service,
    ) {}

    public function index(int $recordId): JsonResponse
    {
        $posts = $this->service->list($recordId, auth()->id());

        return response()->json([
            'data' => PostResource::collection($posts),
        ]);
    }

    public function store(PostStoreRequest $request, int $recordId): JsonResponse
    {
        $result = $this->service->store($recordId, auth()->id(), $request->validated());

        if (isset($result['error'])) {
            return response()->json([
                'message' => $result['error'],
                'errors' => (object) [],
            ], $result['status']);
        }

        return response()->json([
            'data' => [
                'userPost' => $result['userPost'] ? new PostResource($result['userPost']) : null,
                'aiPost' => new PostResource($result['aiPost']),
            ],
        ], Response::HTTP_CREATED);
    }
}
