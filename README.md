# Desafio Fênix – Provas Online

Aplicação fullstack para criação, execução e análise de provas de múltipla escolha.

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.4 · Laravel 12 |
| Frontend | Vue 3 · Vue Router · Vite |
| Banco | PostgreSQL 16 |
| Cache | Redis 7 |
| Infra | Docker Compose · Nginx · PHP-FPM (OPcache) |

## Como rodar

Pré-requisito: Docker com Docker Compose.

```bash
docker compose up -d --build
```

Na primeira subida o container `app` instala as dependências, roda as migrations e os seeders
(5 alunos e 1 prova de exemplo com 3 tentativas), e o container `frontend` roda `npm install`.
Isso leva alguns minutos; acompanhe com `docker compose logs -f app frontend`.

| Serviço | URL |
|---|---|
| Frontend | http://localhost:5173 |
| API | http://localhost:8080/api |
| Documentação (Swagger UI) | http://localhost:8080/docs/ |
| PostgreSQL (cliente externo) | `localhost:5433` · banco/usuário `fenix` · senha `secret` |

## Acessos (sem login, conforme o enunciado)

Na tela inicial escolha **Professor** ou **Aluno** (e qual aluno). O front envia os cabeçalhos
`X-Role: teacher|student` e, para aluno, `X-Student-Id`. Um middleware (`EnsureRole`) protege as rotas.

## Testes e cobertura

```bash
# testes
docker compose exec app php artisan test

# cobertura (PCOV)
docker compose exec app php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text
```

Os testes rodam em SQLite em memória. `tests/TestCase.php` aborta se a conexão não for SQLite,
para que o `RefreshDatabase` nunca apague o banco real.

## Arquitetura (backend)

```
Request → Middleware (perfil) → FormRequest (validação) → Controller (fino)
        → Service (regra de negócio, transações, cache) → Eloquent → Resource (saída JSON)
```

- `app/Http/Controllers` – recebem a requisição e delegam; sem regra de negócio.
- `app/Http/Requests` – validação de entrada (inclui "exatamente uma alternativa correta").
- `app/Services` – `ExamService`, `AttemptService` (correção automática), `DashboardService`.
- `app/Http/Resources` – formato de saída; o aluno usa resources que **não expõem** `is_correct`.
- `app/Exceptions` – exceções de negócio que já se renderizam como JSON com o status correto.
- `app/Support/CacheTags` – tags de cache (`exams`, `dashboard`).

### Regras garantidas no banco (além da aplicação)

- `UNIQUE (exam_id, student_id)` em `attempts`: um aluno só faz a mesma prova uma vez, mesmo com requisições concorrentes.
- Índice único parcial em `options (question_id) WHERE is_correct`: no máximo uma alternativa correta por questão.
- Chaves estrangeiras com `ON DELETE CASCADE`.

### Cache (Redis)

Lista de provas (10 min), métricas do dashboard e ranking paginado (5 min). São invalidados por
tags sempre que uma tentativa é submetida ou uma prova muda. Só arrays vão para o cache, nunca objetos Eloquent.

## Endpoints

| Perfil | Método e rota | Descrição |
|---|---|---|
| — | `GET /api/students` | Lista alunos |
| Professor | `GET/POST /api/exams` | Lista / cria prova com questões |
| Professor | `GET/PUT/DELETE /api/exams/{id}` | Detalha / edita / exclui |
| Professor | `GET /api/dashboard/stats` | Média, melhor e pior pontuação |
| Professor | `GET /api/dashboard/ranking` | Ranking paginado (`page`, `per_page`, `exam_id`) |
| Aluno | `GET /api/student/exams` | Provas disponíveis |
| Aluno | `GET /api/student/exams/{id}` | Abre a prova (sem gabarito) |
| Aluno | `POST /api/student/exams/{id}/attempts` | Submete e recebe a correção |
| Aluno | `GET /api/student/attempts` | Histórico |
| Aluno | `GET /api/student/attempts/{id}` | Resultado detalhado |

Contrato completo e testável em `/docs/`. Um teste garante que todas as rotas estão documentadas.

## Decisões de regra de negócio

- Uma prova que já tem tentativas não permite trocar questões (só título e descrição), para não corromper o histórico.
- Todas as questões precisam ser respondidas; as alternativas devem pertencer à respectiva questão.
- Percentual = acertos ÷ total × 100. O ranking ordena por percentual, depois nota, depois quem terminou antes.

## Limitações conhecidas

- Sem autenticação real (dispensada pelo enunciado); os cabeçalhos de perfil não são um mecanismo de segurança.
- Mensagens de validação do Laravel saem em inglês (o front valida em português antes de enviar).
- O frontend não tem testes automatizados; a cobertura de 80%+ refere-se ao backend.
