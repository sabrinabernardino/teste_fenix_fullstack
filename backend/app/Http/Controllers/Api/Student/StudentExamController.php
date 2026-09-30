<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentExamResource;
use App\Models\Exam;
use App\Services\ExamService;
use Illuminate\Http\Request;

/** Área do aluno: provas disponíveis (sem gabarito). */
class StudentExamController extends Controller
{
    public function __construct(private readonly ExamService $exams)
    {
    }

    public function index(Request $request)
    {
        return StudentExamResource::collection($this->exams->listForStudent($request->user()));
    }

    public function show(Request $request, Exam $exam): StudentExamResource
    {
        return new StudentExamResource($this->exams->findForStudent($exam, $request->user()));
    }
}
