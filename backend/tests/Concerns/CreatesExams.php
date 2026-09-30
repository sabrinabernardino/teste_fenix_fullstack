<?php

namespace Tests\Concerns;

use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Models\Student;

trait CreatesExams
{
    /** Prova com N questões; cada questão tem 4 alternativas e só 1 correta. */
    protected function makeExam(int $questions = 3): Exam
    {
        $exam = Exam::factory()->create();

        foreach (range(1, $questions) as $position) {
            $question = Question::factory()->for($exam)->create(['position' => $position]);
            Option::factory()->for($question)->correct()->create();
            Option::factory()->count(3)->for($question)->create();
        }

        return $exam->load('questions.options');
    }

    /** Monta as respostas com $correct acertos (as primeiras questões acertam, o resto erra). */
    protected function answersFor(Exam $exam, int $correct): array
    {
        return $exam->questions->values()->map(function (Question $question, int $index) use ($correct) {
            $wantCorrect = $index < $correct;
            $option = $question->options->first(fn (Option $o) => $o->is_correct === $wantCorrect);

            return ['question_id' => $question->id, 'option_id' => $option->id];
        })->all();
    }

    protected function teacherHeaders(): array
    {
        return ['X-Role' => 'teacher'];
    }

    protected function studentHeaders(Student $student): array
    {
        return ['X-Role' => 'student', 'X-Student-Id' => (string) $student->id];
    }
}
