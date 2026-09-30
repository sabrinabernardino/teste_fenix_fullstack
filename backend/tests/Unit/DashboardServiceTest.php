<?php

namespace Tests\Unit;

use App\Models\Attempt;
use App\Models\Exam;
use App\Services\DashboardService;
use App\Support\CacheTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DashboardService::class);
    }

    public function test_stats_without_data(): void
    {
        $stats = $this->service->stats();

        $this->assertSame(0, $stats['total_attempts']);
        $this->assertSame(0.0, $stats['average_percentage']);
        $this->assertNull($stats['best']);
        $this->assertNull($stats['worst']);
    }

    public function test_stats_are_cached_until_the_tag_is_flushed(): void
    {
        Attempt::factory()->create();
        $this->assertSame(1, $this->service->stats()['total_attempts']);

        Attempt::factory()->create();
        $this->assertSame(1, $this->service->stats()['total_attempts'], 'deveria vir do cache');

        CacheTags::flush(CacheTags::DASHBOARD);
        $this->assertSame(2, $this->service->stats()['total_attempts']);
    }

    public function test_ranking_breaks_ties_by_score_then_by_who_finished_first(): void
    {
        $exam = Exam::factory()->create();
        $low = Attempt::factory()->for($exam)->create(['percentage' => 100, 'score' => 5, 'created_at' => now()->subMinutes(5)]);
        $high = Attempt::factory()->for($exam)->create(['percentage' => 100, 'score' => 10, 'created_at' => now()]);
        $early = Attempt::factory()->for($exam)->create(['percentage' => 50, 'score' => 5, 'created_at' => now()->subMinutes(10)]);
        $late = Attempt::factory()->for($exam)->create(['percentage' => 50, 'score' => 5, 'created_at' => now()]);

        $ids = collect($this->service->ranking(null, 10, 1)->items())->pluck('attempt_id')->all();

        $this->assertSame([$high->id, $low->id, $early->id, $late->id], $ids);
    }

    public function test_ranking_reports_total_and_respects_page(): void
    {
        Attempt::factory()->count(7)->for(Exam::factory()->create())->create();

        $page = $this->service->ranking(null, 3, 3);

        $this->assertSame(7, $page->total());
        $this->assertCount(1, $page->items());
        $this->assertSame(7, $page->items()[0]['rank']);
    }

    public function test_ranking_for_an_empty_page(): void
    {
        $page = $this->service->ranking(null, 10, 1);

        $this->assertSame(0, $page->total());
        $this->assertCount(0, $page->items());
    }
}
