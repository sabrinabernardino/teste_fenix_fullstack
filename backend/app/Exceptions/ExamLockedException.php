<?php

namespace App\Exceptions;

class ExamLockedException extends ApiException
{
    public function __construct(string $message = 'Esta prova já possui tentativas; as questões não podem mais ser alteradas.')
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 409;
    }
}
