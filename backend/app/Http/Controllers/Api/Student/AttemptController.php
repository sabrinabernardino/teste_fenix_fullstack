<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitAttemptRequest;
use App\Http\Resources\AttemptResource;
use App\Models\Exam;
use App\Services\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttemptController extends Controller
{
    public function __construct(private readonly AttemptService $attempts)
    {
    }

    /** Histórico de tentativas do aluno. */
    public function index(Request $request)
    {
        return AttemptResource::collection($this->attempts->historyFor($request->user()));
    }

    /** Submete as respostas e devolve a correção automática. */
    public function store(SubmitAttemptRequest $request, Exam $exam): JsonResponse
    {
        $attempt = $this->attempts->submit($exam, $request->user(), $request->validated('answers'));

        return (new AttemptResource($attempt))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $attempt): AttemptResource
    {
        return new AttemptResource($this->attempts->findForStudent($request->user(), $attempt));
    }
}
