<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function stats(DashboardFilterRequest $request): JsonResponse
    {
        $examId = $request->validated('exam_id');

        return response()->json([
            'data' => $this->dashboard->stats($examId ? (int) $examId : null),
        ]);
    }

    public function ranking(DashboardFilterRequest $request): AnonymousResourceCollection
    {
        $examId = $request->validated('exam_id');

        $paginator = $this->dashboard->ranking(
            $examId ? (int) $examId : null,
            (int) $request->validated('per_page', 10),
            (int) $request->validated('page', 1),
        );

        return JsonResource::collection($paginator);
    }
}
