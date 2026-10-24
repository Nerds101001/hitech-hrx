<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Auth\UnlockAccountNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * REGRESSION (security re-audit L8). The login page told anyone whether an
 * account existed ("User not found." vs "Invalid password. You have N attempts
 * left"), the forgot-password page did too, and the unlock page showed the
 * unlock link on screen to whoever typed a locked account's email - so a
 * guesser could lock, unlock and keep guessing.
 */
class LoginNoAccountDisclosureTest extends TestCase
{
    use DatabaseTransactions;

    private User $real;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Cache::flush();
        $this->real = User::create([
            'first_name' => 'QA', 'last_name' => 'Login', 'email' => 'qalogin' . uniqid() . '@example.test',
            'code' => 'QL' . random_int(100000, 999999), 'password' => Hash::make('the-right-one'), 'status' => 'active',
        ]);
    }

    private function attempt(string $login): ?string
    {
        return $this->from('/auth/login')->post(route('auth.loginPost'), ['email' => $login, 'password' => 'wrong-guess'])
            ->getSession()->get('error');
    }

    public function test_a_wrong_password_and_an_unknown_login_get_identical_replies_including_the_lock(): void
    {
        $unknown = 'nobody' . uniqid() . '@example.test';
        for ($i = 1; $i <= 3; $i++) {
            $this->assertSame($this->attempt($this->real->email), $this->attempt($unknown), "attempt $i reads the same either way");
        }
        $this->assertStringContainsString('locked', $this->attempt($unknown), 'the unknown login shows the same lock message');
        $this->assertNotNull($this->real->fresh()->locked_until, 'the real account is still actually locked');
    }

    public function test_the_unlock_link_goes_to_the_inbox_only_and_the_reply_never_says_whether_the_account_exists(): void
    {
        $this->real->forceFill(['locked_until' => now()->addMinutes(40)])->save();

        $locked = $this->from('/auth/unlock-request')->post(route('auth.unlock.request'), ['email' => $this->real->email]);
        $this->assertNull($locked->getSession()->get('unlock_link'), 'no link on screen');
        Notification::assertSentTo($this->real, UnlockAccountNotification::class);

        $unknown = $this->from('/auth/unlock-request')->post(route('auth.unlock.request'), ['email' => 'nobody' . uniqid() . '@example.test']);
        $this->assertSame($locked->getSession()->get('success'), $unknown->getSession()->get('success'));
    }

    /**
     * Deliberate, explicit exception to the rule above (owner's call, 2026-10-24):
     * a known account that isn't locked gets a distinct "not locked" reply instead
     * of the generic one, so a genuine owner isn't left waiting on an email that
     * will never come. This does let the unlock-request page confirm whether an
     * email belongs to an account - only for the not-locked case - which the owner
     * accepted in exchange for the feedback.
     */
    public function test_a_known_but_unlocked_account_gets_a_distinct_reply_on_purpose(): void
    {
        $knownReply = $this->from('/auth/unlock-request')
            ->post(route('auth.unlock.request'), ['email' => $this->real->email])
            ->getSession()->get('success');
        Notification::assertNothingSent();

        $unknownReply = $this->from('/auth/unlock-request')
            ->post(route('auth.unlock.request'), ['email' => 'nobody' . uniqid() . '@example.test'])
            ->getSession()->get('success');

        $this->assertNotSame($knownReply, $unknownReply);
        $this->assertStringContainsString('not locked', (string) $knownReply);
    }

    public function test_forgot_password_does_not_say_whether_an_email_is_registered(): void
    {
        $r = $this->post(route('password.email'), ['email' => 'nobody' . uniqid() . '@example.test']);
        $r->assertRedirect(route('login'));
        $this->assertStringContainsString('If that email belongs to an account', (string) $r->getSession()->get('success'));
    }
}
