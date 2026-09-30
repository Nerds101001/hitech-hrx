<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 123456 stands in for the emailed code on a developer's own machine, and only there:
 * the "local" environment AND a request to localhost. Everywhere else it is just a
 * wrong code. This is the guard that keeps the shortcut from ever working on production.
 */
class LocalOtpBypassTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->user = User::create(['first_name' => 'QA', 'last_name' => 'Otp', 'email' => 'qaotp' . uniqid() . '@example.test',
            'code' => 'QAOT' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active']);
        $this->user->forceFill(['otp_code' => '654321', 'otp_expires_at' => now()->addMinutes(10)])->save();
        Auth::logout();
    }

    private function verify(string $env, string $host, string $code)
    {
        $this->app->detectEnvironment(fn () => $env);
        session(['auth_otp_user_id' => $this->user->id]);
        $req = Request::create("http://{$host}/two-factor/verify", 'POST', ['otp' => $code]);
        $req->setLaravelSession(session()->driver());

        try {
            return app(AuthController::class)->verifyOtp($req);
        } finally {
            Auth::shouldUse('web');
        }
    }

    public function test_123456_logs_you_in_on_localhost_in_the_local_environment(): void
    {
        $this->verify('local', 'localhost', '123456');

        $this->assertSame($this->user->id, Auth::id());
    }

    public function test_it_works_on_127_0_0_1_too(): void
    {
        $this->verify('local', '127.0.0.1', '123456');

        $this->assertSame($this->user->id, Auth::id());
    }

    public function test_it_never_works_in_production(): void
    {
        $this->verify('production', 'localhost', '123456');

        $this->assertNull(Auth::id());
        $this->assertSame(1, (int) $this->user->fresh()->otp_attempts, 'counted as a wrong code');
    }

    public function test_it_never_works_on_a_real_domain_even_if_the_environment_says_local(): void
    {
        $this->verify('local', 'hrx.hitechgroup.in', '123456');

        $this->assertNull(Auth::id());
    }

    public function test_the_real_emailed_code_still_works_everywhere(): void
    {
        $this->verify('production', 'hrx.hitechgroup.in', '654321');

        $this->assertSame($this->user->id, Auth::id());
    }

    public function test_any_other_wrong_code_is_still_refused_locally(): void
    {
        $this->verify('local', 'localhost', '111111');

        $this->assertNull(Auth::id());
    }
}
