<?php

namespace Database\Seeders;

use App\Models\LibraryFile;
use Illuminate\Database\Seeder;

/**
 * 17 originally-shared Learn @ HiTech video links plus 5 additional links and
 * 1 companion PDF found in a later cross-referenced transcript-analysis file,
 * reconciled against that analysis for real topic/presenter/date. Two items
 * (Aquaflush/Zorbit - Navin Joshi, Combating Corrosion - Oswald Anthony) had
 * a transcript-matching error in that source (wrong topic borrowed from a
 * different video) -- topic/presenter corrected from the filenames per the
 * user directly, date kept from the source file. Three presenters (Udaysinh
 * Gade, Gaurav Nayyar, and the unnamed "Team Discussion") have no registered
 * account, so presenter_id is left null and their name is kept in the title.
 */
class AddLearnHiTechSessionsSeeder extends Seeder
{
    private const CREATED_BY = 9; // Nagender Kumar (Admin)

    public function run(): void
    {
        $rows = [
            ['url' => 'https://drive.google.com/file/d/10uAYEIWKSdJRKFhnRv9gO4Ht58e41u8z/view', 'title' => 'Aquaflush and Zorbit', 'presenter_id' => 375, 'presenter_name' => 'Navin Joshi', 'date' => '2025-08-23', 'summary' => 'Product session on Aquaflush cleaners and Zorbit desiccant range, presented by Navin Joshi.'],
            ['url' => 'https://drive.google.com/file/d/12jtOe9dyJaa24DAM-pa_qJv7cWhMKgFk/view', 'title' => 'Pump Preservation', 'presenter_id' => 407, 'presenter_name' => 'Daniel Raj Diviyanandam', 'date' => '2025-09-13', 'summary' => 'Pump preservation techniques, the global water pump market, and common preservation challenges like corrosion and rusting after hydro testing and painting. Covered Neutra Safe liquid, 135K oil, and VCI products for export packaging, with Ekki Homa and Ranser Pump case studies.'],
            ['url' => 'https://drive.google.com/file/d/14pb-sHlfQwjfVVFEQtuxyu-wpXr2lRyM/view', 'title' => 'Handling Corrosion Complaints', 'presenter_id' => 430, 'presenter_name' => 'Vinay Pal Singh', 'date' => '2025-10-25', 'summary' => 'Handling customer corrosion complaints with an empathetic and technical approach -- conducting corrosion audits, metalworking fluid maintenance best practices, sealed packaging, dewatering agents, and cleaning media quality.'],
            ['url' => 'https://drive.google.com/file/d/1FvbrLjZ1jbts6X5LzoT8sahIoN0JngOc/view', 'title' => 'Engine Protection During Export', 'presenter_id' => 406, 'presenter_name' => 'Ashoka BM', 'date' => '2025-08-30', 'summary' => 'Engine protection during export -- corrosion challenges, specialized packaging for heavy machinery (generators, turbines), VCI film application, and case studies including Caterpillar.'],
            ['url' => 'https://drive.google.com/file/d/1J5_KAGsK0pvtk069R9zryA6zkcUsEGZC/view', 'title' => 'Role of Rust Preventives in Seven Tank Process', 'presenter_id' => 400, 'presenter_name' => 'Sadanand Sharad Gore', 'date' => '2025-07-26', 'summary' => 'Rust preventives in the seven-tank cleaning process (degreasing, derusting, phosphating, passivation). Nelson Global case study: rusty incoming material, root-cause investigation, filtration and pH monitoring fixes.'],
            ['url' => 'https://drive.google.com/file/d/1N9Q1k7wI68G2KvSZAl2W8Ps3i0_VpleW/view', 'title' => "Ethylene's Impact on Fresh Produce & Scrubbers", 'presenter_id' => 425, 'presenter_name' => 'Pradeep Vishwakarma', 'date' => '2025-10-04', 'summary' => "Ethylene's impact on fresh produce spoilage, measurement and reduction methods, Keep It Fresh ethylene scrubber types and applications, shelf-life benefits, and customer success stories."],
            ['url' => 'https://drive.google.com/file/d/1PnHcSLwRvHdkPAPdgymhl-IpenmP-7LO/view', 'title' => 'O2 and CO2 Absorbers', 'presenter_id' => 477, 'presenter_name' => 'Govind Yadav', 'date' => '2026-04-25', 'summary' => 'O2 and CO2 absorbers -- packaging challenges addressed, up to 99.99% oxygen removal, food-grade material safety, and dosage trial requirements for specific product environments.'],
            ['url' => 'https://drive.google.com/file/d/1UcPGLbP1PBHuGKueUu-2JQ6DNm3jEm3K/view', 'title' => 'Sales Strategies & Professional Communication', 'presenter_id' => 432, 'presenter_name' => 'Oswald Anthony', 'date' => '2026-05-09', 'summary' => 'Core sales strategies and professional, documented communication practices -- pricing, competition, and customer trust as primary obstacles; structured documentation for accountability and conflict resolution.'],
            ['url' => 'https://drive.google.com/file/d/1UnduxO-3f84D5797McX5tZSgUrzVBa3f/view', 'title' => 'EVA (Ethylene-Vinyl Acetate) Applications', 'presenter_id' => 404, 'presenter_name' => 'Karthikeyan D', 'date' => '2025-11-01', 'summary' => 'EVA rubber applications across auto, plastic, and solar panel industries; IMX EVA carrier bag, melting point grades (IMX 73/83), bag size/color options, dual function as raw-material carrier and reclaimed-rubber export packaging.'],
            ['url' => 'https://drive.google.com/file/d/1Ur7iiKRUXDDjyYH1CJNQY5LjRfbxEEBp/view', 'title' => "Ethylene's Impact on Fresh Produce & Scrubbers", 'presenter_id' => 425, 'presenter_name' => 'Pradeep Vishwakarma', 'date' => '2025-10-04', 'summary' => "Ethylene's impact on fresh produce spoilage and Keep It Fresh ethylene scrubber applications (companion recording)."],
            ['url' => 'https://drive.google.com/file/d/1W9WpQ0sLEZ9fbqZdI7lcuX-78zM1qbNF/view', 'title' => 'KIF Sachets and LYF Coat Training', 'presenter_id' => 477, 'presenter_name' => 'Govind Yadav', 'date' => '2025-06-28', 'summary' => "How ethylene accelerates fruit ripening and how KIF sachets absorb ethylene/moisture to extend shelf life during export. Introduced 'Life Coat' (Coco Coat), an FSSAI-certified edible coating for fruits and vegetables."],
            ['url' => 'https://drive.google.com/file/d/1XCkduEQ0VVBMtnof8H0739qGElVku65s/view', 'title' => 'Connection Between Mind & Body (Senses Gateway) -- Team Discussion', 'presenter_id' => null, 'presenter_name' => 'Team Discussion', 'date' => '2025-12-27', 'summary' => 'Psychological connection between mind and body -- staying grounded in the present moment via mindfulness, introductory session before deeper organizational psychology topics.'],
            ['url' => 'https://drive.google.com/file/d/1ZWUtlDbAKpcuIjAx2RQvmtJnum2c2C7R/view', 'title' => 'Rustex Specialized Lubricants / Amplify Range', 'presenter_id' => 178, 'presenter_name' => 'Mukul Sareen', 'date' => '2025-08-23', 'summary' => 'Amplify range for metalworking -- cutting, punching, stamping, and heavy-duty oils. Black-trace mitigation for Amplify 70,000, Neat Cut 22 XP customer feedback, Aquaflush cleaners, and non-chlorine forming oils for stainless steel.'],
            ['url' => 'https://drive.google.com/file/d/1_zN2AFW3aSuZYMDSUxUBlI-79n_8uURw/view', 'title' => 'O2 and CO2 Absorbers (Dr. Bio)', 'presenter_id' => 477, 'presenter_name' => 'Govind Yadav', 'date' => '2026-04-25', 'summary' => 'O2 and CO2 absorbers focused on Dr. Bio applications (companion recording).'],
            ['url' => 'https://drive.google.com/file/d/1cv3eGX9ZU9TtxQ5HJB7VWokBjEVa5Ad0/view', 'title' => 'Dry Packaging (Rustics) -- Udaysinh Gade', 'presenter_id' => null, 'presenter_name' => 'Udaysinh Gade', 'date' => '2025-09-27', 'summary' => 'Dry packaging science -- corrosion, inhibitors, VCI technology mechanics. CL008, Outer 3D metal barrier VCI bag, Rustoam, and VCI papers, with environmental precautions for effective use.'],
            ['url' => 'https://drive.google.com/file/d/1i0rgCm68PG5kLcPRBqh4zmxqOEZH9TeX/view', 'title' => 'Customer Needs, Profiling & Active Listening', 'presenter_id' => 423, 'presenter_name' => 'Surinder Oberoi', 'date' => '2025-09-20', 'summary' => 'Understanding customer needs through profiling and active listening -- pre-meeting preparation, long-term trust building, handling technical questions, price objections, and re-engaging unresponsive customers.'],
            ['url' => 'https://drive.google.com/file/d/1kGx9FUssrOBahhXUnjZkLu6whxn24IfK/view', 'title' => 'Rustex Protip Technical Capabilities (with Rajendra Singh)', 'presenter_id' => 477, 'presenter_name' => 'Rajendra Singh & Govind Yadav', 'date' => '2026-05-02', 'summary' => "Rustex Protip's technical specifications -- corrosion protection and residue-free removal vs standard stretch film -- with sales role-play on cost and transit-damage objections. Co-presented with Rajendra Singh."],
            ['url' => 'https://drive.google.com/file/d/1mrNKs5NR5koPYDgE0-1rSbXRF1fq1hXR/view', 'title' => 'Rustex Specialized Lubricants / Amplify Range (recording 2)', 'presenter_id' => 178, 'presenter_name' => 'Mukul Sareen', 'date' => '2025-08-23', 'summary' => 'Amplify range for metalworking (second recording/export of this session).'],
            ['url' => 'https://drive.google.com/file/d/1nJgRe5ixeWCJjx0hu-KkHFgxVRyUMBDV/view', 'title' => 'KIF Sachets and LYF Coat Training -- Meeting Notes (PDF)', 'presenter_id' => 477, 'presenter_name' => 'Govind Yadav', 'date' => '2025-06-28', 'summary' => 'Written notes companion to the KIF Sachets and LYF Coat Training video session.'],
            ['url' => 'https://drive.google.com/file/d/1nKq6b3rY5g2mBNp4-x65UCU2vSf3PBkr/view', 'title' => 'Stamping and Punching Oils -- Gaurav Nayyar', 'presenter_id' => null, 'presenter_name' => 'Gaurav Nayyar', 'date' => '2025-07-19', 'summary' => "The stamping process, market, and lubricant selection -- black traces on components, pre-plated metal stamping issues. Rustex's evap, FinX, and FormX series as solutions."],
            ['url' => 'https://drive.google.com/file/d/1oRzetegBEmMeUgrtGTJmSZTG1fepvuqr/view', 'title' => 'Flower Power Floral Preservation', 'presenter_id' => 344, 'presenter_name' => 'Heena', 'date' => '2026-04-04', 'summary' => 'Flower Power floral preservation additive -- keeps petals bright and stems hydrated via nutrition and antimicrobial control. Blend 13 outperformed competitors, extending flower life up to 5x.'],
            ['url' => 'https://drive.google.com/file/d/1sXwIzS6WWvVSf_nDHYykU6nR__MuElZM/view', 'title' => 'KIF Sachets For Banana Training', 'presenter_id' => 425, 'presenter_name' => 'Pradeep Vishwakarma', 'date' => '2025-10-04', 'summary' => "Ethylene's impact on fresh produce and KIF ethylene scrubbers, focused on banana applications."],
            ['url' => 'https://drive.google.com/file/d/1uLeFksGRmOY0N_1v0PDZ397-iC0maHLL/view', 'title' => 'Combating Corrosion through Strategic Audit', 'presenter_id' => 432, 'presenter_name' => 'Oswald Anthony', 'date' => '2025-09-27', 'summary' => 'Strategic corrosion audits as a methodology for combating corrosion in customer accounts, presented by Oswald Anthony.'],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $exists = LibraryFile::where('youtube_url', $row['url'])->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            LibraryFile::create([
                'title' => $row['title'],
                'brand' => 'HITECH',
                'sub_category' => 'Learn @ HiTech',
                'category' => 'LEARN',
                'youtube_url' => $row['url'],
                'file_path' => null,
                'mime_type' => 'video/youtube',
                'size' => 0,
                'summary' => $row['summary'],
                'is_public' => true,
                'tenant_id' => null,
                'created_by_id' => self::CREATED_BY,
                'presenter_id' => $row['presenter_id'],
                'session_date' => $row['date'],
            ]);
            $created++;
        }

        $this->command->info("AddLearnHiTechSessionsSeeder: created {$created}, skipped {$skipped} (already existed).");
    }
}
