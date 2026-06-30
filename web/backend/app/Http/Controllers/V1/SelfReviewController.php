<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SelfReviewStoreRequest;
use App\Http\Requests\V1\SelfReviewUpdateRequest;
use App\Models\SelfReview;
use App\Models\KakeiboRecord;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

class SelfReviewController extends Controller
{
    /**
     * @param FormRequest $request
     * @param int $recordId
     * @return JsonResponse
     */
    public function index(Request $request, int $recordId): JsonResponse
    {
        $records = KakeiboRecord::where('id', $recordId)
            -> where('user_id', auth()->id())
            ->firstOrFail();

        $reviews = SelfReview::where('kakeibo_record_id', $records->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

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

    /**
     * @param FormRequest $request
     * @param int $recordId
     * @return JsonResponse
     */
    public function store(SelfReviewStoreRequest $request, int $recordId): JsonResponse
    {
        $record = KakeiboRecord::where('id', $recordId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $review = SelfReview::create([
            'kakeibo_record_id' => $record->id,
            'review_comment' => $request->input('review_comment'),
        ]);

        return response()->json([
            'data' => new SelfReviewResource($review),
        ], 201);
    }

    /**
     * @param int $recordId
     * @param int $id
     * @return JsonResponse
     */
    public function update(SelfReviewUpdateRequest $request, int $recordId, int $id): JsonResponse
    {
        $record = KakeiboRecord::where('id', $recordId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $review = SelfReview::where('id', $id)
            ->where('kakeibo_record_id', $record->id)
            ->firstOrFail();

        $validated = $request->validated();

        $review->update([
            'review_comment' => $validated['reviewComment'] ?? $review->review_comment,
        ]);

        return response()->json([
            'data' => new SelfReviewResource($review),
        ]);
    }

    /**
     * @param FormRequest $request
     * @param int $recordId
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(FormRequest $request, int $recordId, int $id): JsonResponse
    {
        $record = KakeiboRecord::where('id', $recordId)
            ->where('user_id', auth()->id())
            ->firstOrFail();
            
        $review = SelfReview::where('id', $id)
            ->where('kakeibo_record_id', $record->id)
            ->firstOrFail();

        $review->delete();

        return response()->noContent();
    }
}
