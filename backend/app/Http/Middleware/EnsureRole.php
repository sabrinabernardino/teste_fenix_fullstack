<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Login" simplificado (o desafio dispensa autenticação real):
 * o front envia X-Role (teacher|student) e, para alunos, X-Student-Id.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless($request->header('X-Role') === $role, 403, 'Acesso não permitido para este perfil.');

        if ($role === 'student') {
            $id = filter_var($request->header('X-Student-Id'), FILTER_VALIDATE_INT);
            $student = $id === false ? null : Student::find($id);

            abort_if($student === null, 401, 'Aluno não identificado. Informe o header X-Student-Id.');

            $request->setUserResolver(fn () => $student);
        }

        return $next($request);
    }
}
