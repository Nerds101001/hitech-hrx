<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-identity fields copied down from tally_ledgers (GSTIN/PAN/contact/email/address/mailing name)
 * onto the matched Customer the moment a mapping is verified -- see CustomerTallyMappingController::
 * mapTally(). Prefixed tally_ because they're Tally's record of these details, not independently
 * editable here; re-verifying (or the backfill command) overwrites them from the current tally_ledgers
 * snapshot. Added per explicit request (2026-10-29): once mapped, the customer's name/contact/GST/PAN
 * should reflect Tally, not stay blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->string('tally_mailing_name')->nullable()->after('tally_matched_name');
            $t->string('tally_gstin', 20)->nullable()->after('tally_mailing_name');
            $t->string('tally_pan', 20)->nullable()->after('tally_gstin');
            $t->string('tally_contact_person')->nullable()->after('tally_pan');
            $t->string('tally_contact_mobile')->nullable()->after('tally_contact_person');
            $t->string('tally_email')->nullable()->after('tally_contact_mobile');
            $t->text('tally_address')->nullable()->after('tally_email');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->dropColumn(['tally_mailing_name', 'tally_gstin', 'tally_pan', 'tally_contact_person', 'tally_contact_mobile', 'tally_email', 'tally_address']);
        });
    }
};
