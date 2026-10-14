<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 403/404/419/500 used a fixed 8rem numeral and 4rem/3rem card padding with no
 * media query -- on a 360px phone that's over a quarter of the screen width
 * gone to padding alone, and three big digits running close to the card's own
 * usable width. 404 and 500 had no custom view at all (Laravel's unbranded
 * default rendered instead). Fixed with clamp() on the shared CSS classes
 * every one of these pages already uses, plus the two missing views.
 */
class ErrorPagesResponsiveTest extends TestCase
{
    use DatabaseTransactions;

    private function user(): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Error', 'email' => 'qaerr' . uniqid() . '@example.test',
            'code' => 'QE' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
        $u->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']));

        return $u->fresh();
    }

    public function test_404_renders_the_branded_responsive_page(): void
    {
        $r = $this->actingAs($this->user())->get('/this-route-genuinely-does-not-exist-qa');
        $r->assertStatus(404);
        $r->assertSeeText('404');
        $r->assertSee('error-h1', false);
        $r->assertSee('Back to Dashboard');
    }

    public function test_500_renders_the_branded_responsive_page(): void
    {
        Route::get('/__qa_throw_500', fn () => throw new \RuntimeException('qa forced 500'))->middleware(['web', 'auth']);

        $r = $this->actingAs($this->user())->get('/__qa_throw_500');
        $r->assertStatus(500);
        $r->assertSeeText('500');
        $r->assertSee('error-h1', false);
        $r->assertSee('Back to Dashboard');
    }

    public function test_403_still_renders_after_the_shared_css_change(): void
    {
        // A bare view('errors.403')->render() skips ShareErrorsFromSession (no
        // $errors is bound outside a real request), which this layout's shared
        // partials reference -- go through an actual request instead, same as
        // the 404/500 tests above.
        Route::get('/__qa_throw_403', fn () => abort(403))->middleware(['web', 'auth']);
        $r = $this->actingAs($this->user())->get('/__qa_throw_403');
        $r->assertStatus(403);
        $r->assertSee('error-h1', false);
        $r->assertSeeText('403');
    }

    public function test_419_uses_the_shared_icon_class_not_an_inline_size(): void
    {
        Route::get('/__qa_throw_419', fn () => abort(419))->middleware(['web', 'auth']);
        $r = $this->actingAs($this->user())->get('/__qa_throw_419');
        $r->assertStatus(419);
        $r->assertSee('error-icon-lg', false);
        $html = $r->getContent();
        $this->assertStringNotContainsString('font-size: 80px', $html, 'the icon size now comes from the shared, responsive CSS class, not a fixed inline style');
    }

    public function test_the_shared_error_css_uses_clamp_not_a_fixed_numeral_size(): void
    {
        $css = file_get_contents(resource_path('assets/vendor/scss/pages/hitech-portal.scss'));
        $this->assertMatchesRegularExpression('/\.error-h1\s*\{[^}]*font-size:\s*clamp\(/', $css);
        $this->assertMatchesRegularExpression('/\.error-glass-card\s*\{[^}]*padding:\s*clamp\(/', $css);
    }
}
