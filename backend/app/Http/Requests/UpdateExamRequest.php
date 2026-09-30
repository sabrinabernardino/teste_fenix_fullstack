<?php

namespace App\Http\Requests;

class UpdateExamRequest extends StoreExamRequest
{
    /** Na edição as questões são opcionais (prova com tentativas só aceita título/descrição). */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'questions' => ['sometimes', 'array', 'min:1'],
        ]);
    }
}
