<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.statement' => ['required', 'string', 'max:1000'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:6'],
            'questions.*.options.*.text' => ['required', 'string', 'max:500'],
            'questions.*.options.*.is_correct' => ['required', 'boolean'],
        ];
    }

    /** Regra de negócio: cada questão tem EXATAMENTE uma alternativa correta. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ($this->input('questions', []) as $index => $question) {
                    $correct = collect($question['options'])
                        ->filter(fn (array $option) => filter_var($option['is_correct'], FILTER_VALIDATE_BOOLEAN))
                        ->count();

                    if ($correct !== 1) {
                        $validator->errors()->add(
                            "questions.$index.options",
                            'Cada questão deve ter exatamente uma alternativa correta.'
                        );
                    }
                }
            },
        ];
    }
}
