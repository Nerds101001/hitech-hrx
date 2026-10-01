<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDevice;
use App\Support\SafeHtml;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * The mobile app's token used to keep working after its owner left, and the
 * device-ID login (no password) handed out a fresh 28-day token to any phone
 * that had ever registered, for anyone but 'inactive' staff.
 */
class MobileAppAccessTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // The test environment has no JWT_SECRET of its own.
        config(['jwt.secret' => str_repeat('qa-test-secret-', 4)]);

        $this->user = User::where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->where('is_mobile_app_access_enabled', 1))
            ->firstOrFail();
    }

    private function me(string $token)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/V1/account/getAccountStatus');
    }

    private function device(int $idleDays): string
    {
        $uid = 'qa-device-' . uniqid();
        $device = UserDevice::create([
            'user_id' => $this->user->id, 'device_type' => 'android', 'device_id' => $uid,
            'brand' => 'qa', 'board' => 'qa', 'sdk_version' => '34', 'model' => 'qa', 'token' => 'qa',
            'latitude' => 0, 'longitude' => 0,
        ]);
        DB::table('user_devices')->where('id', $device->id)->update(['updated_at' => now()->subDays($idleDays)]);

        return $uid;
    }

    public function test_a_token_works_for_current_staff_and_stops_once_they_leave(): void
    {
        $token = JWTAuth::fromUser($this->user);
        $this->assertNotSame(401, $this->me($token)->status(), 'current staff keep access');

        DB::table('users')->where('id', $this->user->id)->update(['status' => 'terminated']);
        app('auth')->forgetGuards();
        $this->me($token)->assertStatus(401);
    }

    public function test_staff_held_on_onboarding_training_keep_the_app(): void
    {
        DB::table('users')->where('id', $this->user->id)->update(['status' => 'onboarding_submitted']);

        $this->assertNotSame(401, $this->me(JWTAuth::fromUser($this->user))->status());
    }

    public function test_device_login_works_for_a_recently_used_phone(): void
    {
        $res = $this->postJson(route('loginWithUid'), [$this->device(2)]);

        $this->assertNotEmpty($res->json('data.token') ?? $res->json('token'), $res->getContent());
    }

    public function test_device_login_refuses_a_phone_silent_for_over_a_month(): void
    {
        $res = $this->postJson(route('loginWithUid'), [$this->device(31)]);

        $this->assertStringContainsString('sign in with your password', $res->getContent());
        $this->assertStringNotContainsString('"token"', $res->getContent());
    }

    public function test_device_login_refuses_someone_who_has_left(): void
    {
        $uid = $this->device(1);
        DB::table('users')->where('id', $this->user->id)->update(['status' => 'relieved']);

        $res = $this->postJson(route('loginWithUid'), [$uid]);

        $this->assertStringContainsString('not active', $res->getContent());
        $this->assertStringNotContainsString('"token"', $res->getContent());
    }

    public function test_an_embedded_picture_gets_an_empty_alt_not_its_data(): void
    {
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
        $out = SafeHtml::clean('<p><img src="' . $png . '"></p>');

        $this->assertStringContainsString('alt=""', $out);
        $this->assertSame(1, substr_count($out, 'base64'));
        $this->assertStringContainsString('alt="Team photo"', SafeHtml::clean('<img src="' . $png . '" alt="Team photo">'));
    }
}
