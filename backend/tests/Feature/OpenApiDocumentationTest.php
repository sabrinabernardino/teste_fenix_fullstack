<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Evita que a documentação "envelheça": toda rota /api precisa estar no openapi.json e vice-versa.
 */
class OpenApiDocumentationTest extends TestCase
{
    public function test_openapi_spec_matches_registered_routes(): void
    {
        $spec = json_decode(file_get_contents(public_path('docs/openapi.json')), true, flags: JSON_THROW_ON_ERROR);

        $documented = [];
        foreach ($spec['paths'] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                $documented[] = strtolower($method).' /api'.$path;
            }
        }

        $registered = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
            ->flatMap(fn ($route) => collect($route->methods())
                ->reject(fn ($method) => in_array($method, ['HEAD', 'PATCH'], true))
                ->map(fn ($method) => strtolower($method).' /'.$route->uri()))
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing($registered, $documented);
    }

    public function test_swagger_ui_page_exists(): void
    {
        $this->assertFileExists(public_path('docs/index.html'));
    }
}
