<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExamRequest;
use App\Http\Requests\UpdateExamRequest;
use App\Http\Resources\ExamResource;
use App\Models\Exam;
use App\Services\ExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Área do professor: CRUD de provas. */
class ExamController extends Controller
{
    public function __construct(private readonly ExamService $exams)
    {
    }

    public function index()
    {
        return ExamResource::collection($this->exams->list());
    }

    public function store(StoreExamRequest $request): JsonResponse
    {
        $exam = $this->exams->create($request->validated());

        return (new ExamResource($exam))->response()->setStatusCode(201);
    }

    public function show(Exam $exam): ExamResource
    {
        return new ExamResource($this->exams->findWithQuestions($exam));
    }

    public function update(UpdateExamRequest $request, Exam $exam): ExamResource
    {
        return new ExamResource($this->exams->update($exam, $request->validated()));
    }

    public function destroy(Exam $exam): Response
    {
        $this->exams->delete($exam);

        return response()->noContent();
    }
}
