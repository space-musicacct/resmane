<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SelfReviewStoreRequest;
use App\Http\Requests\V1\SelfReviewUpdateRequest;
use App\Http\Resources\V1\SelfReviewResource;
use App\Services\V1\SelfReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SelfReviewController extends Controller
{
    public function __construct(
        private readonly SelfReviewService $service,
    ) {}

    public function index(int $recordId): JsonResponse
    {
        $reviews = $this->service->list($recordId, auth()->id());

        return response()->json([
            'data' => SelfReviewResource::collection($reviews->items()),
            'meta' => [
                'currentPage' => $reviews->currentPage(),
                'lastPage' => $reviews->lastPage(),
                'perPage' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function store(SelfReviewStoreRequest $request, int $recordId): JsonResponse
    {
        $review = $this->service->create($recordId, auth()->id(), $request->validated());

        return response()->json([
            'data' => new SelfReviewResource($review),
        ], Response::HTTP_CREATED);
    }

    public function update(SelfReviewUpdateRequest $request, int $recordId, int $id): JsonResponse
    {
        $review = $this->service->update($recordId, $id, auth()->id(), $request->validated());

        return response()->json([
            'data' => new SelfReviewResource($review),
        ]);
    }

    public function destroy(int $recordId, int $id): Response
    {
        $this->service->delete($recordId, $id, auth()->id());

        return response()->noContent();
    }
}
