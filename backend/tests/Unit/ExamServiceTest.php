<?php

namespace Tests\Unit;

use App\Exceptions\ExamLockedException;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Student;
use App\Services\DashboardService;
use App\Services\ExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesExams;
use Tests\TestCase;

class ExamServiceTest extends TestCase
{
    use CreatesExams;
    use RefreshDatabase;

    private ExamService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ExamService::class);
    }

    private function data(string $title = 'Prova', int $questions = 2): array
    {
        return [
            'title' => $title,
            'description' => null,
            'questions' => array_map(fn (int $i) => [
                'statement' => "Questão $i",
                'options' => [
                    ['text' => 'Certa', 'is_correct' => true],
                    ['text' => 'Errada', 'is_correct' => false],
                ],
            ], range(1, $questions)),
        ];
    }

    public function test_create_persists_questions_in_order(): void
    {
        $exam = $this->service->create($this->data('Minha prova', 3));

        $this->assertSame('Minha prova', $exam->title);
        $this->assertSame([1, 2, 3], $exam->questions->pluck('position')->all());
        $this->assertCount(2, $exam->questions->first()->options);
    }

    public function test_list_is_served_from_cache_until_something_changes(): void
    {
        $this->makeExam(1);
        $this->assertCount(1, $this->service->list());

        Exam::factory()->create(); // insert direto: não passa pelo service, não invalida o cache
        $this->assertCount(1, $this->service->list());

        $this->service->create($this->data()); // passa pelo service: invalida
        $this->assertCount(3, $this->service->list());
    }

    public function test_list_for_student_flags_taken_exams(): void
    {
        $student = Student::factory()->create();
        $taken = $this->makeExam(1);
        $this->makeExam(1);
        Attempt::factory()->for($taken)->for($student)->create();

        $list = $this->service->listForStudent($student)->keyBy('id');

        $this->assertNotNull($list[$taken->id]->attempt_id);
        $this->assertCount(1, $list->filter(fn ($exam) => $exam->attempt_id === null));
    }

    public function test_find_for_student_loads_questions_and_attempt_id(): void
    {
        $student = Student::factory()->create();
        $exam = $this->makeExam(2);

        $found = $this->service->findForStudent($exam, $student);

        $this->assertNull($found->attempt_id);
        $this->assertCount(2, $found->questions);
    }

    public function test_update_replaces_questions(): void
    {
        $exam = $this->makeExam(3);

        $updated = $this->service->update($exam, $this->data('Atualizada', 1));

        $this->assertSame('Atualizada', $updated->title);
        $this->assertCount(1, $updated->questions);
        $this->assertDatabaseCount('questions', 1);
    }

    public function test_update_without_questions_keeps_them(): void
    {
        $exam = $this->makeExam(3);

        $updated = $this->service->update($exam, ['title' => 'Só título']);

        $this->assertSame('Só título', $updated->title);
        $this->assertCount(3, $updated->questions);
    }

    public function test_update_with_questions_is_blocked_when_exam_has_attempts(): void
    {
        $exam = $this->makeExam(2);
        Attempt::factory()->for($exam)->create();

        $this->expectException(ExamLockedException::class);
        $this->service->update($exam, $this->data());
    }

    public function test_delete_removes_exam_and_invalidates_caches(): void
    {
        $exam = $this->makeExam(1);
        Attempt::factory()->for($exam)->create();
        $dashboard = app(DashboardService::class);

        $this->assertCount(1, $this->service->list());
        $this->assertSame(1, $dashboard->stats()['total_attempts']);

        $this->service->delete($exam);

        $this->assertCount(0, $this->service->list());
        $this->assertSame(0, $dashboard->stats()['total_attempts']);
    }
}
