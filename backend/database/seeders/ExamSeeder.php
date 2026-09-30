<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Student;
use App\Services\AttemptService;
use App\Services\ExamService;
use Illuminate\Database\Seeder;

/** Cria uma prova de exemplo e algumas tentativas para o dashboard não nascer vazio. */
class ExamSeeder extends Seeder
{
    public function run(ExamService $exams, AttemptService $attempts): void
    {
        if (Exam::exists()) {
            return; // idempotente: o container roda o seed a cada start
        }

        $exam = $exams->create([
            'title' => 'Fundamentos de PHP',
            'description' => 'Prova de exemplo com 5 questões de múltipla escolha.',
            'questions' => [
                $this->question('Qual símbolo inicia uma variável em PHP?', ['$', '@', '#', '&'], 0),
                $this->question('Qual função exibe o tipo e o valor de uma variável?', ['print_r()', 'var_dump()', 'echo()', 'type()'], 1),
                $this->question('Qual operador concatena strings em PHP?', ['+', '&', '.', '::'], 2),
                $this->question('Qual gerenciador de dependências é o padrão do ecossistema PHP?', ['npm', 'pip', 'Composer', 'Maven'], 2),
                $this->question('Qual versão introduziu os enums nativos?', ['PHP 7.4', 'PHP 8.0', 'PHP 8.1', 'PHP 8.3'], 2),
            ],
        ]);

        // Acertos por aluno (ana=5, bruno=3, carla=2)
        $correctPerStudent = [5, 3, 2];

        Student::orderBy('id')->take(count($correctPerStudent))->get()
            ->each(function (Student $student, int $i) use ($exam, $attempts, $correctPerStudent) {
                $answers = $exam->questions->values()->map(function ($question, int $qi) use ($i, $correctPerStudent) {
                    $wantCorrect = $qi < $correctPerStudent[$i];
                    $option = $question->options->first(fn ($o) => $o->is_correct === $wantCorrect);

                    return ['question_id' => $question->id, 'option_id' => $option->id];
                })->all();

                $attempts->submit($exam, $student, $answers);
            });
    }

    private function question(string $statement, array $options, int $correctIndex): array
    {
        return [
            'statement' => $statement,
            'options' => array_map(
                fn (string $text, int $i) => ['text' => $text, 'is_correct' => $i === $correctIndex],
                $options,
                array_keys($options),
            ),
        ];
    }
}
