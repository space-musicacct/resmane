<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\KakeiboRecordStoreRequest;
use App\Http\Requests\KakeiboRecordUpdateRequest;
use App\Models\KakeiboRecord;
use Illuminate\Http\JsonResponse;

class KakeiboRecordController extends Controller
{
    public function index(): JsonResponse
    {
        $records = KakeiboRecord::where('user_id', auth()->id())
            ->with(['amountType', 'category'])
            ->paginate(20);

        return response()->json([
            'data' => collect($records->items())->map(function ($record) {
                return [
                    'id' => $record->id,
                    'userId' => $record->user_id,
                    'purchaseDate' => $record->purchase_date,
                    'amountTypeId' => $record->amount_type_id,
                    'amountTypeName' => $record->amountType->type_name,
                    'amount' => $record->amount,
                    'details' => $record->details,
                    'categoryId' => $record->kakeibo_default_category_id,
                    'categoryName' => $record->category->category_name,
                    'createdAt' => $record->created_at,
                    'updatedAt' => $record->updated_at,
                ];
            }),
            'meta' => [
                'currentPage' => $records->currentPage(),
                'lastPage' => $records->lastPage(),
                'perPage' => $records->perPage(),
                'total' => $records->total(),
            ]
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $record = KakeiboRecord::where('user_id', auth()->id())
            ->with(['amountType', 'category'])
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $record->id,
                'userId' => $record->user_id,
                'purchaseDate' => $record->purchase_date,
                'amountTypeId' => $record->amount_type_id,
                'amountTypeName' => $record->amountType->type_name,
                'amount' => $record->amount,
                'details' => $record->details,
                'categoryId' => $record->kakeibo_default_category_id,
                'categoryName' => $record->category->category_name,
                'createdAt' => $record->created_at,
                'updatedAt' => $record->updated_at,
            ]
        ]);
    }

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
            'data' => [
                'id' => $record->id,
                'userId' => $record->user_id,
                'purchaseDate' => $record->purchase_date,
                'amountTypeId' => $record->amount_type_id,
                'amountTypeName' => $record->amountType->type_name,
                'amount' => $record->amount,
                'details' => $record->details,
                'categoryId' => $record->kakeibo_default_category_id,
                'categoryName' => $record->category->category_name,
                'createdAt' => $record->created_at,
                'updatedAt' => $record->updated_at,
            ]
        ], 201);
    }

    public function update(
        KakeiboRecordUpdateRequest $request,
        int $id
    ): JsonResponse {
        $record = KakeiboRecord::where('user_id', auth()->id())
            ->findOrFail($id);

        $record->update([
            'purchase_date' => $request->purchaseDate,
            'amount_type_id' => $request->amountTypeId,
            'amount' => $request->amount,
            'details' => $request->details,
            'kakeibo_default_category_id' => $request->kakeiboDefaultCategoryId,
        ]);

        $record->load(['amountType', 'category']);

        return response()->json([
            'data' => [
                'id' => $record->id,
                'userId' => $record->user_id,
                'purchaseDate' => $record->purchase_date,
                'amountTypeId' => $record->amount_type_id,
                'amountTypeName' => $record->amountType->type_name,
                'amount' => $record->amount,
                'details' => $record->details,
                'categoryId' => $record->kakeibo_default_category_id,
                'categoryName' => $record->category->category_name,
                'createdAt' => $record->created_at,
                'updatedAt' => $record->updated_at,
            ]
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = KakeiboRecord::where('user_id', auth()->id())
            ->findOrFail($id);

        $record->delete();

        return response()->noContent();
    }
}
