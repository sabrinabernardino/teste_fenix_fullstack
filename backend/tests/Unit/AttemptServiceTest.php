<?php

namespace Tests\Unit;

use App\Exceptions\ExamAlreadyTakenException;
use App\Exceptions\InvalidAnswersException;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Student;
use App\Services\AttemptService;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesExams;
use Tests\TestCase;

class AttemptServiceTest extends TestCase
{
    use CreatesExams;
    use RefreshDatabase;

    private AttemptService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttemptService::class);
    }

    public function test_grades_and_persists_the_attempt(): void
    {
        $exam = $this->makeExam(4);
        $student = Student::factory()->create();

        $attempt = $this->service->submit($exam, $student, $this->answersFor($exam, 3));

        $this->assertSame(3, $attempt->score);
        $this->assertSame(4, $attempt->total_questions);
        $this->assertSame(75.0, $attempt->percentage);
        $this->assertCount(4, $attempt->answers);
        $this->assertSame(3, $attempt->answers->where('is_correct', true)->count());
    }

    public function test_zero_correct_answers_gives_zero_percent(): void
    {
        $exam = $this->makeExam(2);

        $attempt = $this->service->submit($exam, Student::factory()->create(), $this->answersFor($exam, 0));

        $this->assertSame(0, $attempt->score);
        $this->assertSame(0.0, $attempt->percentage);
    }

    public function test_throws_when_student_already_took_the_exam(): void
    {
        $exam = $this->makeExam(2);
        $student = Student::factory()->create();
        $this->service->submit($exam, $student, $this->answersFor($exam, 2));

        $this->expectException(ExamAlreadyTakenException::class);
        $this->service->submit($exam, $student, $this->answersFor($exam, 2));
    }

    public function test_unique_constraint_catches_concurrent_submissions(): void
    {
        $exam = $this->makeExam(2);
        $student = Student::factory()->create();

        // Simula uma requisição concorrente que grava a tentativa entre a checagem e o insert.
        Attempt::creating(function () use ($exam, $student) {
            DB::table('attempts')->insert([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'score' => 0,
                'total_questions' => 2,
                'percentage' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $this->expectException(ExamAlreadyTakenException::class);
            $this->service->submit($exam, $student, $this->answersFor($exam, 2));
        } finally {
            Attempt::flushEventListeners();
        }
    }

    public function test_throws_when_exam_has_no_questions(): void
    {
        $exam = Exam::factory()->create();

        $this->expectException(InvalidAnswersException::class);
        $this->service->submit($exam, Student::factory()->create(), [['question_id' => 1, 'option_id' => 1]]);
    }

    public function test_throws_when_answers_are_incomplete(): void
    {
        $exam = $this->makeExam(3);
        $answers = array_slice($this->answersFor($exam, 3), 0, 2);

        $this->expectException(InvalidAnswersException::class);
        $this->service->submit($exam, Student::factory()->create(), $answers);
    }

    public function test_throws_when_a_question_is_answered_twice(): void
    {
        $exam = $this->makeExam(2);
        $answers = $this->answersFor($exam, 2);
        $answers[1] = $answers[0];

        $this->expectException(InvalidAnswersException::class);
        $this->service->submit($exam, Student::factory()->create(), $answers);
    }

    public function test_throws_when_answer_refers_to_unknown_question(): void
    {
        $exam = $this->makeExam(2);
        $answers = $this->answersFor($exam, 2);
        $answers[1]['question_id'] = 999999;

        $this->expectException(InvalidAnswersException::class);
        $this->service->submit($exam, Student::factory()->create(), $answers);
    }

    public function test_throws_when_option_belongs_to_another_question(): void
    {
        $exam = $this->makeExam(2);
        $answers = $this->answersFor($exam, 2);
        $answers[0]['option_id'] = $answers[1]['option_id'];

        $this->expectException(InvalidAnswersException::class);
        $this->service->submit($exam, Student::factory()->create(), $answers);
    }

    public function test_invalid_submission_does_not_persist_anything(): void
    {
        $exam = $this->makeExam(2);
        $answers = array_slice($this->answersFor($exam, 2), 0, 1);

        try {
            $this->service->submit($exam, Student::factory()->create(), $answers);
        } catch (InvalidAnswersException) {
            // esperado
        }

        $this->assertDatabaseCount('attempts', 0);
        $this->assertDatabaseCount('attempt_answers', 0);
    }

    public function test_submission_invalidates_dashboard_cache(): void
    {
        $exam = $this->makeExam(2);
        $dashboard = app(DashboardService::class);

        $this->assertSame(0, $dashboard->stats()['total_attempts']); // aquece o cache

        $this->service->submit($exam, Student::factory()->create(), $this->answersFor($exam, 2));

        $this->assertSame(1, $dashboard->stats()['total_attempts']);
    }

    public function test_find_for_student_only_returns_own_attempts(): void
    {
        [$owner, $other] = Student::factory()->count(2)->create();
        $attempt = Attempt::factory()->for($this->makeExam(1))->for($owner)->create();

        $this->assertSame($attempt->id, $this->service->findForStudent($owner, $attempt->id)->id);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->findForStudent($other, $attempt->id);
    }

    public function test_history_is_sorted_from_newest_to_oldest(): void
    {
        $student = Student::factory()->create();
        $old = Attempt::factory()->for($student)->create();
        $new = Attempt::factory()->for($student)->create();

        $this->assertSame([$new->id, $old->id], $this->service->historyFor($student)->pluck('id')->all());
    }
}
