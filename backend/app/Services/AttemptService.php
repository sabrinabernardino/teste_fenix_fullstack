<?php

namespace App\Services;

use App\Exceptions\ExamAlreadyTakenException;
use App\Exceptions\InvalidAnswersException;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Student;
use App\Support\CacheTags;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class AttemptService
{
    /**
     * Corrige e grava a tentativa. Regras:
     *  - um aluno só faz a mesma prova uma vez;
     *  - todas as questões precisam ser respondidas com alternativas da própria questão;
     *  - correção automática dentro de uma transação.
     *
     * @param  array<int, array{question_id:int|string, option_id:int|string}>  $answers
     */
    public function submit(Exam $exam, Student $student, array $answers): Attempt
    {
        if (Attempt::where('exam_id', $exam->id)->where('student_id', $student->id)->exists()) {
            throw new ExamAlreadyTakenException();
        }

        $exam->load('questions.options');
        $graded = $this->grade($exam, $answers);

        $score = count(array_filter($graded, fn (array $row) => $row['is_correct']));
        $total = count($graded);

        try {
            $attempt = DB::transaction(function () use ($exam, $student, $graded, $score, $total) {
                $attempt = Attempt::create([
                    'exam_id' => $exam->id,
                    'student_id' => $student->id,
                    'score' => $score,
                    'total_questions' => $total,
                    'percentage' => round($score / $total * 100, 2),
                ]);

                $attempt->answers()->createMany($graded);

                return $attempt;
            });
        } catch (UniqueConstraintViolationException) {
            // Duas submissões simultâneas: a constraint única do banco é a palavra final.
            throw new ExamAlreadyTakenException();
        }

        CacheTags::flush(CacheTags::EXAMS, CacheTags::DASHBOARD);

        return $this->loadDetails($attempt);
    }

    public function findForStudent(Student $student, int $attemptId): Attempt
    {
        $attempt = Attempt::where('student_id', $student->id)->findOrFail($attemptId);

        return $this->loadDetails($attempt);
    }

    public function historyFor(Student $student): Collection
    {
        return Attempt::where('student_id', $student->id)
            ->with('exam:id,title')
            ->latest('id')
            ->get();
    }

    private function loadDetails(Attempt $attempt): Attempt
    {
        return $attempt->load(['exam:id,title', 'student:id,name', 'answers.option', 'answers.question.options']);
    }

    /** @return array<int, array{question_id:int, option_id:int, is_correct:bool}> */
    private function grade(Exam $exam, array $answers): array
    {
        if ($exam->questions->isEmpty()) {
            throw new InvalidAnswersException('Esta prova não possui questões.');
        }

        $byQuestion = collect($answers)->keyBy(fn (array $answer) => (int) $answer['question_id']);

        if ($byQuestion->count() !== count($answers)) {
            throw new InvalidAnswersException('Há questões respondidas mais de uma vez.');
        }

        if ($byQuestion->count() !== $exam->questions->count()) {
            throw new InvalidAnswersException('Todas as questões da prova devem ser respondidas.');
        }

        return $exam->questions->map(function ($question) use ($byQuestion) {
            $answer = $byQuestion->get($question->id);

            if ($answer === null) {
                throw new InvalidAnswersException('Todas as questões da prova devem ser respondidas.');
            }

            $option = $question->options->firstWhere('id', (int) $answer['option_id']);

            if ($option === null) {
                throw new InvalidAnswersException("A alternativa informada não pertence à questão {$question->id}.");
            }

            return [
                'question_id' => $question->id,
                'option_id' => $option->id,
                'is_correct' => $option->is_correct,
            ];
        })->all();
    }
}
