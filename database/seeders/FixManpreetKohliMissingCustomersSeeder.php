<?php

namespace Database\Seeders;

use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use Illuminate\Database\Seeder;

/**
 * All 71 customers in "TUFFPAULIN OFFICE HEAD TO MANPREET KAUR KOHLI HEAD.xlsx"
 * were entirely absent from sales_pipelines -- 0 of 71 matched anything in the
 * DB. The 48 existing Tuffpaulin rows under her (ccare_id=251) are a different,
 * pre-existing set of customers. No salesperson assigned (per original
 * instruction); CCare = Manpreet Kaur Kohli.
 */
class FixManpreetKohliMissingCustomersSeeder extends Seeder
{
    private const CCARE_ID = 251; // Manpreet Kaur Kohli

    public function run(): void
    {
        $rows = [
            ['party' => 'Sarpanch warehousing', 'potential' => 0, 'city' => 'JIND', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => 9.23],
            ['party' => 'Prafulkumar Dhyanishi Mohanty', 'potential' => 0, 'city' => 'THANE', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => 0.23],
            ['party' => 'SEEDS TECHNICAL SERVICES PRIVATE LIMITED', 'potential' => 0, 'city' => 'GURUGRAM', 'type' => 'Dealer', 'april' => 0.88, 'may' => null, 'june' => 0.07],
            ['party' => 'OM BOHRA LOGISTICS PRIVATE LIMITED', 'potential' => 0, 'city' => 'Lalsot', 'type' => 'Customer', 'april' => 1.97, 'may' => null, 'june' => null],
            ['party' => 'AMBAI MAA TRADING CO', 'potential' => 0, 'city' => 'MUMBAI', 'type' => 'Dealer', 'april' => 10.33, 'may' => null, 'june' => 0.3],
            ['party' => 'BHAGAT SINGH & SONS', 'potential' => 0, 'city' => 'JALANDHAR', 'type' => 'Dealer', 'april' => 2.05, 'may' => 2.62, 'june' => null],
            ['party' => 'INDIAN FEBRICS', 'potential' => 100, 'city' => 'MADHOPUR', 'type' => 'Dealer', 'april' => 11.72, 'may' => null, 'june' => null],
            ['party' => 'RAMPATI WAREHOUSE', 'potential' => 0, 'city' => 'JAIPUR', 'type' => 'Customer', 'april' => 3.54, 'may' => null, 'june' => null],
            ['party' => 'SRI GANESH WOOD PACKING', 'potential' => 32.62, 'city' => 'Padappai', 'type' => 'Dealer', 'april' => 0.97, 'may' => 2.72, 'june' => 3.55],
            ['party' => 'LAMBROS', 'potential' => 19.35, 'city' => 'FARIDABAD', 'type' => 'Dealer', 'april' => null, 'may' => 3.16, 'june' => 2.68],
            ['party' => 'KDC POLYPLAST LLP', 'potential' => 11.56, 'city' => 'MUMBAI', 'type' => 'Dealer', 'april' => null, 'may' => 5.51, 'june' => null],
            ['party' => 'SHIVAM VASTRALAYA', 'potential' => 408.98, 'city' => 'MADHYA PRADESH', 'type' => 'Distributor', 'april' => 40.37, 'may' => 211.07, 'june' => 114.13],
            ['party' => 'ARAGONDA RAVI', 'potential' => 1.13, 'city' => 'Siddipet', 'type' => 'Customer', 'april' => 0.24, 'may' => null, 'june' => null],
            ['party' => 'T ADINARAYANA', 'potential' => 0.73, 'city' => 'CHITTOOR', 'type' => 'Customer', 'april' => 0.09, 'may' => 0.19, 'june' => null],
            ['party' => 'HP ADHENSIVES PVT LTD', 'potential' => 0.22, 'city' => 'Panvel', 'type' => 'Customer', 'april' => 0.78, 'may' => null, 'june' => null],
            ['party' => 'V-TECH AGRO INDUSTRIES', 'potential' => 10.11, 'city' => 'Mohali', 'type' => 'Dealer', 'april' => 0.02, 'may' => 0.11, 'june' => 0.66],
            ['party' => 'CACHET', 'potential' => 25.22, 'city' => 'ERNAKULAM', 'type' => 'Dealer', 'april' => 1.63, 'may' => null, 'june' => null],
            ['party' => 'V2S POLYPACK PRIVATE LIMITED', 'potential' => 1.62, 'city' => 'Krishnagiri', 'type' => 'Dealer', 'april' => 1.8, 'may' => null, 'june' => null],
            ['party' => 'TRANS OCEAN EXPORTS', 'potential' => 4.82, 'city' => 'KOLLAM', 'type' => 'Dealer', 'april' => null, 'may' => null, 'june' => 3.79],
            ['party' => 'KRS BRIQUETTES FARM', 'potential' => 0.23, 'city' => 'KANNAUJ', 'type' => 'Customer', 'april' => null, 'may' => 2.68, 'june' => null],
            ['party' => 'SHRI NAGARAJA MATSYA JEEVI UTPADAN SAHKARI SAMITI LIMITED SINGOT', 'potential' => 0.3, 'city' => 'DUNDA', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => 0.31],
            ['party' => 'PRISM JOHNSON LIMITED', 'potential' => 58.32, 'city' => 'SATNA', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => 6.37],
            ['party' => 'SATHYA SADHANA TRADERS', 'potential' => 2.91, 'city' => 'CHITTOOR', 'type' => 'Dealer', 'april' => null, 'may' => 1.8, 'june' => null],
            ['party' => 'SAIS & SAIS', 'potential' => 23.38, 'city' => 'ERNAKULAM', 'type' => 'Dealer', 'april' => 8.57, 'may' => 11.75, 'june' => 7.27],
            ['party' => 'BABAL FANCY STORE', 'potential' => 4.7, 'city' => 'MANDI', 'type' => 'Dealer', 'april' => 1.2, 'may' => 0.83, 'june' => null],
            ['party' => 'TARA FLY ASH BRICKS', 'potential' => 0, 'city' => 'JANJGIR-CHAMPA,', 'type' => 'Customer', 'april' => 0.24, 'may' => null, 'june' => null],
            ['party' => 'THE REGALIA', 'potential' => 0.17, 'city' => 'North Goa', 'type' => 'Customer', 'april' => 0.35, 'may' => null, 'june' => null],
            ['party' => 'ROYAL TOUCH PAINTS', 'potential' => 14.14, 'city' => 'AMRITSIR', 'type' => 'Dealer', 'april' => null, 'may' => 3.06, 'june' => null],
            ['party' => 'P.K TRADING COMPANY( 3 order )', 'potential' => 0, 'city' => 'INDORE', 'type' => 'Dealer', 'april' => null, 'may' => null, 'june' => 17.8],
            ['party' => 'GARDEN AGENCIES', 'potential' => 0.98, 'city' => 'YELLAPUR', 'type' => 'Dealer', 'april' => null, 'may' => 1.11, 'june' => null],
            ['party' => 'RACHIT ENTERPRISES', 'potential' => 0.07, 'city' => 'PALGHAR', 'type' => 'Customer', 'april' => 0.08, 'may' => null, 'june' => null],
            ['party' => 'CHOICE', 'potential' => 0.34, 'city' => 'PANJI', 'type' => 'Dealer', 'april' => null, 'may' => 0.35, 'june' => null],
            ['party' => 'KOSMO PLASTIC EXPORT PVT LTD', 'potential' => 0, 'city' => 'JALANDHAR', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => null],
            ['party' => 'HARYANA INDUSTRIAL HOUSE', 'potential' => 0, 'city' => 'FARIDABAD', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => null],
            ['party' => 'NARENDER KUMAR', 'potential' => 0, 'city' => 'GURGAUON', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => null],
            ['party' => 'YADAV TRADERS (TUFF)', 'potential' => 0, 'city' => 'GURGAUON', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => null],
            ['party' => 'ARYA MR TRADERS  CONSULTANTS', 'potential' => 0, 'city' => 'GURUGRAM', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => null],
            ['party' => 'ASSAM TIRPAL HOUSE', 'potential' => 0, 'city' => 'KAMRUP', 'type' => 'Dealer', 'april' => null, 'may' => null, 'june' => null],
            ['party' => 'GANPATI HARDWARE', 'potential' => 0, 'city' => 'JHARSUGUDA', 'type' => 'Dealer', 'april' => null, 'may' => null, 'june' => null],
            ['party' => 'BINAYAK BAG HOUSE', 'potential' => 0, 'city' => 'Berhampur', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => null],
            ['party' => 'THE NEW GLOBL FUMIGATION CORPORATION (SALES)', 'potential' => 0, 'city' => 'REWARI', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => null],
            ['party' => 'MAVI TILES', 'potential' => 0, 'city' => 'PAKHOWAL', 'type' => 'Customer', 'april' => null, 'may' => null, 'june' => null],
            ['party' => 'BURHANI', 'potential' => 0, 'city' => 'NASHIK', 'type' => 'Dealer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'SRAVAN KUMAR', 'potential' => 0, 'city' => 'uttarakhand', 'type' => 'Dealer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'TARPAULINS INDIA TARP', 'potential' => 0, 'city' => 'Delhi', 'type' => 'Dealer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'SATHYASEELAN', 'potential' => 0, 'city' => 'Tamilnadu', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'GEE BEE ENTERPRISES', 'potential' => 0, 'city' => 'Delhi', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'SARDAR SINGH AND SONS', 'potential' => 0, 'city' => 'Dehradun', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'SPARK TRADERS', 'potential' => 0, 'city' => 'Telangana', 'type' => 'Dealer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'POOJA ENTERPRISE', 'potential' => 0, 'city' => 'MUMBAI', 'type' => 'Dealer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'KUMAR TRADERS', 'potential' => 0, 'city' => 'Tamilnadu', 'type' => 'Dealer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'JAGATAMBA PAPER MART', 'potential' => 0, 'city' => 'Puducherry', 'type' => 'Dealer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'SHARMA TRADERS', 'potential' => 0, 'city' => 'Himachal Pradesh', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'RAJ TRADERS', 'potential' => 0, 'city' => 'Tamilnadu', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'MISTRY INDUSTRIES', 'potential' => 0, 'city' => 'Gujarat', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'SHF Enterprises', 'potential' => 0, 'city' => 'Jammu & Kashmir', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'ASSAM MEGHALAYA TIRPAL HOUSE', 'potential' => 0, 'city' => 'Assam', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'H.LALRINFELA', 'potential' => 0, 'city' => 'Mizoram', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'Jay Gauri Projects India Private Limited', 'potential' => 0, 'city' => 'Jaipur', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'NAVIN YADAV', 'potential' => 0, 'city' => 'Jharkhand', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'JAI MAA HARDWARE AND SANITARY STORE', 'potential' => 0, 'city' => 'uttarakhand', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'FIVE DIAMOND ENTERPRISES', 'potential' => 0, 'city' => 'Vishakhapatnam', 'type' => 'Dealer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'S & A MARKETING', 'potential' => 0, 'city' => 'Ghaziabad', 'type' => 'Dealer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'SWASTIK ENTERPRISES (H.P.)', 'potential' => 0, 'city' => 'Solan', 'type' => 'Dealer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'DURATECH IMPRESSIONS INC', 'potential' => 0, 'city' => 'Jamshedpur', 'type' => 'Dealer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'MARUTI INDUSTRIES', 'potential' => 0, 'city' => 'MUMBAI', 'type' => 'Dealer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'AMIT AGROPLAST', 'potential' => 0, 'city' => 'Sawai madhopur', 'type' => 'Dealer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'FORTUNA INDUSTRIES', 'potential' => 0, 'city' => 'Delhi', 'type' => 'Dealer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'SRI VEERAPANDIAN TRADERS', 'potential' => 0, 'city' => 'Tamilnadu', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'AGARWAL AUTOMOBILES', 'potential' => 0, 'city' => 'Assam', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
            ['party' => 'SHARMA TRADE LINKS', 'potential' => 0, 'city' => 'FARIDABAD', 'type' => 'Customer', 'april' => 0, 'may' => 0, 'june' => 0],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $exists = SalesPipeline::withoutGlobalScopes()
                ->where('ccare_id', self::CCARE_ID)
                ->where('party_name', $row['party'])
                ->where('is_locked', true)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $pipeline = SalesPipeline::create([
                'party_name' => $row['party'],
                'salesperson_id' => null,
                'ccare_id' => self::CCARE_ID,
                'new_biz_id' => null,
                'total_business_potential' => $row['potential'],
                'type' => $row['type'],
                'product' => 'Tuffpaulin',
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

        $this->command->info("FixManpreetKohliMissingCustomersSeeder: created {$created}, skipped {$skipped} (already existed).");
    }
}
