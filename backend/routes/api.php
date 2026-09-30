<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\Student\AttemptController;
use App\Http\Controllers\Api\Student\StudentExamController;
use Illuminate\Support\Facades\Route;

// Lista de alunos (usada pela "entrada" do aluno no front)
Route::get('students', [StudentController::class, 'index']);

// ---- Professor ----
Route::middleware('role:teacher')->group(function () {
    Route::apiResource('exams', ExamController::class);

    Route::prefix('dashboard')->group(function () {
        Route::get('stats', [DashboardController::class, 'stats']);
        Route::get('ranking', [DashboardController::class, 'ranking']);
    });
});

// ---- Aluno ----
Route::middleware('role:student')->prefix('student')->group(function () {
    Route::get('exams', [StudentExamController::class, 'index']);
    Route::get('exams/{exam}', [StudentExamController::class, 'show']);
    Route::post('exams/{exam}/attempts', [AttemptController::class, 'store']);

    Route::get('attempts', [AttemptController::class, 'index']);
    Route::get('attempts/{attempt}', [AttemptController::class, 'show']);
});
