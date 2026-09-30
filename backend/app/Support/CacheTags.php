<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class CacheTags
{
    public const EXAMS = 'exams';
    public const DASHBOARD = 'dashboard';

    public static function flush(string ...$tags): void
    {
        Cache::tags($tags)->flush();
    }
}
