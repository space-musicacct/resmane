<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\KakeiboRecord;

class KakeiboRecordController extends Controller
{
    //
    public function index(){
        $records = KakeiboRecord::with([
            'amountType',
            'category'
        ])->paginate(20);

        $data = $records->items();

        return response()->json([
            'data' => collect($data)->map(function ($record) {
                return [
                    'id' => $record->id,
                    'userId' => $record->user_id,
                    'purchaseDate' => $record->purchase_date,
                    'amountTypeId' => $record->amount_type_id,
                    'amountTypeName' => $record->amountType->name,
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
    public function store(KakeiboRecordStoreRequest $request){
        $record = KakeiboRecord::create([
            'user_id' => auth()->id(),
            'purchase_date' => $request->purchaseDate,
            'amount_type_id' => $request->amountTypeId,
            'amount' => $request->amount,
            'details' => $request->details,
            'kakeibo_default_category_id'
                => $request->kakeiboDefaultCategoryId,
        ]);

        return response()->json([
            'data' => [
                'id' => $record->id,
                'userId' => $record->user_id,
                'purchaseDate' => $record->purchase_date,
                'amountTypeId' => $record->amount_type_id,
                'amount' => $record->amount,
                'details' => $record->details,
                'kakeiboDefaultCategoryId'
                    => $record->kakeibo_default_category_id,
                'createdAt' => $record->created_at,
                'updatedAt' => $record->updated_at,
            ]
        ], 201);
    }
    public function show(int $id){
        $record = KakeiboRecord::with([
            'amountType',
            'category'
        ])->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $record->id,
                'userId' => $record->user_id,
                'purchaseDate' => $record->purchase_date,
                'amountTypeId' => $record->amount_type_id,
                'amountTypeName' => $record->amountType->name,
                'amount' => $record->amount,
                'details' => $record->details,
                'categoryId' => $record->kakeibo_default_category_id,
                'categoryName' => $record->category->category_name,
                'createdAt' => $record->created_at,
                'updatedAt' => $record->updated_at,
            ]
        ]);
    }
    public function update(KakeiboRecordStoreRequest $request, int $id){
        $record = KakeiboRecord::findOrFail($id);

        $record->update([
            'purchase_date' => $request->purchaseDate,
            'amount_type_id' => $request->amountTypeId,
            'amount' => $request->amount,
            'details' => $request->details,
            'kakeibo_default_category_id' => $request->kakeiboDefaultCategoryId,
        ]);

        return response()->json([
            'data' => [
                'id' => $record->id,
                'userId' => $record->user_id,
                'purchaseDate' => $record->purchase_date,
                'amountTypeId' => $record->amount_type_id,
                'amount' => $record->amount,
                'details' => $record->details,
                'categoryId' => $record->kakeibo_default_category_id,
                'updatedAt' => $record->updated_at,
            ]
        ]);
    }

    public function destroy(int $id){
        $record = KakeiboRecord::findOrFail($id);

        $record->delete();

        return response()->json([
            'message' => '削除しました'
        ], 200);
    }
}
