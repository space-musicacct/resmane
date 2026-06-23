<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\KakeiboDefaultCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = KakeiboDefaultCategory::query();

        if ($request->filled('amountTypeId')) {
            $query->where('amount_type_id', $request->input('amountTypeId'));
        }

        $categories = $query->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'amountTypeId' => $cat->amount_type_id,
                'categoryName' => $cat->category_name,
            ]);

        return response()->json([
            'data' => $categories,
        ]);
    }
}
