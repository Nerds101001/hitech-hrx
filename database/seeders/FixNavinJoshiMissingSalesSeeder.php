<?php

namespace Database\Seeders;

use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use Illuminate\Database\Seeder;

/**
 * 28 customers present in the source file (SHALLU SHARMA\Navin joshi sale
 * report 18-7-26.xlsx) with real April/May/June 26-27 sale figures never
 * made it into sales_pipelines at all -- accounting for the ~16.7L gap
 * between the file's June total (208.34) and the DB's (191.61). All 28 are
 * tagged "DIPIKA" (New Biz) in the source file's own NEW BIZ/CCARE column.
 */
class FixNavinJoshiMissingSalesSeeder extends Seeder
{
    private const SALESPERSON_ID = 375; // Navin Chandra Joshi
    private const NEW_BIZ_ID = 250;     // Dipika Das

    public function run(): void
    {
        $rows = [
            ['party' => 'DHRUV INDUSTRIES', 'potential' => 1.5, 'city' => 'MAHARASHTRA', 'type' => 'Customer', 'april' => 0.21, 'may' => 0.21, 'june' => 0.42],
            ['party' => 'INFINIUM DOORS & WINDOWS', 'potential' => 1, 'city' => 'MAHARASHTRA', 'type' => 'Customer', 'april' => null, 'may' => 0.31, 'june' => null],
            ['party' => 'CASTPRO INDIA PRIVATE LIMITED', 'potential' => 2, 'city' => 'AURANGABAD', 'type' => 'Customer', 'april' => 0.05, 'may' => null, 'june' => null],
            ['party' => 'SYRMA SGS TECHNOLOGY', 'potential' => 1, 'city' => 'GUJARAT', 'type' => 'Customer', 'april' => 0.38, 'may' => null, 'june' => null],
            ['party' => 'CLASSIC PRODUCTS PVT.LTD (ANDHRA PRADESH)', 'potential' => 1, 'city' => 'TAMIL NADU', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => 3.29],
            ['party' => 'ALEX EXTRUSIONS LIMITED', 'potential' => 1, 'city' => 'HARIDWAAR', 'type' => 'Customer', 'april' => null, 'may' => 1.41, 'june' => null],
            ['party' => 'CONTINENTAL ENGINEERING & POWER', 'potential' => 1, 'city' => 'HIMACHAL', 'type' => 'Customer', 'april' => 0.04, 'may' => 0.01, 'june' => null],
            ['party' => 'YES TECNO LIMITED', 'potential' => 2, 'city' => 'MAHARASHTRA', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => 0.31],
            ['party' => 'TREVENI ELECTROPLAST PVT LTD', 'potential' => 1, 'city' => 'NOIDA', 'type' => 'Customer', 'april' => null, 'may' => 1.9, 'june' => null],
            ['party' => 'CRESCENT FOUNDRY CO.PVT LTD', 'potential' => 1, 'city' => 'MAHARASHTRA', 'type' => 'Customer', 'april' => 0.56, 'may' => null, 'june' => null],
            ['party' => 'DBM AUTOMOTIVE PVT LTD', 'potential' => 1, 'city' => 'MAHRASHTRA', 'type' => 'Customer', 'april' => 0.37, 'may' => null, 'june' => 0.37],
            ['party' => 'KROSS LIMITED', 'potential' => 1, 'city' => 'JAMSHEDPUR', 'type' => 'Customer', 'april' => 0.81, 'may' => 0.36, 'june' => 3.27],
            ['party' => 'RATNAMANI FINOW SPOOLING', 'potential' => 1, 'city' => 'GUJARAT', 'type' => 'Customer', 'april' => 0.21, 'may' => null, 'june' => 0.32],
            ['party' => 'STACK PACK LTD', 'potential' => 1, 'city' => 'GUJARAT', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => 0.1],
            ['party' => 'KRYPTON INDUSTRIES', 'potential' => 1, 'city' => 'AASAM', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => 0.09],
            ['party' => 'NEESHAN INDIA', 'potential' => 1, 'city' => 'MAHARASHTRA', 'type' => 'Customer', 'april' => null, 'may' => 0.29, 'june' => null],
            ['party' => 'KAPIL ENTERPRISES PVT.LTD', 'potential' => 1, 'city' => 'MAHARASHTRA', 'type' => 'Customer', 'april' => null, 'may' => 0.12, 'june' => null],
            ['party' => 'SUZLON ENERGY LIMITED (PONDICHERRY)', 'potential' => 1, 'city' => 'PONDICHERRY', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => 1.72],
            ['party' => 'URJA IMPEX', 'potential' => 1, 'city' => 'MAHARASHTRA', 'type' => 'Customer', 'april' => 1.07, 'may' => null, 'june' => 2.34],
            ['party' => 'RSR AVIATION SERVICES PVT.LTD', 'potential' => 1, 'city' => 'KARNATAKA', 'type' => 'Customer', 'april' => null, 'may' => 6.31, 'june' => null],
            ['party' => 'SIGNODE INDIA (TELANGANA)', 'potential' => 1, 'city' => 'TELANGANGA', 'type' => 'Dealer', 'april' => null, 'may' => null, 'june' => 3.29],
            ['party' => 'SLPYNE PACKING SOLUTIONS', 'potential' => 1, 'city' => 'WEST BENGAL', 'type' => 'Customer', 'april' => 1.78, 'may' => 2.14, 'june' => null],
            ['party' => 'PATIDAR WOODEN WORKS', 'potential' => 1, 'city' => 'ANDHARA PRADESH', 'type' => 'Customer', 'april' => null, 'may' => 0.02, 'june' => null],
            ['party' => 'INFRAFUSION ENGINEERING LIMITED', 'potential' => 1, 'city' => 'MAHARASHTRA', 'type' => 'Customer', 'april' => 0.93, 'may' => null, 'june' => 0.21],
            ['party' => 'PRAJ GENX LIMITED', 'potential' => 1, 'city' => 'KARNATAKA', 'type' => 'Customer', 'april' => 0.45, 'may' => null, 'june' => 0.16],
            ['party' => 'OVERSEAS SADDLERY', 'potential' => 1, 'city' => 'KANPUR', 'type' => 'Customer', 'april' => null, 'may' => 0.39, 'june' => 0.28],
            ['party' => 'BHARAT DYNAMICS LIMITED (A GOVT. OF INDIA)', 'potential' => 1, 'city' => 'TELANGANA', 'type' => 'Customer', 'april' => null, 'may' => 0.51, 'june' => null],
            ['party' => 'SONALIKA INDUSTRIES', 'potential' => 1, 'city' => 'Hosiyarpur', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => 0.57],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $exists = SalesPipeline::withoutGlobalScopes()
                ->where('salesperson_id', self::SALESPERSON_ID)
                ->where('party_name', $row['party'])
                ->where('is_locked', true)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $pipeline = SalesPipeline::create([
                'party_name' => $row['party'],
                'salesperson_id' => self::SALESPERSON_ID,
                'ccare_id' => null,
                'new_biz_id' => self::NEW_BIZ_ID,
                'total_business_potential' => $row['potential'],
                'type' => $row['type'],
                'product' => 'Rust-X',
                'address' => $row['city'],
                'is_locked' => true,
                'tenant_id' => null,
            ]);

            foreach (['2026-04' => $row['april'], '2026-05' => $row['may'], '2026-06' => $row['june']] as $monthYear => $saleAmount) {
                if ($saleAmount === null) {
                    continue;
                }
                SalesPipelineMonth::create([
                    'sales_pipeline_id' => $pipeline->id,
                    'month_year' => $monthYear,
                    'sale_amount' => $saleAmount,
                    'tenant_id' => null,
                ]);
            }

            $created++;
        }

        $this->command->info("FixNavinJoshiMissingSalesSeeder: created {$created}, skipped {$skipped} (already existed).");
    }
}
