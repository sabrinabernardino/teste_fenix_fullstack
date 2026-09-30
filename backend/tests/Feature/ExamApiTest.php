<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Exam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesExams;
use Tests\TestCase;

class ExamApiTest extends TestCase
{
    use CreatesExams;
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'title' => 'Prova de Laravel',
            'description' => 'Conceitos básicos',
            'questions' => [
                [
                    'statement' => 'O que é Eloquent?',
                    'options' => [
                        ['text' => 'Um ORM', 'is_correct' => true],
                        ['text' => 'Um template engine', 'is_correct' => false],
                    ],
                ],
            ],
        ], $override);
    }

    public function test_teacher_creates_exam_with_questions_and_options(): void
    {
        $this->withHeaders($this->teacherHeaders())
            ->postJson('/api/exams', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Prova de Laravel')
            ->assertJsonCount(1, 'data.questions')
            ->assertJsonCount(2, 'data.questions.0.options')
            ->assertJsonPath('data.questions.0.options.0.is_correct', true);

        $this->assertDatabaseCount('exams', 1);
        $this->assertDatabaseCount('questions', 1);
        $this->assertDatabaseCount('options', 2);
    }

    public function test_rejects_question_with_two_correct_options(): void
    {
        $payload = $this->payload(['questions' => [[
            'statement' => 'Pergunta',
            'options' => [
                ['text' => 'A', 'is_correct' => true],
                ['text' => 'B', 'is_correct' => true],
            ],
        ]]]);

        $this->withHeaders($this->teacherHeaders())
            ->postJson('/api/exams', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['questions.0.options']);

        $this->assertDatabaseCount('exams', 0);
    }

    public function test_rejects_question_without_correct_option(): void
    {
        $payload = $this->payload(['questions' => [[
            'statement' => 'Pergunta',
            'options' => [
                ['text' => 'A', 'is_correct' => false],
                ['text' => 'B', 'is_correct' => false],
            ],
        ]]]);

        $this->withHeaders($this->teacherHeaders())
            ->postJson('/api/exams', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['questions.0.options']);
    }

    public function test_validates_required_fields(): void
    {
        $this->withHeaders($this->teacherHeaders())
            ->postJson('/api/exams', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'questions']);
    }

    public function test_question_needs_at_least_two_options(): void
    {
        $payload = $this->payload(['questions' => [[
            'statement' => 'Pergunta',
            'options' => [['text' => 'Única', 'is_correct' => true]],
        ]]]);

        $this->withHeaders($this->teacherHeaders())
            ->postJson('/api/exams', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['questions.0.options']);
    }

    public function test_only_teacher_can_access(): void
    {
        $this->getJson('/api/exams')->assertForbidden();
        $this->withHeaders(['X-Role' => 'student'])->getJson('/api/exams')->assertForbidden();
    }

    public function test_index_lists_exams_with_counts(): void
    {
        $exam = $this->makeExam(2);
        Attempt::factory()->for($exam)->create();

        $this->withHeaders($this->teacherHeaders())
            ->getJson('/api/exams')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.questions_count', 2)
            ->assertJsonPath('data.0.attempts_count', 1);
    }

    public function test_show_returns_questions_with_correct_flag(): void
    {
        $exam = $this->makeExam(2);

        $this->withHeaders($this->teacherHeaders())
            ->getJson("/api/exams/{$exam->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.questions')
            ->assertJsonCount(4, 'data.questions.0.options')
            ->assertJsonStructure(['data' => ['questions' => [['options' => [['is_correct']]]]]]);
    }

    public function test_show_returns_json_404_for_unknown_exam(): void
    {
        $this->withHeaders($this->teacherHeaders())
            ->getJson('/api/exams/9999')
            ->assertNotFound()
            ->assertJson(['message' => 'Recurso não encontrado.']);
    }

    public function test_update_replaces_questions_when_no_attempts(): void
    {
        $exam = $this->makeExam(3);

        $this->withHeaders($this->teacherHeaders())
            ->putJson("/api/exams/{$exam->id}", $this->payload(['title' => 'Novo título']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Novo título')
            ->assertJsonCount(1, 'data.questions');

        $this->assertDatabaseCount('questions', 1);
    }

    public function test_update_blocks_question_changes_when_exam_has_attempts(): void
    {
        $exam = $this->makeExam(2);
        Attempt::factory()->for($exam)->create();

        $this->withHeaders($this->teacherHeaders())
            ->putJson("/api/exams/{$exam->id}", $this->payload())
            ->assertStatus(409);

        $this->assertDatabaseCount('questions', 2);
    }

    public function test_update_title_only_is_allowed_when_exam_has_attempts(): void
    {
        $exam = $this->makeExam(2);
        Attempt::factory()->for($exam)->create();

        $this->withHeaders($this->teacherHeaders())
            ->putJson("/api/exams/{$exam->id}", ['title' => 'Só o título'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Só o título')
            ->assertJsonCount(2, 'data.questions');
    }

    public function test_destroy_removes_exam_and_children(): void
    {
        $exam = $this->makeExam(2);

        $this->withHeaders($this->teacherHeaders())
            ->deleteJson("/api/exams/{$exam->id}")
            ->assertNoContent();

        $this->assertModelMissing($exam);
        $this->assertDatabaseCount('questions', 0);
        $this->assertDatabaseCount('options', 0);
    }

    public function test_unknown_api_route_returns_json_404(): void
    {
        $this->getJson('/api/nao-existe')
            ->assertNotFound()
            ->assertJson(['message' => 'Recurso não encontrado.']);
    }

    public function test_exam_model_relations(): void
    {
        $exam = $this->makeExam(2);

        $this->assertInstanceOf(Exam::class, $exam);
        $this->assertCount(2, $exam->questions);
        $this->assertSame([1, 2], $exam->questions->pluck('position')->all());
    }
}
