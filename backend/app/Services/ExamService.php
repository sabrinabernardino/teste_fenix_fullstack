<?php

namespace App\Services;

use App\Exceptions\ExamLockedException;
use App\Models\Exam;
use App\Models\Student;
use App\Support\CacheTags;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ExamService
{
    private const CACHE_TTL = 600;

    /**
     * Lista de provas (cache em Redis). Guardamos só atributos (arrays),
     * nunca objetos, para o cache ser leve e seguro de desserializar.
     */
    public function list(): Collection
    {
        $rows = Cache::tags([CacheTags::EXAMS])->remember('exams:list', self::CACHE_TTL, function () {
            return Exam::query()
                ->withCount(['questions', 'attempts'])
                ->latest('id')
                ->get()
                ->map(fn (Exam $exam) => $exam->getAttributes())
                ->all();
        });

        return Exam::hydrate($rows);
    }

    /** Lista de provas marcando quais o aluno já realizou (attempt_id). */
    public function listForStudent(Student $student): Collection
    {
        $taken = $student->attempts()->pluck('id', 'exam_id');

        return $this->list()->each(function (Exam $exam) use ($taken) {
            $exam->setAttribute('attempt_id', $taken[$exam->id] ?? null);
        });
    }

    public function findWithQuestions(Exam $exam): Exam
    {
        return $exam->load('questions.options')->loadCount('attempts');
    }

    public function findForStudent(Exam $exam, Student $student): Exam
    {
        $exam->load('questions.options');
        $exam->setAttribute('attempt_id', $student->attempts()->where('exam_id', $exam->id)->value('id'));

        return $exam;
    }

    public function create(array $data): Exam
    {
        $exam = DB::transaction(function () use ($data) {
            $exam = Exam::create(Arr::only($data, ['title', 'description']));
            $this->storeQuestions($exam, $data['questions']);

            return $exam;
        });

        CacheTags::flush(CacheTags::EXAMS);

        return $exam->load('questions.options');
    }

    public function update(Exam $exam, array $data): Exam
    {
        $replacesQuestions = array_key_exists('questions', $data);

        // Trocar questões de uma prova com tentativas corromperia o histórico.
        if ($replacesQuestions && $exam->attempts()->exists()) {
            throw new ExamLockedException();
        }

        DB::transaction(function () use ($exam, $data, $replacesQuestions) {
            $exam->update(Arr::only($data, ['title', 'description']));

            if ($replacesQuestions) {
                $exam->questions()->getQuery()->reorder()->delete();
                $this->storeQuestions($exam, $data['questions']);
            }
        });

        CacheTags::flush(CacheTags::EXAMS);

        return $exam->load('questions.options');
    }

    public function delete(Exam $exam): void
    {
        $exam->delete(); // FKs em cascata removem questões, alternativas e tentativas

        CacheTags::flush(CacheTags::EXAMS, CacheTags::DASHBOARD);
    }

    private function storeQuestions(Exam $exam, array $questions): void
    {
        foreach (array_values($questions) as $index => $data) {
            $question = $exam->questions()->create([
                'statement' => $data['statement'],
                'position' => $index + 1,
            ]);

            $question->options()->createMany(
                collect($data['options'])->map(fn (array $option) => [
                    'text' => $option['text'],
                    'is_correct' => filter_var($option['is_correct'], FILTER_VALIDATE_BOOLEAN),
                ])->all()
            );
        }
    }
}
