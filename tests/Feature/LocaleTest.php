<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_locale_is_persisted_and_urdu_renders_rtl(): void
    {
        $this->post('/locale', ['locale' => 'ur'])->assertRedirect()->assertPlainCookie('locale', 'ur');
        $user = User::factory()->create();
        $this->actingAs($user)->withUnencryptedCookie('locale', 'ur')->get('/dashboard')
            ->assertOk()
            ->assertSee('lang="ur"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('/fonts/noto-nastaliq-urdu-variable.ttf', false)
            ->assertSee('<body class="antialiased">', false)
            ->assertDontSee('<body class="font-sans antialiased">', false);
        $this->post('/locale', ['locale' => 'unsupported'])->assertSessionHasErrors('locale');
    }
}
