<?php

namespace Tests\Feature;

use App\Enums\UserAccountStatus;
use App\Models\LibraryFile;
use App\Models\TrainingPhase;
use App\Models\TrainingVideoView;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The Learn @ Hi Tech training phase lists the Digital Library's Learn @ Hi Tech
 * videos live. Each needs a minimum watch time (enforced by the server), and
 * the phase is complete after any N of them - not all.
 */
class TrainingLibraryVideosTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private TrainingPhase $phase;
    private string $sub;
    private array $videos = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->user = User::create([
            'first_name' => 'QA', 'last_name' => 'Viewer',
            'email' => 'qaviewer' . uniqid() . '@example.test', 'code' => 'QV' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'), 'status' => UserAccountStatus::ACTIVE->value,
        ]);
        $this->user->assignRole('employee');

        // Its own phase, ordered first so no earlier module stands in the way.
        $this->phase = TrainingPhase::create(['title' => 'ZZ Learn Phase ' . uniqid(), 'order' => -100]);
        $this->sub = 'ZZ Learn ' . uniqid();
        config([
            'training.library_video_phase_ids' => [$this->phase->id],
            'training.library_video_subcategory' => $this->sub,
            'training.library_videos_required' => 3,
            'training.library_video_min_minutes' => 20,
            'training.library_video_prerequisite_phase_ids' => [],
        ]);

        foreach (range(1, 4) as $i) {
            $this->videos[] = $this->video([
                'title' => "ZZ Session $i", 'sub_category' => $this->sub,
                'youtube_url' => "https://drive.google.com/file/d/ZZdrive{$i}abc/view?usp=sharing",
            ]);
        }
    }

    private function video(array $a): LibraryFile
    {
        return LibraryFile::create($a + ['category' => 'Video', 'mime_type' => 'video/link', 'size' => 0,
            'created_by_id' => $this->user->id, 'is_public' => true]);
    }

    /** Pretend this video was opened $mins minutes ago. */
    private function openedAgo(LibraryFile $v, int $mins): void
    {
        $this->actingAs($this->user)->get(route('training.library-video.show', $v->id))->assertOk();
        TrainingVideoView::where('user_id', $this->user->id)->where('library_file_id', $v->id)
            ->update(['started_at' => now()->subMinutes($mins)]);
    }

    public function test_the_phase_lists_the_library_videos_live(): void
    {
        $html = $this->actingAs($this->user)->get(route('training.portal'))->assertOk()->getContent();

        foreach ($this->videos as $v) {
            $this->assertStringContainsString(e($v->title), $html);
            $this->assertStringContainsString(route('training.library-video.show', $v->id), $html);
        }
        $this->assertStringContainsString('0/3 watched', $html);

        // Added to the library later: in training straight away, nothing copied.
        $this->video(['title' => 'ZZ Brand New Session', 'sub_category' => $this->sub,
            'youtube_url' => 'https://drive.google.com/file/d/ZZnew/view']);
        $this->assertStringContainsString('ZZ Brand New Session', $this->actingAs($this->user)->get(route('training.portal'))->getContent());
    }

    public function test_the_video_plays_from_its_drive_link(): void
    {
        $this->actingAs($this->user)->get(route('training.library-video.show', $this->videos[0]->id))
            ->assertOk()->assertSee('https://drive.google.com/file/d/ZZdrive1abc/preview', false);
    }

    public function test_marking_watched_before_the_minimum_time_is_refused(): void
    {
        $this->openedAgo($this->videos[0], 5);

        $this->actingAs($this->user)->postJson(route('training.library-video.watched', $this->videos[0]->id))
            ->assertStatus(403)->assertJsonPath('seconds_left', fn ($s) => $s > 14 * 60);
        $this->assertNull(TrainingVideoView::where('user_id', $this->user->id)->value('completed_at'));
    }

    public function test_a_video_never_opened_cannot_be_marked_watched(): void
    {
        $this->actingAs($this->user)->postJson(route('training.library-video.watched', $this->videos[0]->id))->assertStatus(403);
    }

    public function test_any_three_watched_complete_the_phase(): void
    {
        foreach (array_slice($this->videos, 0, 3) as $i => $v) {
            $this->openedAgo($v, 25);
            $res = $this->actingAs($this->user)->postJson(route('training.library-video.watched', $v->id))->assertOk();
            $this->assertSame($i + 1, $res->json('watched'));
        }
        $this->assertStringContainsString('completes the Learn', $res->json('message'));

        $html = $this->actingAs($this->user)->get(route('training.portal'))->getContent();
        $this->assertStringContainsString('Complete', $html);
    }

    public function test_a_video_outside_the_learn_category_is_not_served(): void
    {
        $other = $this->video(['title' => 'ZZ Other', 'sub_category' => 'Cleaners',
            'youtube_url' => 'https://drive.google.com/file/d/ZZother/view']);

        $this->actingAs($this->user)->get(route('training.library-video.show', $other->id))->assertNotFound();
    }

    public function test_an_admin_can_put_the_videos_in_any_order_and_trainees_see_that_order(): void
    {
        $admin = User::create([
            'first_name' => 'QA', 'last_name' => 'Admin', 'email' => 'qaadm' . uniqid() . '@example.test',
            'code' => 'QA' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => UserAccountStatus::ACTIVE->value,
        ]);
        $admin->assignRole('admin');

        // Oldest first, and one id that is not a Learn video (ignored).
        $wanted = [$this->videos[3]->id, $this->videos[1]->id, $this->videos[0]->id, $this->videos[2]->id];
        $this->actingAs($admin)->postJson(route('training.manage.library-videos.order'), ['ids' => array_merge($wanted, ['not-a-video'])])
            ->assertOk()->assertJson(['success' => true, 'saved' => 4]);

        $this->assertSame($wanted, \App\Http\Controllers\tenant\TrainingController::libraryVideos()->pluck('id')->all());

        $html = $this->actingAs($this->user)->get(route('training.portal'))->getContent();
        $at = fn ($v) => strpos($html, route('training.library-video.show', $v->id));
        $this->assertTrue($at($this->videos[3]) < $at($this->videos[1]) && $at($this->videos[1]) < $at($this->videos[0]) && $at($this->videos[0]) < $at($this->videos[2]),
            'the portal lists them in the saved order');

        // A session added later goes after the arranged ones.
        $new = $this->video(['title' => 'ZZ Later', 'sub_category' => $this->sub, 'youtube_url' => 'https://drive.google.com/file/d/ZZlater/view']);
        $this->assertSame($new->id, \App\Http\Controllers\tenant\TrainingController::libraryVideos()->last()->id);
    }

    public function test_only_the_prerequisite_phases_must_be_finished_not_every_earlier_one(): void
    {
        // Two earlier phases, each with a module: only the first is a prerequisite.
        $pre = TrainingPhase::create(['title' => 'ZZ Pre ' . uniqid(), 'order' => -300]);
        $other = TrainingPhase::create(['title' => 'ZZ Other ' . uniqid(), 'order' => -200]);
        $mk = fn ($ph) => \App\Models\TrainingModule::create(['phase_id' => $ph->id, 'title' => 'ZZ mod ' . uniqid(),
            'content_type' => 'policy', 'estimated_time_minutes' => 1, 'order' => 1]);
        $preMod = $mk($pre);
        $mk($other);   // like the CRM module: never passed
        config(['training.library_video_prerequisite_phase_ids' => [$pre->id]]);

        // Prerequisite not done: the video is refused and the portal shows it locked.
        $this->actingAs($this->user)->get(route('training.library-video.show', $this->videos[0]->id))
            ->assertRedirect(route('training.portal'));
        $this->assertStringContainsString('to unlock these videos', $this->actingAs($this->user)->get(route('training.portal'))->getContent());

        // Prerequisite passed, the other earlier phase still not: open.
        \App\Models\UserTrainingProgress::create(['user_id' => $this->user->id, 'module_id' => $preMod->id, 'status' => 'completed', 'completed_at' => now()]);
        $this->actingAs($this->user)->get(route('training.library-video.show', $this->videos[0]->id))->assertOk();
        $this->assertStringContainsString(route('training.library-video.show', $this->videos[0]->id),
            $this->actingAs($this->user)->get(route('training.portal'))->getContent());
    }

    public function test_an_ordinary_employee_cannot_change_the_order(): void
    {
        $this->actingAs($this->user)->postJson(route('training.manage.library-videos.order'), ['ids' => [$this->videos[0]->id]])
            ->assertStatus(403);
    }
}
