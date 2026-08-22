<?php

namespace Tests\Feature;

use App\Console\Commands\MigrateLearnToVideoCommand;
use App\Models\LibraryFile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Add Document validation.
 *
 * Both tabs of the dialog live in one form. The video-link tab's category
 * select is named sub_category_yt specifically so it can never collide with
 * the file tab's own sub_category field regardless of which tab actually
 * gets submitted — these tests post exactly what the browser posts to prove
 * that holds, and that Learn @ Hi Tech (now just a Video category, not its
 * own document type) still gets its fields enforced.
 */
class VaultUploadValidationTest extends TestCase
{
    use DatabaseTransactions;

    private function uploader(): User
    {
        return User::role('admin')->where('email', 'ai@rustx.com')->firstOrFail();
    }

    public function test_comparison_report_uploads_with_the_video_tab_still_serialised(): void
    {
        Mail::fake();
        Storage::fake('r2');

        $title = 'Comparison_Report_135C_Used_vs_Fresh_GKN_' . bin2hex(random_bytes(4));

        $res = $this->actingAs($this->uploader())->post('/digital-library/store', [
            'file'         => UploadedFile::fake()->create("$title.pdf", 120, 'application/pdf'),
            'category'     => 'Comparison Report',
            'brand'        => 'RUST-X',
            'name'         => $title,
            // What the untouched video tab contributes on every file upload:
            'sub_category_yt' => MigrateLearnToVideoCommand::LEARN_CATEGORY,
            'title_yt'        => '',
            'youtube_url'     => '',
            'presenter_id'    => '',
            'session_date'    => '',
        ]);

        $res->assertOk();
        $this->assertStringNotContainsString('Video Title is required', $res->getContent());
        $this->assertDatabaseHas('library_files', ['title' => $title, 'category' => 'Comparison Report']);
    }

    public function test_a_real_learn_session_still_requires_its_fields(): void
    {
        Mail::fake();

        $res = $this->actingAs($this->uploader())->post('/digital-library/store', [
            'youtube_url'  => 'https://youtube.com/watch?v=abc123',
            'sub_category' => MigrateLearnToVideoCommand::LEARN_CATEGORY,
            'title_yt'     => '',
            'presenter_id' => '',
            'session_date' => '',
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('Video Title is required', $res->getContent());
    }

    public function test_a_general_video_link_does_not_demand_learn_fields(): void
    {
        Mail::fake();

        $url = 'https://youtube.com/watch?v=' . bin2hex(random_bytes(4));

        $res = $this->actingAs($this->uploader())->post('/digital-library/store', [
            'youtube_url'  => $url,
            'sub_category' => MigrateLearnToVideoCommand::GENERAL_CATEGORY,
            'title_yt'     => 'Quarterly product walkthrough',
        ]);

        $res->assertOk();
        $this->assertDatabaseHas('library_files', ['youtube_url' => $url, 'category' => 'Video']);
    }

    public function test_video_link_upload_never_gets_a_brand_stamped(): void
    {
        Mail::fake();

        $url = 'https://youtube.com/watch?v=' . bin2hex(random_bytes(4));

        $res = $this->actingAs($this->uploader())->post('/digital-library/store', [
            'youtube_url'  => $url,
            'sub_category' => 'Product Videos',
            'title_yt'     => 'Factory walkthrough',
        ]);

        $res->assertOk();
        $file = LibraryFile::where('youtube_url', $url)->first();
        $this->assertNotNull($file);
        $this->assertNull($file->brand);
        $this->assertSame('Product Videos', $file->sub_category);
    }
}
