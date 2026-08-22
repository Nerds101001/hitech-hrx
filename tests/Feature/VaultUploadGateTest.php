<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Digital Library upload controls.
 *
 * The routes behind Add Document / AI Bulk Upload have always accepted the
 * vault.upload permission, but the page gated the buttons on the admin/HR role
 * alone — so granting a manager vault.upload changed nothing they could see.
 */
class VaultUploadGateTest extends TestCase
{
    use DatabaseTransactions;

    private function manager(): User
    {
        return User::role('manager')
            ->where('status', \App\Enums\UserAccountStatus::ACTIVE)
            ->orderBy('id')
            ->firstOrFail();
    }

    public function test_manager_granted_vault_upload_sees_the_upload_controls(): void
    {
        Mail::fake();
        Permission::findOrCreate('vault.upload', 'web');

        $manager = $this->manager();
        $manager->givePermissionTo('vault.upload');
        $manager->forgetCachedPermissions();

        $html = $this->actingAs($manager)->get('/digital-library')->assertOk()->getContent();

        $this->assertStringContainsString('Add Document', $html);
        $this->assertStringContainsString('AI Bulk Upload', $html);
    }

    public function test_manager_without_the_permission_does_not(): void
    {
        Mail::fake();

        $manager = $this->manager();
        $manager->revokePermissionTo('vault.upload');
        $manager->forgetCachedPermissions();

        $html = $this->actingAs($manager)->get('/digital-library')->assertOk()->getContent();

        $this->assertStringNotContainsString('AI Bulk Upload', $html);
        $this->assertStringNotContainsString("openAddDocumentModal('", $html);
    }
}
