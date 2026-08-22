<?php

namespace Tests\Feature;

use App\Models\LibraryFile;
use App\Models\User;
use App\Services\VaultAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Try the AI while the uploader waits; hand slow ones to the backlog.
 *
 * The provider's queue for the large model swings from seconds to minutes. The
 * upload must never fail because of that — it accepts the file, marks the crux
 * pending, and vault:audit-pending finishes the job later.
 */
class VaultAuditFallbackTest extends TestCase
{
    use DatabaseTransactions;

    private function uploader(): User
    {
        return User::role('admin')->where('email', 'ai@rustx.com')->firstOrFail();
    }

    /** A timed-out audit is a queued file, not a failed upload. */
    public function test_analyze_falls_back_to_pending_when_the_ai_times_out(): void
    {
        Mail::fake();

        $this->mock(VaultAuditService::class, function ($mock) {
            $mock->shouldReceive('extractText')->andReturn(str_repeat('technical datasheet text ', 20));
            $mock->shouldReceive('audit')->andReturn([
                'status'  => 'timeout',
                'message' => 'The AI service did not answer in time.',
            ]);
        });

        $res = $this->actingAs($this->uploader())->post('/digital-library/analyze', [
            'file' => UploadedFile::fake()->create('Comparison_Report.pdf', 90, 'application/pdf'),
        ]);

        $res->assertOk();
        $res->assertJson([
            'success' => true,
            'pending' => true,
            'summary' => VaultAuditService::PENDING_SUMMARY,
        ]);
    }

    /** A fast audit still returns the real classification, unchanged. */
    public function test_analyze_returns_the_classification_when_the_ai_is_quick(): void
    {
        Mail::fake();

        $this->mock(VaultAuditService::class, function ($mock) {
            $mock->shouldReceive('extractText')->andReturn(str_repeat('cutting oil comparison ', 20));
            $mock->shouldReceive('audit')->andReturn([
                'status'       => 'ok',
                'brand'        => 'RUST-X',
                'sub_category' => 'Cutting Oil',
                'category'     => 'Comparison Report',
                'name'         => 'Comparison Report 135C',
                'date'         => '2026-08-08',
                'summary'      => 'A real crux.',
            ]);
        });

        $res = $this->actingAs($this->uploader())->post('/digital-library/analyze', [
            'file' => UploadedFile::fake()->create('Comparison_Report.pdf', 90, 'application/pdf'),
        ]);

        $res->assertOk();
        $res->assertJson(['success' => true, 'brand' => 'RUST-X', 'category' => 'Comparison Report']);
        $res->assertJsonMissing(['pending' => true]);
    }

    /** The backlog command classifies a pending file and stops tracking it. */
    public function test_backlog_command_classifies_a_pending_file(): void
    {
        Mail::fake();
        Storage::fake('r2');
        Storage::disk('r2')->put('digital-library/pending.pdf', '%PDF-1.4 fake');

        $file = LibraryFile::create([
            'title'        => 'Pending_' . bin2hex(random_bytes(4)),
            'brand'        => 'HITECH',
            'sub_category' => 'Industrial Assets',
            'file_path'    => 'digital-library/pending.pdf',
            'category'     => 'TDS',
            'mime_type'    => 'application/pdf',
            'size'         => 100,
            'summary'      => VaultAuditService::PENDING_SUMMARY,
            'is_public'    => true,
            'tenant_id'    => 1,
            'created_by_id' => $this->uploader()->id,
        ]);

        $this->mock(VaultAuditService::class, function ($mock) {
            $mock->shouldReceive('extractText')->andReturn('cutting oil comparison text');
            $mock->shouldReceive('audit')->andReturn([
                'status'       => 'ok',
                'brand'        => 'RUST-X',
                'sub_category' => 'Cutting Oil',
                'category'     => 'Comparison Report',
                'name'         => 'Comparison Report',
                'date'         => '2026-08-08',
                'summary'      => 'Extracted crux for the report.',
            ]);
        });

        // --id, because the real library already holds a handful of files with
        // the placeholder and the command works oldest-first.
        $this->artisan('vault:audit-pending', ['--id' => $file->id])->assertExitCode(0);

        $file->refresh();
        $this->assertSame('RUST-X', $file->brand);
        $this->assertSame('Comparison Report', $file->category);
        $this->assertSame('Extracted crux for the report.', $file->summary);
    }

    /** A file that times out again stays pending for the next run. */
    public function test_backlog_leaves_a_still_slow_file_pending(): void
    {
        Mail::fake();
        Storage::fake('r2');
        Storage::disk('r2')->put('digital-library/slow.pdf', '%PDF-1.4 fake');

        $file = LibraryFile::create([
            'title'        => 'Slow_' . bin2hex(random_bytes(4)),
            'brand'        => 'HITECH',
            'sub_category' => 'Industrial Assets',
            'file_path'    => 'digital-library/slow.pdf',
            'category'     => 'TDS',
            'mime_type'    => 'application/pdf',
            'size'         => 100,
            'summary'      => VaultAuditService::PENDING_SUMMARY,
            'is_public'    => true,
            'tenant_id'    => 1,
            'created_by_id' => $this->uploader()->id,
        ]);

        $this->mock(VaultAuditService::class, function ($mock) {
            $mock->shouldReceive('extractText')->andReturn('some text');
            $mock->shouldReceive('audit')->andReturn(['status' => 'timeout', 'message' => 'still slow']);
        });

        $this->artisan('vault:audit-pending', ['--id' => $file->id])->assertExitCode(0);

        $file->refresh();
        $this->assertSame(VaultAuditService::PENDING_SUMMARY, $file->summary, 'must remain queued for a later run');
        $this->assertSame('HITECH', $file->brand, 'nothing should be half-written on a timeout');
    }
}
