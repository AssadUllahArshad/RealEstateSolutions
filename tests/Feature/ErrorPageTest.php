<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_forbidden_inertia_navigation_renders_an_accessible_error_page(): void
    {
        $version = app(HandleInertiaRequests::class)->version(Request::create('/team'));
        $response = $this->actingAs(User::factory()->create())->get('/team', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ])
            ->assertForbidden()->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'errors/status')->assertJsonPath('props.status', 403)
            ->assertJsonMissingPath('props.exception')->assertJsonMissingPath('props.trace');
        $this->assertTrue(Str::isUuid($response->json('props.requestId')));
    }

    public function test_missing_page_works_for_guests_without_staff_layout_props(): void
    {
        $this->get('/this-page-does-not-exist')->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('errors/status')->where('status', 404));
    }

    public function test_expired_session_and_throttle_errors_keep_status_and_retry_headers(): void
    {
        Route::get('/_test/session-expired', fn () => abort(419));
        Route::get('/_test/rate-limited', fn () => abort(429, 'Internal limiter detail', ['Retry-After' => '30']));
        $this->get('/_test/session-expired')->assertStatus(419)
            ->assertInertia(fn (Assert $page) => $page->component('errors/status')->where('status', 419));
        $this->get('/_test/rate-limited')->assertStatus(429)->assertHeader('Retry-After', '30')
            ->assertDontSee('Internal limiter detail')
            ->assertInertia(fn (Assert $page) => $page->component('errors/status')->where('status', 429));
    }

    public function test_production_style_server_errors_do_not_expose_exception_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/server-error', fn () => throw new RuntimeException('Private diagnostic details'));
        $this->get('/_test/server-error')->assertStatus(500)->assertDontSee('Private diagnostic details')
            ->assertInertia(fn (Assert $page) => $page->component('errors/status')->where('status', 500)
                ->missing('exception')->missing('trace'));
    }

    public function test_json_errors_keep_their_json_contract(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/team')->assertForbidden()
            ->assertJsonStructure(['message'])->assertJsonMissingPath('component');
    }
}
