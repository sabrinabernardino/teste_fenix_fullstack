<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'questions_count' => $this->whenCounted('questions'),
            'already_taken' => $this->attempt_id !== null,
            'attempt_id' => $this->attempt_id,
            'questions' => StudentQuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
