<?php

namespace App\Services;

use App\Models\Attempt;
use App\Support\CacheTags;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as PaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    private const CACHE_TTL = 300;

    /**
     * Métricas gerais (ou de uma prova específica). Cache invalidado a cada nova tentativa.
     */
    public function stats(?int $examId = null): array
    {
        $key = 'stats:'.($examId ?? 'all');

        return Cache::tags([CacheTags::DASHBOARD])->remember($key, self::CACHE_TTL, function () use ($examId) {
            $row = $this->filtered($examId)
                ->toBase()
                ->selectRaw('COUNT(*) AS total, AVG(percentage) AS avg_percentage, AVG(score) AS avg_score')
                ->first();

            $best = $this->filtered($examId)->with(['student:id,name', 'exam:id,title'])
                ->orderByDesc('percentage')->orderByDesc('score')->orderBy('created_at')->orderBy('id')
                ->first();

            $worst = $this->filtered($examId)->with(['student:id,name', 'exam:id,title'])
                ->orderBy('percentage')->orderBy('score')->orderBy('created_at')->orderBy('id')
                ->first();

            return [
                'total_attempts' => (int) $row->total,
                'average_percentage' => round((float) $row->avg_percentage, 2),
                'average_score' => round((float) $row->avg_score, 2),
                'best' => $best ? $this->summarize($best) : null,
                'worst' => $worst ? $this->summarize($worst) : null,
            ];
        });
    }

    /**
     * Ranking paginado (maior percentual primeiro; desempate por nota e por quem terminou antes).
     */
    public function ranking(?int $examId, int $perPage, int $page): PaginatorContract
    {
        $key = sprintf('ranking:%s:%d:%d', $examId ?? 'all', $perPage, $page);

        $cached = Cache::tags([CacheTags::DASHBOARD])->remember($key, self::CACHE_TTL, function () use ($examId, $perPage, $page) {
            $paginator = $this->filtered($examId)
                ->with(['student:id,name', 'exam:id,title'])
                ->orderByDesc('percentage')->orderByDesc('score')->orderBy('created_at')->orderBy('id')
                ->paginate($perPage, ['*'], 'page', $page);

            $offset = $paginator->firstItem() ?? 1;

            return [
                'total' => $paginator->total(),
                'items' => $paginator->getCollection()
                    ->values()
                    ->map(fn (Attempt $attempt, int $i) => $this->summarize($attempt, $offset + $i))
                    ->all(),
            ];
        });

        return new LengthAwarePaginator($cached['items'], $cached['total'], $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]);
    }

    private function filtered(?int $examId): Builder
    {
        return Attempt::query()->when($examId, fn (Builder $query) => $query->where('exam_id', $examId));
    }

    private function summarize(Attempt $attempt, ?int $rank = null): array
    {
        return [
            'rank' => $rank,
            'attempt_id' => $attempt->id,
            'student' => $attempt->student->name,
            'exam' => $attempt->exam->title,
            'score' => $attempt->score,
            'total_questions' => $attempt->total_questions,
            'percentage' => $attempt->percentage,
            'submitted_at' => $attempt->created_at->toIso8601String(),
        ];
    }
}
