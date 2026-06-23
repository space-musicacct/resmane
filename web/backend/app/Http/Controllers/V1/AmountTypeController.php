<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\AmountType;
use Illuminate\Http\JsonResponse;

class AmountTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = AmountType::all()
            ->map(fn ($type) => [
                'id' => $type->id,
                'typeName' => $type->type_name,
            ]);

        return response()->json([
            'data' => $types,
        ]);
    }
}
