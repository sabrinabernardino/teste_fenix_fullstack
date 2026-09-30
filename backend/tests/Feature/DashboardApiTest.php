<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesExams;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use CreatesExams;
    use RefreshDatabase;

    public function test_only_teacher_can_see_dashboard(): void
    {
        $this->getJson('/api/dashboard/stats')->assertForbidden();
        $this->withHeaders(['X-Role' => 'student', 'X-Student-Id' => '1'])
            ->getJson('/api/dashboard/ranking')->assertForbidden();
    }

    public function test_stats_are_empty_without_attempts(): void
    {
        $this->withHeaders($this->teacherHeaders())
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.total_attempts', 0)
            ->assertJsonPath('data.average_percentage', 0)
            ->assertJsonPath('data.best', null)
            ->assertJsonPath('data.worst', null);
    }

    public function test_stats_compute_average_best_and_worst(): void
    {
        $exam = Exam::factory()->create();
        Attempt::factory()->for($exam)->create(['score' => 5, 'percentage' => 100]);
        Attempt::factory()->for($exam)->create(['score' => 3, 'percentage' => 60]);
        Attempt::factory()->for($exam)->create(['score' => 2, 'percentage' => 40]);

        $this->withHeaders($this->teacherHeaders())
            ->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.total_attempts', 3)
            ->assertJsonPath('data.average_percentage', 66.67)
            ->assertJsonPath('data.average_score', 3.33)
            ->assertJsonPath('data.best.percentage', 100)
            ->assertJsonPath('data.worst.percentage', 40);
    }

    public function test_ranking_is_ordered_and_numbered(): void
    {
        $exam = Exam::factory()->create();
        Attempt::factory()->for($exam)->create(['percentage' => 40, 'score' => 2]);
        Attempt::factory()->for($exam)->create(['percentage' => 100, 'score' => 5]);
        Attempt::factory()->for($exam)->create(['percentage' => 60, 'score' => 3]);

        $this->withHeaders($this->teacherHeaders())
            ->getJson('/api/dashboard/ranking')
            ->assertOk()
            ->assertJsonPath('data.0.rank', 1)
            ->assertJsonPath('data.0.percentage', 100)
            ->assertJsonPath('data.1.rank', 2)
            ->assertJsonPath('data.2.percentage', 40)
            ->assertJsonStructure(['data' => [['rank', 'student', 'exam', 'score', 'percentage']], 'links', 'meta']);
    }

    public function test_ranking_is_paginated(): void
    {
        Attempt::factory()->count(15)->for(Exam::factory()->create())->create();

        $this->withHeaders($this->teacherHeaders())
            ->getJson('/api/dashboard/ranking?per_page=10&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('data.0.rank', 11);
    }

    public function test_ranking_and_stats_can_be_filtered_by_exam(): void
    {
        $a = Exam::factory()->create();
        $b = Exam::factory()->create();
        Attempt::factory()->count(2)->for($a)->create();
        Attempt::factory()->for($b)->create();

        $this->withHeaders($this->teacherHeaders())
            ->getJson("/api/dashboard/ranking?exam_id={$a->id}")
            ->assertJsonPath('meta.total', 2);

        $this->withHeaders($this->teacherHeaders())
            ->getJson("/api/dashboard/stats?exam_id={$b->id}")
            ->assertJsonPath('data.total_attempts', 1);
    }

    public function test_filters_are_validated(): void
    {
        $this->withHeaders($this->teacherHeaders())
            ->getJson('/api/dashboard/ranking?per_page=500&exam_id=9999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'exam_id']);
    }

    public function test_cache_is_invalidated_when_a_new_attempt_is_submitted(): void
    {
        $exam = $this->makeExam(2);
        [$first, $second] = Student::factory()->count(2)->create();
        $answers = ['answers' => $this->answersFor($exam, 2)];

        $this->withHeaders($this->studentHeaders($first))
            ->postJson("/api/student/exams/{$exam->id}/attempts", $answers)->assertCreated();

        $this->withHeaders($this->teacherHeaders())
            ->getJson('/api/dashboard/stats')->assertJsonPath('data.total_attempts', 1);

        $this->withHeaders($this->studentHeaders($second))
            ->postJson("/api/student/exams/{$exam->id}/attempts", $answers)->assertCreated();

        $this->withHeaders($this->teacherHeaders())
            ->getJson('/api/dashboard/stats')->assertJsonPath('data.total_attempts', 2);
    }
}
