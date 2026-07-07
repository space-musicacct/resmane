<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\KakeiboRecordIndexRequest;
use App\Http\Requests\V1\KakeiboRecordStoreRequest;
use App\Http\Requests\V1\KakeiboRecordUpdateRequest;
use App\Http\Resources\V1\KakeiboRecordResource;
use App\Services\V1\KakeiboRecordService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class KakeiboRecordController extends Controller
{
    public function __construct(
        private readonly KakeiboRecordService $service,
    ) {}

    public function index(KakeiboRecordIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sortOrder = $request->input('sort', 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = (int) ($validated['perPage'] ?? 20);

        $filters = array_filter([
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'amountTypeId' => $validated['amountTypeId'] ?? null,
            'categoryId' => $validated['categoryId'] ?? null,
        ], fn ($v) => $v !== null);

        $result = $this->service->list(auth()->id(), $sortOrder, $filters, $perPage);
        $records = $result['records'];

        return response()->json([
            'data' => KakeiboRecordResource::collection($records->items()),
            'meta' => [
                'currentPage' => $records->currentPage(),
                'lastPage' => $records->lastPage(),
                'perPage' => $records->perPage(),
                'total' => $records->total(),
            ],
            'summary' => [
                'totalIncome' => $result['totalIncome'],
                'totalExpense' => $result['totalExpense'],
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id, auth()->id());

        $record->load(['amountType', 'category']);

        return response()->json([
            'data' => new KakeiboRecordResource($record),
        ]);
    }

    public function store(KakeiboRecordStoreRequest $request): JsonResponse
    {
        $record = $this->service->create(auth()->id(), $request->validated());

        return response()->json([
            'data' => new KakeiboRecordResource($record),
        ], Response::HTTP_CREATED);
    }

    public function update(KakeiboRecordUpdateRequest $request, int $id): JsonResponse
    {
        $record = $this->service->update($id, auth()->id(), $request->validated());

        return response()->json([
            'data' => new KakeiboRecordResource($record),
        ]);
    }

    public function destroy(int $id): Response
    {
        $this->service->delete($id, auth()->id());

        return response()->noContent();
    }
}
