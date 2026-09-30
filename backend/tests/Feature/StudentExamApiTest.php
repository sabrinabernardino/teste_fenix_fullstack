<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesExams;
use Tests\TestCase;

class StudentExamApiTest extends TestCase
{
    use CreatesExams;
    use RefreshDatabase;

    public function test_students_endpoint_is_public(): void
    {
        Student::factory()->count(3)->create();

        $this->getJson('/api/students')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_student_routes_require_role_and_valid_student(): void
    {
        $this->getJson('/api/student/exams')->assertForbidden();

        $this->withHeaders(['X-Role' => 'student'])
            ->getJson('/api/student/exams')->assertUnauthorized();

        $this->withHeaders(['X-Role' => 'student', 'X-Student-Id' => '9999'])
            ->getJson('/api/student/exams')->assertUnauthorized();

        $this->withHeaders(['X-Role' => 'student', 'X-Student-Id' => 'abc'])
            ->getJson('/api/student/exams')->assertUnauthorized();

        $this->withHeaders($this->teacherHeaders())
            ->getJson('/api/student/exams')->assertForbidden();
    }

    public function test_lists_exams_flagging_the_ones_already_taken(): void
    {
        $student = Student::factory()->create();
        $taken = $this->makeExam(2);
        $available = $this->makeExam(2);
        $attempt = Attempt::factory()->for($taken)->for($student)->create();

        $response = $this->withHeaders($this->studentHeaders($student))
            ->getJson('/api/student/exams')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($byId[$taken->id]['already_taken']);
        $this->assertSame($attempt->id, $byId[$taken->id]['attempt_id']);
        $this->assertFalse($byId[$available->id]['already_taken']);
    }

    public function test_show_never_exposes_the_correct_option(): void
    {
        $student = Student::factory()->create();
        $exam = $this->makeExam(2);

        $response = $this->withHeaders($this->studentHeaders($student))
            ->getJson("/api/student/exams/{$exam->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.questions')
            ->assertJsonCount(4, 'data.questions.0.options')
            ->assertJsonPath('data.already_taken', false);

        $this->assertStringNotContainsString('is_correct', $response->getContent());
    }

    public function test_submission_is_graded_automatically(): void
    {
        $student = Student::factory()->create();
        $exam = $this->makeExam(3);

        $this->withHeaders($this->studentHeaders($student))
            ->postJson("/api/student/exams/{$exam->id}/attempts", ['answers' => $this->answersFor($exam, 2)])
            ->assertCreated()
            ->assertJsonPath('data.score', 2)
            ->assertJsonPath('data.total_questions', 3)
            ->assertJsonPath('data.percentage', 66.67)
            ->assertJsonCount(3, 'data.details')
            ->assertJsonPath('data.details.0.is_correct', true)
            ->assertJsonPath('data.details.2.is_correct', false);

        $this->assertDatabaseHas('attempts', ['exam_id' => $exam->id, 'student_id' => $student->id, 'score' => 2]);
        $this->assertDatabaseCount('attempt_answers', 3);
    }

    public function test_perfect_score_is_100_percent(): void
    {
        $student = Student::factory()->create();
        $exam = $this->makeExam(4);

        $this->withHeaders($this->studentHeaders($student))
            ->postJson("/api/student/exams/{$exam->id}/attempts", ['answers' => $this->answersFor($exam, 4)])
            ->assertCreated()
            ->assertJsonPath('data.percentage', 100);
    }

    public function test_student_cannot_take_the_same_exam_twice(): void
    {
        $student = Student::factory()->create();
        $exam = $this->makeExam(2);
        $answers = ['answers' => $this->answersFor($exam, 1)];

        $this->withHeaders($this->studentHeaders($student))
            ->postJson("/api/student/exams/{$exam->id}/attempts", $answers)->assertCreated();

        $this->withHeaders($this->studentHeaders($student))
            ->postJson("/api/student/exams/{$exam->id}/attempts", $answers)
            ->assertStatus(409)
            ->assertJson(['message' => 'Você já realizou esta prova.']);

        $this->assertDatabaseCount('attempts', 1);
    }

    public function test_two_different_students_can_take_the_same_exam(): void
    {
        $exam = $this->makeExam(2);
        $answers = ['answers' => $this->answersFor($exam, 2)];

        foreach (Student::factory()->count(2)->create() as $student) {
            $this->withHeaders($this->studentHeaders($student))
                ->postJson("/api/student/exams/{$exam->id}/attempts", $answers)->assertCreated();
        }

        $this->assertDatabaseCount('attempts', 2);
    }

    public function test_rejects_incomplete_answers(): void
    {
        $student = Student::factory()->create();
        $exam = $this->makeExam(3);
        $answers = array_slice($this->answersFor($exam, 3), 0, 2);

        $this->withHeaders($this->studentHeaders($student))
            ->postJson("/api/student/exams/{$exam->id}/attempts", ['answers' => $answers])
            ->assertUnprocessable();

        $this->assertDatabaseCount('attempts', 0);
    }

    public function test_rejects_option_from_another_question(): void
    {
        $student = Student::factory()->create();
        $exam = $this->makeExam(2);
        $answers = $this->answersFor($exam, 2);
        $answers[0]['option_id'] = $answers[1]['option_id'];

        $this->withHeaders($this->studentHeaders($student))
            ->postJson("/api/student/exams/{$exam->id}/attempts", ['answers' => $answers])
            ->assertUnprocessable();
    }

    public function test_rejects_duplicated_answers(): void
    {
        $student = Student::factory()->create();
        $exam = $this->makeExam(2);
        $answers = $this->answersFor($exam, 2);
        $answers[1] = $answers[0];

        $this->withHeaders($this->studentHeaders($student))
            ->postJson("/api/student/exams/{$exam->id}/attempts", ['answers' => $answers])
            ->assertUnprocessable();
    }

    public function test_submission_payload_is_validated(): void
    {
        $student = Student::factory()->create();
        $exam = $this->makeExam(1);

        $this->withHeaders($this->studentHeaders($student))
            ->postJson("/api/student/exams/{$exam->id}/attempts", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_student_reads_own_result_with_answer_key(): void
    {
        $student = Student::factory()->create();
        $exam = $this->makeExam(2);

        $attemptId = $this->withHeaders($this->studentHeaders($student))
            ->postJson("/api/student/exams/{$exam->id}/attempts", ['answers' => $this->answersFor($exam, 1)])
            ->json('data.id');

        $this->withHeaders($this->studentHeaders($student))
            ->getJson("/api/student/attempts/{$attemptId}")
            ->assertOk()
            ->assertJsonPath('data.score', 1)
            ->assertJsonStructure(['data' => ['details' => [['statement', 'selected_option', 'correct_option', 'is_correct']]]]);
    }

    public function test_student_cannot_read_someone_elses_result(): void
    {
        [$owner, $other] = Student::factory()->count(2)->create();
        $attempt = Attempt::factory()->for($this->makeExam(1))->for($owner)->create();

        $this->withHeaders($this->studentHeaders($other))
            ->getJson("/api/student/attempts/{$attempt->id}")
            ->assertNotFound();
    }

    public function test_history_lists_only_own_attempts(): void
    {
        [$me, $other] = Student::factory()->count(2)->create();
        Attempt::factory()->count(2)->for($me)->create();
        Attempt::factory()->for($other)->create();

        $this->withHeaders($this->studentHeaders($me))
            ->getJson('/api/student/attempts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.exam.title', fn ($title) => is_string($title));
    }
}
