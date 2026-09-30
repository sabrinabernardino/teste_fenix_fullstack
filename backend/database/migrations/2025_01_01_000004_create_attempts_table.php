<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('score');
            $table->unsignedInteger('total_questions');
            $table->decimal('percentage', 5, 2);
            $table->timestamps();

            // Um aluno só faz a mesma prova uma vez (vale mesmo com requisições concorrentes).
            $table->unique(['exam_id', 'student_id']);
            // Apoio ao ranking.
            $table->index(['percentage', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};
