<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\KakeiboRecordStoreRequest;
use App\Http\Requests\V1\KakeiboRecordUpdateRequest;
use App\Http\Resources\V1\KakeiboRecordResource;
use App\Models\KakeiboRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class KakeiboRecordController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = KakeiboRecord::where('user_id', auth()->id());

        $sortOrder = $request->input('sort', 'desc') === 'asc' ? 'asc' : 'desc';

        $records = (clone $query)
            ->with(['amountType', 'category'])
            ->orderBy('purchase_date', $sortOrder)
            ->orderBy('id', $sortOrder)
            ->paginate(20);

        $totalIncome = (clone $query)->where('amount_type_id', 2)->sum('amount');
        $totalExpense = (clone $query)->where('amount_type_id', 1)->sum('amount');

        return response()->json([
            'data' => KakeiboRecordResource::collection($records->items()),
            'meta' => [
                'currentPage' => $records->currentPage(),
                'lastPage' => $records->lastPage(),
                'perPage' => $records->perPage(),
                'total' => $records->total(),
            ],
            'summary' => [
                'totalIncome' => (int) $totalIncome,
                'totalExpense' => (int) $totalExpense,
            ],
        ]);
    }

    /**
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $record = KakeiboRecord::where('user_id', auth()->id())
            ->with(['amountType', 'category'])
            ->findOrFail($id);

        return response()->json([
            'data' => new KakeiboRecordResource($record),
        ]);
    }

    /**
     * @param KakeiboRecordStoreRequest $request
     * @return JsonResponse
     */
    public function store(KakeiboRecordStoreRequest $request): JsonResponse
    {
        $record = KakeiboRecord::create([
            'user_id' => auth()->id(),
            'purchase_date' => $request->purchaseDate ?? now()->toDateString(),
            'amount_type_id' => $request->amountTypeId,
            'amount' => $request->amount,
            'details' => $request->details,
            'kakeibo_default_category_id' => $request->kakeiboDefaultCategoryId,
        ]);

        $record->load(['amountType', 'category']);

        return response()->json([
            'data' => new KakeiboRecordResource($record),
        ], 201);
    }

    /**
     * @param KakeiboRecordUpdateRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(KakeiboRecordUpdateRequest $request, int $id): JsonResponse
    {
        $record = KakeiboRecord::where('user_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validated();

        $record->update([
            'purchase_date' => $validated['purchaseDate'] ?? $record->purchase_date,
            'amount_type_id' => $validated['amountTypeId'] ?? $record->amount_type_id,
            'amount' => $validated['amount'] ?? $record->amount,
            'details' => array_key_exists('details', $validated) ? $validated['details'] : $record->details,
            'kakeibo_default_category_id' => $validated['kakeiboDefaultCategoryId'] ?? $record->kakeibo_default_category_id,
        ]);

        $record->load(['amountType', 'category']);

        return response()->json([
            'data' => new KakeiboRecordResource($record),
        ]);
    }

    /**
     * @param int $id
     * @return Response
     */
    public function destroy(int $id): Response
    {
        $record = KakeiboRecord::where('user_id', auth()->id())
            ->findOrFail($id);

        $record->delete();

        return response()->noContent();
    }
}
