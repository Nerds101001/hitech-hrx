<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StandardizeDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:standardize';

    protected $description = 'Standardize document types and sync document numbers to the users table.';

    public function handle()
    {
        $this->info('Starting document standardization...');

        $mappings = [
            'Aadhaar Card' => ['Aadhar', 'Aadhaar', 'AADHAR CARD', 'AAA', 'aadhaar card'],
            'PAN Card' => ['Pan', 'PAN', 'PAN CARD', 'pan card'],
            '10th Marksheet (Matric)' => ['Matric', '10th', '10th Marksheet', 'SSC'],
            '12th Marksheet (Intermediate)' => ['Inter', '12th', '12th Marksheet', 'HSC', 'Intermediate'],
            'Graduation Marksheet' => ['Graduation', 'Bachelor', 'Degree'],
            'Post Graduation Marksheet' => ['Post Graduation', 'Master', 'Masters'],
            'Experience Certificate' => ['Exp Certificate', 'Experience', 'Letter of Experience'],
        ];

        // 1. Merge Redundant Types
        foreach ($mappings as $standardName => $variants) {
            $standardType = DB::table('document_types')->where('name', $standardName)->first();

            if (!$standardType) {
                $id = DB::table('document_types')->insertGetId([
                    'name' => $standardName,
                    'code' => strtoupper(str_replace([' ', '(', ')'], ['_', '', ''], $standardName)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $standardType = (object)['id' => $id, 'name' => $standardName];
                $this->info("Created standard type: $standardName");
            }

            foreach ($variants as $variant) {
                if ($variant === $standardName) continue;

                $variantType = DB::table('document_types')->where('name', $variant)->first();
                if ($variantType) {
                    $this->warn("Merging $variant into $standardName...");
                    DB::table('document_requests')
                        ->where('document_type_id', $variantType->id)
                        ->update(['document_type_id' => $standardType->id]);

                    DB::table('document_types')->where('id', $variantType->id)->delete();
                }
            }
        }

        // 2. Identify Junk and move to "Other"
        // (Simplified for now: keep existing if not mapped, or rename if clearly junk)
        $junkTypes = DB::table('document_types')
            ->whereNotIn('name', array_keys($mappings))
            ->whereNotIn('name', ['Other', 'Bank Passbook', 'Salary Slip', 'NOC'])
            ->get();

        foreach ($junkTypes as $junk) {
            if (strlen($junk->name) < 3 || preg_match('/^[0-9]+$/', $junk->name)) {
                $this->warn("Identified junk type: {$junk->name}. Moving to 'Other'...");
                $otherType = DB::table('document_types')->where('name', 'Other')->first();
                if (!$otherType) {
                    $id = DB::table('document_types')->insertGetId([
                        'name' => 'Other',
                        'code' => 'OTHER',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $otherType = (object)['id' => $id];
                }

                DB::table('document_requests')
                    ->where('document_type_id', $junk->id)
                    ->update(['document_type_id' => $otherType->id]);

                DB::table('document_types')->where('id', $junk->id)->delete();
            }
        }

        // 3. Sync to Users Table
        $fieldMap = [
            'Aadhaar Card' => 'aadhaar_no',
            'PAN Card' => 'pan_no',
            '10th Marksheet (Matric)' => 'matric_marksheet_no',
            '12th Marksheet (Intermediate)' => 'inter_marksheet_no',
            'Graduation Marksheet' => 'bachelor_marksheet_no',
            'Post Graduation Marksheet' => 'master_marksheet_no',
            'Experience Certificate' => 'experience_certificate_no',
        ];

        foreach ($fieldMap as $docTypeName => $userField) {
            $docType = DB::table('document_types')->where('name', $docTypeName)->first();
            if (!$docType) continue;

            $requests = DB::table('document_requests')
                ->where('document_type_id', $docType->id)
                ->whereNotNull('remarks')
                ->get();

            foreach ($requests as $req) {
                DB::table('users')
                    ->where('id', $req->user_id)
                    ->where(function ($query) use ($userField) {
                        $query->whereNull($userField)->orWhere($userField, '');
                    })
                    ->update([$userField => $req->remarks]);
            }
        }

        $this->info('Standardization complete!');
    }
}
