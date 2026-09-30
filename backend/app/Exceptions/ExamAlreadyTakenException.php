<?php

namespace App\Exceptions;

class ExamAlreadyTakenException extends ApiException
{
    public function __construct(string $message = 'Você já realizou esta prova.')
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 409;
    }
}
