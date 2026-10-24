<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\BirthdayWishNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The Employees -> Celebrations page (/employees/celebrations) only ever had
 * Email/WhatsApp buttons for today's birthdays/anniversaries - it never had
 * the in-app "Send Wish" option that the Admin/HR Dashboard's own Celebrations
 * card has (BirthdayController::sendWish, via openSendWishModal). Added here
 * too, alongside the existing buttons (owner's call, 2026-10-24).
 */
class CelebrationsInAppWishTest extends TestCase
{
    use DatabaseTransactions;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->viewer = User::role('admin')->where('status', 'active')->firstOrFail();
    }

    private function makeCelebrant(): User
    {
        return User::create([
            'first_name' => 'Celeb', 'last_name' => 'Test', 'email' => 'celeb' . uniqid() . '@example.test',
            'code' => 'CEL' . random_int(100000, 999999), 'password' => bcrypt('unused'), 'status' => 'active',
            'dob' => Carbon::today()->subYears(30)->toDateString(),
            'date_of_joining' => Carbon::today()->subYears(3)->toDateString(),
            'phone' => '9' . random_int(100000000, 999999999),
        ]);
    }

    public function test_todays_birthday_and_anniversary_offer_an_in_app_wish_button(): void
    {
        $celebrant = $this->makeCelebrant();

        $html = $this->actingAs($this->viewer)->get(route('employee.celebrations'))->assertOk()->getContent();

        $this->assertStringContainsString(
            "openSendWishModal(this.dataset.uid, this.dataset.uname, 'birthday')",
            $html,
            'the birthday card offers an in-app wish button'
        );
        $this->assertStringContainsString(
            "openSendWishModal(this.dataset.uid, this.dataset.uname, 'anniversary')",
            $html,
            'the anniversary card offers an in-app wish button'
        );
        // Still has the original Email/WhatsApp buttons - this is additive, not a replacement.
        $this->assertStringContainsString('mailto:' . $celebrant->email, $html);
        $this->assertStringContainsString('wa.me/', $html);
    }

    public function test_an_already_sent_in_app_wish_shows_wished_not_the_button_again(): void
    {
        $celebrant = $this->makeCelebrant();

        \Illuminate\Support\Facades\DB::table('notifications')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => BirthdayWishNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $celebrant->id,
            'data' => json_encode(['sender_id' => $this->viewer->id]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $html = $this->actingAs($this->viewer)->get(route('employee.celebrations'))->assertOk()->getContent();
        $this->assertStringContainsString('Wished in-app', $html);
    }

    /**
     * makeCelebrant()'s dob and date_of_joining are both "today", so this one
     * person is the SAME User object in both $birthdays and $anniversaries
     * (both built from one $users collection in EmployeeController::celebrations()).
     * Wishing only the birthday must not also mark the anniversary wished, and
     * vice versa - a shared "is_wished" property name on that shared object did
     * exactly that (set true in the birthday pass, silently overwritten false by
     * the anniversary pass running after it, or the reverse depending on which
     * ran last). Fixed with separate is_wished_birthday / is_wished_anniversary
     * properties.
     */
    public function test_wishing_the_birthday_does_not_mark_the_same_days_anniversary_wished_too(): void
    {
        $celebrant = $this->makeCelebrant();

        \Illuminate\Support\Facades\DB::table('notifications')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => BirthdayWishNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $celebrant->id,
            'data' => json_encode(['sender_id' => $this->viewer->id]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $html = $this->actingAs($this->viewer)->get(route('employee.celebrations'))->assertOk()->getContent();

        $this->assertStringContainsString('Wished in-app', $html, 'the birthday shows as wished');
        $this->assertStringContainsString(
            "openSendWishModal(this.dataset.uid, this.dataset.uname, 'anniversary')",
            $html,
            'the anniversary wish button is still offered - wishing the birthday did not wish it too'
        );
    }
}
