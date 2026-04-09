<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Onboarding specific
            $table->timestamp('onboarding_at')->after('status')->nullable();
            $table->timestamp('onboarding_deadline')->after('onboarding_at')->nullable();
            $table->timestamp('onboarding_completed_at')->after('onboarding_deadline')->nullable();
            $table->text('onboarding_resubmission_notes')->after('onboarding_completed_at')->nullable();
            $table->timestamp('consent_accepted_at')->after('onboarding_resubmission_notes')->nullable();

            // Offer details
            $table->decimal('ctc_offered', 15, 2)->after('base_salary')->nullable();
            $table->string('designation_offered')->after('ctc_offered')->nullable();

            // Contact & Personal
            $table->string('home_phone')->after('phone')->nullable();
            $table->string('birth_country')->after('gender')->nullable();
            $table->string('father_name')->after('birth_country')->nullable();
            $table->string('mother_name')->after('father_name')->nullable();
            $table->string('marital_status')->after('mother_name')->nullable();
            $table->string('spouse_name')->after('marital_status')->nullable();
            $table->integer('no_of_children')->after('spouse_name')->nullable();
            $table->json('children_details')->after('no_of_children')->nullable();
            $table->string('citizenship')->after('children_details')->nullable();
            $table->string('blood_group')->after('citizenship')->nullable();

            // Addresses
            $table->string('perm_street')->after('address')->nullable();
            $table->string('perm_building')->after('perm_street')->nullable();
            $table->string('perm_zip')->after('perm_building')->nullable();
            $table->string('perm_city')->after('perm_zip')->nullable();
            $table->string('perm_state')->after('perm_city')->nullable();
            $table->string('perm_country')->after('perm_state')->nullable();

            $table->string('temp_street')->after('perm_country')->nullable();
            $table->string('temp_building')->after('temp_street')->nullable();
            $table->string('temp_zip')->after('temp_building')->nullable();
            $table->string('temp_city')->after('temp_zip')->nullable();
            $table->string('temp_state')->after('temp_city')->nullable();
            $table->string('temp_country')->after('temp_state')->nullable();

            // Identities
            $table->string('passport_no')->after('temp_country')->nullable();
            $table->date('passport_issue_date')->after('passport_no')->nullable();
            $table->date('passport_expiry_date')->after('passport_issue_date')->nullable();
            $table->string('visa_type')->after('passport_expiry_date')->nullable();
            $table->date('visa_issue_date')->after('visa_type')->nullable();
            $table->date('visa_expiry_date')->after('visa_issue_date')->nullable();
            $table->string('frro_registration')->after('visa_expiry_date')->nullable();
            $table->date('frro_issue_date')->after('frro_registration')->nullable();
            $table->date('frro_expiry_date')->after('frro_issue_date')->nullable();
            $table->string('aadhaar_no')->after('frro_expiry_date')->nullable();
            $table->string('pan_no')->after('aadhaar_no')->nullable();

            // Emergency
            $table->string('emergency_contact_name')->after('pan_no')->nullable();
            $table->string('emergency_contact_relation')->after('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->after('emergency_contact_relation')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'onboarding_at', 'onboarding_deadline', 'onboarding_completed_at', 'onboarding_resubmission_notes', 'consent_accepted_at',
                'ctc_offered', 'designation_offered', 'home_phone', 'birth_country',
                'father_name', 'mother_name', 'marital_status', 'spouse_name', 'no_of_children', 'children_details', 'citizenship', 'blood_group',
                'perm_street', 'perm_building', 'perm_zip', 'perm_city', 'perm_state', 'perm_country',
                'temp_street', 'temp_building', 'temp_zip', 'temp_city', 'temp_state', 'temp_country',
                'passport_no', 'passport_issue_date', 'passport_expiry_date',
                'visa_type', 'visa_issue_date', 'visa_expiry_date',
                'frro_registration', 'frro_issue_date', 'frro_expiry_date',
                'aadhaar_no', 'pan_no',
                'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone'
            ]);
        });
    }
};
