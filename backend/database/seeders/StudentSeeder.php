<?php

namespace Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $students = [
            ['name' => 'Ana Souza', 'email' => 'ana@example.com'],
            ['name' => 'Bruno Lima', 'email' => 'bruno@example.com'],
            ['name' => 'Carla Mendes', 'email' => 'carla@example.com'],
            ['name' => 'Diego Alves', 'email' => 'diego@example.com'],
            ['name' => 'Elisa Rocha', 'email' => 'elisa@example.com'],
        ];

        foreach ($students as $data) {
            Student::firstOrCreate(['email' => $data['email']], $data);
        }
    }
}
