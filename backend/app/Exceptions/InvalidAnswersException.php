<?php

namespace App\Exceptions;

class InvalidAnswersException extends ApiException
{
    public function status(): int
    {
        return 422;
    }
}
