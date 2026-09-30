<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam' => $this->whenLoaded('exam', fn () => [
                'id' => $this->exam->id,
                'title' => $this->exam->title,
            ]),
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->name,
            ]),
            'score' => $this->score,
            'total_questions' => $this->total_questions,
            'percentage' => $this->percentage,
            'submitted_at' => $this->created_at,
            'details' => $this->whenLoaded('answers', fn () => $this->details()),
        ];
    }

    /** Gabarito detalhado (questão a questão) — só depois de o aluno ter submetido. */
    private function details(): array
    {
        return $this->answers
            ->sortBy(fn ($answer) => $answer->question->position)
            ->values()
            ->map(function ($answer) {
                $correct = $answer->question->options->firstWhere('is_correct', true);

                return [
                    'question_id' => $answer->question_id,
                    'statement' => $answer->question->statement,
                    'selected_option' => ['id' => $answer->option->id, 'text' => $answer->option->text],
                    'correct_option' => $correct ? ['id' => $correct->id, 'text' => $correct->text] : null,
                    'is_correct' => $answer->is_correct,
                ];
            })
            ->all();
    }
}
