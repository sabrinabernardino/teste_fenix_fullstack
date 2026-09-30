<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Base das exceções de regra de negócio. Cada subclasse define o status HTTP
 * e a exceção sabe se renderizar como JSON padronizado.
 */
abstract class ApiException extends RuntimeException
{
    abstract public function status(): int;

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status());
    }
}
