<?php

namespace Database\Seeders;

use App\Models\TrainingModule;
use App\Models\TrainingPhase;
use App\Models\TrainingQuestion;
use Illuminate\Database\Seeder;

/**
 * Attaches the brand-wise MCQ question bank DIRECTLY to the existing product
 * catalogue modules — no new phases, no new modules:
 *   - Rust-X Product Catalog  ← all RUST-X questions (VCI/SMP science, corrosion
 *     control, films/papers/emitters, oils/coatings/cleaners, ordering/pricing,
 *     compliance/storage/safety)
 *   - Dr. Bio / KIF / Fillezy / Zorbit / Tuffpaulin catalogues ← their questions
 *
 * Also cleans up the modules/phases earlier versions of this seeder wrongly
 * created, moves the mis-filed Rust-X questions off the Eva Bags catalogue,
 * and removes the old HRX-Core questions wrongly attached to the Rust-X
 * catalogue. Idempotent — safe to re-run.
 *
 * Run with: php artisan db:seed --class=BrandTrainingCurriculumSeeder
 */
class BrandTrainingCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $this->removeWronglyCreatedModulesAndPhases();

        // Attach bank questions to the existing catalogue modules. A module's
        // questions may be a flat list, or grouped by category (string keys) —
        // categories drive the segment-wise selection in getAssessment().
        foreach ($this->catalogueQuestions() as $titlePattern => $groups) {
            $module = TrainingModule::where('content_type', 'catalog')
                ->where('title', 'like', $titlePattern)
                ->orderBy('id')
                ->first();

            if (!$module) {
                $this->command?->warn("Catalogue module not found for pattern: {$titlePattern} — skipped.");
                continue;
            }

            $questions = [];
            foreach ($groups as $key => $value) {
                if (is_string($key)) {
                    foreach ($value as $q) {
                        $q['category'] = $key;
                        $questions[] = $q;
                    }
                } else {
                    $value['category'] = null;
                    $questions[] = $value;
                }
            }

            foreach ($questions as $q) {
                TrainingQuestion::updateOrCreate(
                    ['module_id' => $module->id, 'question' => $q['q']],
                    [
                        'options' => $q['options'],
                        'correct_option_index' => $q['correct'],
                        'category' => $q['category'],
                        'marks' => 1,
                    ]
                );
            }
        }

        $rustx = TrainingModule::where('content_type', 'catalog')
            ->where('title', 'like', '%Rust-X%')->orderBy('id')->first();

        // The 3 questions sitting on the Eva Bags catalogue are actually about
        // Rust-X VCI products (verified against both PDFs) — move them home.
        $eva = TrainingModule::where('content_type', 'catalog')
            ->where('title', 'like', '%Eva%')->orderBy('id')->first();
        if ($rustx && $eva) {
            TrainingQuestion::where('module_id', $eva->id)->update(['module_id' => $rustx->id]);
        }

        // The old questions on the Rust-X catalogue were generated from HRX-Core
        // software marketing text, not the Rust-X catalogue — remove them.
        if ($rustx) {
            TrainingQuestion::where('module_id', $rustx->id)
                ->where(function ($q) {
                    $q->where('question', 'like', '%HRX%')
                      ->orWhere('question', 'like', '%recruitment to retirement%')
                      ->orWhere('question', 'like', '%AI-Powered Analytics%');
                })
                ->delete();

            // Categorise the questions moved over from Eva Bags so the
            // segment-wise selection covers them too.
            TrainingQuestion::where('module_id', $rustx->id)
                ->where(function ($q) {
                    $q->where('question', 'like', '%water based acrylic%')
                      ->orWhere('question', 'like', '%powder cleaner%');
                })
                ->update(['category' => 'oils_coatings']);
            TrainingQuestion::where('module_id', $rustx->id)
                ->where('question', 'like', '%manufacturing location%')
                ->update(['category' => 'commercial']);
        }
    }

    private function removeWronglyCreatedModulesAndPhases(): void
    {
        $obsoleteModules = [
            'Company — Basics', 'Company — Advanced',
            'VCI & SMP — Basics', 'Corrosion Control — Advanced',
            'Films, Papers & Emitters', 'Oils, Coatings & Cleaners',
            'Ordering, Pricing & Lead Time', 'Compliance, Storage & Safety',
            'Dr Bio — Basics', 'Dr Bio — Commercial & Printing',
            'Keep It Fresh — Basics', 'KIF Advanced + Life Coat',
            'Fillezy — Basics', 'Fillezy + Zorbit + Tuffpaulin',
            'CRM / ERP — Basics', 'CRM / ERP — Advanced',
            'Customer Service — Basics', 'Customer Service — Advanced',
            'Onboarding, Orders & Policy',
        ];

        TrainingModule::withTrashed()
            ->whereIn('title', $obsoleteModules)
            ->where('content_type', 'document')
            ->get()
            ->each(function ($module) {
                TrainingQuestion::where('module_id', $module->id)->delete();
                $module->forceDelete();
            });

        $obsoletePhases = [
            'Company & Group',
            'RUST-X — Corrosion Science', 'RUST-X — Products & Specs',
            'RUST-X — Oils, Coatings & Cleaning', 'RUST-X — Commercial & Process',
            'RUST-X — Compliance & Safety',
            'Dr Bio — Compostables', 'Keep It Fresh — Post-Harvest',
            'Fillezy & Protective Packaging', 'CRM & ERP Software',
            'Customer Service & Communication', 'Onboarding, Orders & Policy',
        ];

        TrainingPhase::whereIn('title', $obsoletePhases)
            ->whereDoesntHave('modules')
            ->get()
            ->each(fn ($phase) => $phase->delete());
    }

    /**
     * Bank questions keyed by a LIKE pattern matching the existing catalogue
     * module titles.
     */
    private function catalogueQuestions(): array
    {
        return [
            '%Rust-X%' => [
                'vci_smp' => [
                ['q' => 'VCI stands for:', 'options' => ['Vacuum Coated Iron', 'Volatile Corrosion Inhibitor', 'Very Clean Interior', 'Vapour Coating Industry'], 'correct' => 1],
                ['q' => 'SMP technology stands for:', 'options' => ['Speedy Moisture Passivation', 'Standard Metal Protection', 'Surface Membrane Plating', 'Sealed Metal Packaging'], 'correct' => 0],
                ['q' => 'VCI molecules stop rust by:', 'options' => ['Heating the metal', 'Depositing a molecular layer and inhibiting anode-cathode electron flow', 'Removing the metal', 'Painting it'], 'correct' => 1],
                ['q' => 'About how much of the VCI chemical activates within the first 6 hours?', 'options' => ['~5%', '~20%', '~80%', '100%'], 'correct' => 1],
                ['q' => 'The two internationally recognised tests for VCI efficacy are:', 'options' => ['Taste & drop test', 'VIA test & Razor Blade test', 'pH & burn test', 'Weight & colour test'], 'correct' => 1],
                ['q' => 'In the Razor Blade test, effectiveness is confirmed when there is no corrosion after:', 'options' => ['1 hour', '48 hours', '30 days', '1 year'], 'correct' => 1],
                ],
                'corrosion_control' => [
                ['q' => 'Which is NOT a valid way to control corrosion?', 'options' => ['Control moisture/humidity', 'Apply a VCI or barrier protective layer', 'Keep metal clean and dry', 'Spray the metal with salt water'], 'correct' => 3],
                ['q' => 'A standard VCI film/bag alone provides protection for up to:', 'options' => ['1 week', 'Up to 2 years', '50 years', 'No time limit'], 'correct' => 1],
                ['q' => 'VCI protection can be extended to 5–15 years by combining film with:', 'options' => ['Sunlight', 'Emitters, desiccants and/or rust-preventive oils', 'Salt', 'More air'], 'correct' => 1],
                ['q' => '"Container rain" is:', 'options' => ['A type of VCI spray', 'Condensation inside shipping containers dripping onto goods', 'A cleaning method', 'A printing defect'], 'correct' => 1],
                ['q' => 'VCI fogging is used to:', 'options' => ['Cool a warehouse', 'Apply VCI vapour into large enclosed cavities/volumes', 'Clean floors', 'Print bags'], 'correct' => 1],
                ],
                'films_papers' => [
                ['q' => 'A 200-micron VCI film / pallet liner can handle a load up to about:', 'options' => ['10 kg', '25 kg', '100 kg', '1,000 kg'], 'correct' => 2],
                ['q' => 'For small components up to ~10 kg, the typical film thickness is:', 'options' => ['50–75 micron', '200 micron', '500 micron', '1 mm'], 'correct' => 0],
                ['q' => 'VCI Paper (vs VCI Film) is preferred when you need:', 'options' => ['A sealed see-through barrier', 'Breathability, interleaving between metal sheets, or steel-coil packaging', 'To inflate a cushion', 'To absorb ethylene'], 'correct' => 1],
                ['q' => 'For long-term sea-freight export, the recommended combined use is:', 'options' => ['Film only', 'VCI Paper as inner wrap + VCI Film as outer bag/liner', 'Paper only', 'No packaging'], 'correct' => 1],
                ['q' => 'VCI 19000 is:', 'options' => ['A spray', 'A combined moisture + VCI desiccant', 'A tarpaulin', 'A cutting oil'], 'correct' => 1],
                ['q' => 'An anti-static VCI bag is used mainly for:', 'options' => ['Fruit', 'Electronics / ESD-sensitive components', 'Tarpaulins', 'Cooking'], 'correct' => 1],
                ['q' => 'VCI Emitter 5 and Emitter 11 differ mainly by:', 'options' => ['Colour', 'The enclosure volume they protect', 'Their smell', 'Their price only'], 'correct' => 1],
                ],
                'oils_coatings' => [
                ['q' => 'A "dry-film" rust preventive differs from a "non-dry" one in that it:', 'options' => ['Leaves a touch-dry film vs an oily/wet film', 'Is always cheaper', 'Contains water only', 'Cannot protect steel'], 'correct' => 0],
                ['q' => 'VCI Jelly Wax is ideal for:', 'options' => ['Fruit coating', 'Exposed metal, flanges and threaded connections (oil & gas / outdoor)', 'Electronics cooling', 'Void fill'], 'correct' => 1],
                ['q' => 'RUST-X Cold Zinc / Clear Coat spray provides:', 'options' => ['A thick grease', 'A dry, invisible coating for painted or machined parts', 'An edible layer', 'An air cushion'], 'correct' => 1],
                ['q' => 'Flash point is:', 'options' => ['The melting point', 'The lowest temperature at which a liquid gives off enough vapour to ignite', 'The boiling point of water', 'A print setting'], 'correct' => 1],
                ['q' => 'VCI Coatings and Jelly Wax are removed using:', 'options' => ['Plain water only', 'RUST-X Aqueous Cleaner or Solvent Cleaner', 'Sandpaper', 'Nothing works'], 'correct' => 1],
                ['q' => 'Aquaflush is a:', 'options' => ['Solvent cleaner', 'Water-based, solvent-free cleaner (dosed 5–10% in water), safe for oil & gas', 'Rust-preventive oil', 'Desiccant'], 'correct' => 1],
                ['q' => 'In the grade name "135A-EX", the "EX" indicates it is the:', 'options' => ['Extra-cheap grade', 'Export grade', 'Expired grade', 'Experimental grade'], 'correct' => 1],
                ],
                'commercial' => [
                ['q' => 'Standard payment term for a NEW customer is:', 'options' => ['30-day credit', '100% advance before production', 'Pay on delivery', 'No payment'], 'correct' => 1],
                ['q' => 'RUST-X prices are quoted:', 'options' => ['Delivered, freight free', 'Ex-factory (EXW) — freight is chargeable to the client', 'Always inclusive of freight', 'In USD only'], 'correct' => 1],
                ['q' => 'Ready-stock items are dispatched within about:', 'options' => ['7 working days', '3 months', '1 hour', '60 days'], 'correct' => 0],
                ['q' => 'A custom-printing cylinder remains valid for:', 'options' => ['1 month', '2 years', '10 years', 'Forever'], 'correct' => 1],
                ['q' => 'Total lead time for custom print (1–8 colours) can be up to:', 'options' => ['1 day', '30 days', '6 months', '1 year'], 'correct' => 1],
                ['q' => 'A corrosion audit is:', 'options' => ['A financial check', "A systematic assessment of a customer's process to find corrosion risks and recommend solutions", 'A safety drill', 'A printing proof'], 'correct' => 1],
                ['q' => 'Within the director-approved maximum discount, a Sales Manager:', 'options' => ['Needs extra approval each time', 'Can quote directly with no further approval', 'Cannot quote at all', "Must email the customer's bank"], 'correct' => 1],
                ],
                'compliance' => [
                ['q' => 'RUST-X VCI films and papers are formulated to be free from:', 'options' => ['Nitrites and secondary amines (and heavy metals)', 'Plastic', 'Paper', 'All chemicals'], 'correct' => 0],
                ['q' => 'TRGS 615 is a:', 'options' => ['German occupational safety standard (nitrite-free)', 'Type of film', 'Printing machine', 'Packing box size'], 'correct' => 0],
                ['q' => 'Which is NOT a RUST-X certification/approval?', 'options' => ['ISO 9001:2015 (TUV)', 'REACH / RoHS', 'BFSV Germany', 'USDA Organic'], 'correct' => 3],
                ['q' => 'A TDS differs from an SDS/MSDS in that the TDS gives:', 'options' => ['Hazard & safety handling data', 'Product specs, application guidance, shelf life & storage', 'Salary details', 'Pricing'], 'correct' => 1],
                ['q' => 'Shelf life of VCI films and bags (sealed, cool, dry) is about:', 'options' => ['2 years', '10 years', '1 week', 'Unlimited'], 'correct' => 0],
                ['q' => 'VCI aerosol sprays must NOT be exposed to temperatures above:', 'options' => ['10°C', '50°C', '200°C', 'No limit'], 'correct' => 1],
                ['q' => 'Ideal storage temperature for VCI packaging is:', 'options' => ['15–25°C, away from sunlight', 'Above 40°C', 'Below 0°C only', 'In direct sun'], 'correct' => 0],
                ],
            ],

            '%Dr%Bio%' => [
                ['q' => 'Dr Bio compostable products are made largely from:', 'options' => ['Crude oil', 'Corn starch / plant-based bio-polymer', 'Glass', 'Aluminium'], 'correct' => 1],
                ['q' => 'Certified compostable products are approved under which standard?', 'options' => ['ISO 9001', 'ISO / IS 17088', 'ISO 27001', 'RoHS'], 'correct' => 1],
                ['q' => 'A certified compostable bag composts within about:', 'options' => ['6 months', '100 years', '1 week', 'Never'], 'correct' => 0],
                ['q' => 'Oxo-biodegradable plastics are a problem because they:', 'options' => ['Are too strong', 'Leave harmful microplastics (found even in the human body)', 'Cost too much', 'Are edible'], 'correct' => 1],
                ['q' => "In India, selling uncertified 'biodegradable' bags is:", 'options' => ['Encouraged', 'Not allowed — none are CPCB-certified for performance', 'Cheaper', 'Mandatory'], 'correct' => 1],
                ['q' => 'The bio-polymer grade used to make Dr Bio carry bags is:', 'options' => ['Dr Bio 7272', 'PVC 100', 'HDPE 50', 'Nylon 6'], 'correct' => 0],
                ['q' => 'Why must Dr Bio compostable bags always be printed?', 'options' => ['For looks', 'Govt/CPCB requires the name, QR code and manufacturer address on every bag', 'To add weight', 'It is optional'], 'correct' => 1],
                ['q' => 'Dr Bio prints using which technology (preferred over flexo)?', 'options' => ['Gravure (laser-engraved cylinders)', 'Inkjet', 'Handwriting', 'Screen only'], 'correct' => 0],
                ['q' => 'Dr Bio films can sustain hot liquids up to about:', 'options' => ['30°C', '90°C', '250°C', '10°C'], 'correct' => 1],
                ['q' => 'Dr Bio high-performance filler is used in PBAT at a ratio up to about:', 'options' => ['5%', '60%', '100%', '0%'], 'correct' => 1],
                ['q' => 'Minimum order for custom single-colour printing is about:', 'options' => ['10 kg', '350 kg (multi-colour 750 kg per size)', '5,000 kg', 'No minimum'], 'correct' => 1],
                ['q' => 'A 50-micron garbage bag typically carries a load of about:', 'options' => ['1 kg', '25 kg', '200 kg', 'No load'], 'correct' => 1],
            ],

            '%KIF%' => [
                ['q' => 'Ethylene is:', 'options' => ['A pesticide', 'A natural plant hormone that triggers ripening', 'A preservative', 'A film'], 'correct' => 1],
                ['q' => 'KIF ethylene absorber sachets are based on:', 'options' => ['Salt & sugar', 'Zeolite powder & potassium permanganate', 'Silica gel only', 'Chalk'], 'correct' => 1],
                ['q' => 'KIF sachets can extend shelf life, even in high humidity, by about:', 'options' => ['No change', '3–5×', '100×', 'Only if frozen'], 'correct' => 1],
                ['q' => 'As well as ethylene, the sachets absorb:', 'options' => ['Light', 'Excess moisture (inhibiting microbes)', 'Colour', 'Sound'], 'correct' => 1],
                ['q' => 'Climacteric fruits such as banana and mango:', 'options' => ['Produce little ethylene', 'Produce a lot of ethylene', 'Produce oxygen', 'Produce SO₂'], 'correct' => 1],
                ['q' => 'Grape pads control Botrytis by releasing which gas, from which salt?', 'options' => ['Oxygen, from table salt', 'Sulphur dioxide, from sodium metabisulfite', 'Nitrogen, from saltpetre', 'Chlorine, from bleach'], 'correct' => 1],
                ['q' => 'Best practice for banana export combines a sachet with:', 'options' => ['Nothing else', 'A vacuum bag & germination paper + temperature/humidity control', 'Extra ethylene', 'Direct sunlight'], 'correct' => 1],
                ['q' => 'Life Coat is:', 'options' => ['A plastic wrap', 'A food-grade, water-based edible coating that seals produce surfaces', 'A pesticide', 'A candle wax'], 'correct' => 1],
                ['q' => 'On tender coconut, Life Coat is marketed as:', 'options' => ['Coco Coat', 'RustGuard', 'AirFill', 'Zorbit'], 'correct' => 0],
                ['q' => 'An ethylene scrubber machine is used for:', 'options' => ['A single fruit box', 'Cold stores & containers (high-volume continuous ethylene removal)', 'Cleaning metal', 'Inflating cushions'], 'correct' => 1],
            ],

            '%Fillezy%' => [
                ['q' => 'Fillezy air cushions are created by:', 'options' => ['Pouring foam', 'Inflating flat film with air at the point of packing', 'Folding cardboard', 'Adding water bags'], 'correct' => 1],
                ['q' => 'The two jobs an air cushion does inside a box are:', 'options' => ['Cooling & heating', 'Void fill & cushioning / shock absorption', 'Labelling & sealing', 'Weighing & counting'], 'correct' => 1],
                ['q' => "Why is 'shipping air, not filler' an advantage?", 'options' => ['It is heavier', 'Flat film stores compactly and cuts storage, weight & labour', 'It looks nicer', 'It needs no machine'], 'correct' => 1],
                ['q' => 'Which Fillezy option suits a sustainability-focused customer?', 'options' => ['Double Cushion', 'Bio-Aer (compostable) or Paper Aer void fill', 'Air Tubes only', 'None'], 'correct' => 1],
                ['q' => 'Which is a Fillezy product format?', 'options' => ['Air quilt / air tube / hex cushion', 'Edible film', 'Grape pad', 'VCI emitter'], 'correct' => 0],
            ],

            '%Zorbit%' => [
                ['q' => 'Zorbit desiccants are available as:', 'options' => ['Clay and silica gel', 'Only paper', 'Only foam', 'Only oil'], 'correct' => 0],
                ['q' => 'Zorbit clay desiccant works by:', 'options' => ['Releasing moisture', 'Adsorbing / neutralising moisture (natural activated clay)', 'Heating air', 'Releasing SO₂'], 'correct' => 1],
            ],

            '%Tuff%' => [
                ['q' => 'Tuffpaulin tarpaulins are notable for:', 'options' => ['Single-use design', 'Cross-laminated, multi-layer build with ~3–15 year weatherproof life', 'Being edible', 'Containing ethylene'], 'correct' => 1],
            ],
        ];
    }
}
