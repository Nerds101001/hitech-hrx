<?php

namespace Database\Seeders;

use App\Models\TrainingModule;
use App\Models\TrainingPhase;
use App\Models\TrainingQuestion;
use Illuminate\Database\Seeder;

/**
 * Adds a brand-wise training curriculum (Company, RUST-X, Dr Bio, Keep It Fresh,
 * Fillezy/Zorbit/Tuffpaulin, CRM/ERP, Customer Service, Onboarding) as new phases
 * alongside the existing 4 phases. Idempotent — safe to re-run.
 *
 * Run with: php artisan db:seed --class=BrandTrainingCurriculumSeeder
 */
class BrandTrainingCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->curriculum() as $phaseOrder => $phaseData) {
            $phase = TrainingPhase::updateOrCreate(
                ['title' => $phaseData['title']],
                [
                    'description' => $phaseData['description'],
                    'order' => $phaseData['order'],
                ]
            );

            foreach ($phaseData['modules'] as $moduleOrder => $moduleData) {
                $module = TrainingModule::updateOrCreate(
                    ['phase_id' => $phase->id, 'title' => $moduleData['title']],
                    [
                        'description' => $moduleData['description'],
                        'content_type' => 'document',
                        'content_body' => $moduleData['description'],
                        'content_url' => null,
                        'estimated_time_minutes' => 15,
                        'order' => $moduleData['order'],
                        'is_assessment_required' => true,
                        'passing_percentage' => 80,
                        'questions_per_test' => count($moduleData['questions']),
                        'show_all_at_once' => true,
                    ]
                );

                foreach ($moduleData['questions'] as $q) {
                    TrainingQuestion::updateOrCreate(
                        ['module_id' => $module->id, 'question' => $q['q']],
                        [
                            'options' => $q['options'],
                            'correct_option_index' => $q['correct'],
                            'marks' => 1,
                        ]
                    );
                }
            }
        }
    }

    private function curriculum(): array
    {
        return [
            [
                'title' => 'Company & Group',
                'order' => 10,
                'description' => 'Foundational knowledge of the Hi-Tech Group and its brands.',
                'modules' => [
                    [
                        'title' => 'Company — Basics',
                        'description' => 'Brands & what we do',
                        'order' => 1,
                        'questions' => [
                            ['q' => 'Which of these is a Hi-Tech Group brand?', 'options' => ['AquaPure', 'Keep It Fresh', 'BubbleCo', 'FreshMax'], 'correct' => 1],
                            ['q' => "RUST-X's core expertise is:", 'options' => ['Food delivery', 'Corrosion prevention & VCI', 'Accounting software', 'Logistics'], 'correct' => 1],
                            ['q' => 'Dr Bio focuses on:', 'options' => ['Compostable bio-polymers', 'Steel coils', 'Rust-preventive oils', 'Tarpaulins'], 'correct' => 0],
                            ['q' => 'Keep It Fresh helps customers with:', 'options' => ['Rust', 'Post-harvest shelf-life extension', 'Void-fill packaging', 'Cleaning'], 'correct' => 1],
                            ['q' => 'Fillezy makes:', 'options' => ['Edible coatings', 'Protective / void-fill packaging', 'Desiccants', 'Cutting oils'], 'correct' => 1],
                        ],
                    ],
                    [
                        'title' => 'Company — Advanced',
                        'description' => 'Scale, customers, credentials',
                        'order' => 2,
                        'questions' => [
                            ['q' => '"Vertically integrated" means RUST-X:', 'options' => ['Only uses distributors', 'Makes its own chemicals, films, papers, oils & desiccants in-house', 'Has many office floors', 'Outsources production'], 'correct' => 1],
                            ['q' => 'RUST-X manufacturing locations include all EXCEPT:', 'options' => ['USA (California)', 'India', 'Germany / Italy', 'Brazil'], 'correct' => 3],
                            ['q' => 'The group serves roughly how many customers globally?', 'options' => ['100', '1,000', '10,000+', '50'], 'correct' => 2],
                            ['q' => 'Marquee customers include:', 'options' => ['Coca-Cola, KFC, Tata, Honda, Caterpillar', 'Only local shops', 'Only banks', 'Only airlines'], 'correct' => 0],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'RUST-X — Corrosion Science',
                'order' => 11,
                'description' => 'How VCI protects metal and the fundamentals of corrosion control.',
                'modules' => [
                    [
                        'title' => 'VCI & SMP — Basics',
                        'description' => 'How VCI protects metal',
                        'order' => 1,
                        'questions' => [
                            ['q' => 'VCI stands for:', 'options' => ['Vacuum Coated Iron', 'Volatile Corrosion Inhibitor', 'Very Clean Interior', 'Vapour Coating Industry'], 'correct' => 1],
                            ['q' => 'SMP technology stands for:', 'options' => ['Speedy Moisture Passivation', 'Standard Metal Protection', 'Surface Membrane Plating', 'Sealed Metal Packaging'], 'correct' => 0],
                            ['q' => 'VCI molecules stop rust by:', 'options' => ['Heating the metal', 'Depositing a molecular layer and inhibiting anode-cathode electron flow', 'Removing the metal', 'Painting it'], 'correct' => 1],
                            ['q' => 'About how much of the VCI chemical activates within the first 6 hours?', 'options' => ['~5%', '~20%', '~80%', '100%'], 'correct' => 1],
                            ['q' => 'The two internationally recognised tests for VCI efficacy are:', 'options' => ['Taste & drop test', 'VIA test & Razor Blade test', 'pH & burn test', 'Weight & colour test'], 'correct' => 1],
                            ['q' => 'In the Razor Blade test, effectiveness is confirmed when there is no corrosion after:', 'options' => ['1 hour', '48 hours', '30 days', '1 year'], 'correct' => 1],
                        ],
                    ],
                    [
                        'title' => 'Corrosion Control — Advanced',
                        'description' => 'Fundamentals, protection periods',
                        'order' => 2,
                        'questions' => [
                            ['q' => 'Which is NOT a valid way to control corrosion?', 'options' => ['Control moisture/humidity', 'Apply a VCI or barrier protective layer', 'Keep metal clean and dry', 'Spray the metal with salt water'], 'correct' => 3],
                            ['q' => 'A standard VCI film/bag alone provides protection for up to:', 'options' => ['1 week', 'Up to 2 years', '50 years', 'No time limit'], 'correct' => 1],
                            ['q' => 'VCI protection can be extended to 5–15 years by combining film with:', 'options' => ['Sunlight', 'Emitters, desiccants and/or rust-preventive oils', 'Salt', 'More air'], 'correct' => 1],
                            ['q' => '"Container rain" is:', 'options' => ['A type of VCI spray', 'Condensation inside shipping containers dripping onto goods', 'A cleaning method', 'A printing defect'], 'correct' => 1],
                            ['q' => 'VCI fogging is used to:', 'options' => ['Cool a warehouse', 'Apply VCI vapour into large enclosed cavities/volumes', 'Clean floors', 'Print bags'], 'correct' => 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'RUST-X — Products & Specs',
                'order' => 12,
                'description' => 'Thickness, loads, metals, formats',
                'modules' => [
                    [
                        'title' => 'Films, Papers & Emitters',
                        'description' => 'Thickness, loads, metals, formats',
                        'order' => 1,
                        'questions' => [
                            ['q' => 'A 200-micron VCI film / pallet liner can handle a load up to about:', 'options' => ['10 kg', '25 kg', '100 kg', '1,000 kg'], 'correct' => 2],
                            ['q' => 'For small components up to ~10 kg, the typical film thickness is:', 'options' => ['50–75 micron', '200 micron', '500 micron', '1 mm'], 'correct' => 0],
                            ['q' => 'VCI Paper (vs VCI Film) is preferred when you need:', 'options' => ['A sealed see-through barrier', 'Breathability, interleaving between metal sheets, or steel-coil packaging', 'To inflate a cushion', 'To absorb ethylene'], 'correct' => 1],
                            ['q' => 'For long-term sea-freight export, the recommended combined use is:', 'options' => ['Film only', 'VCI Paper as inner wrap + VCI Film as outer bag/liner', 'Paper only', 'No packaging'], 'correct' => 1],
                            ['q' => 'VCI 19000 is:', 'options' => ['A spray', 'A combined moisture + VCI desiccant', 'A tarpaulin', 'A cutting oil'], 'correct' => 1],
                            ['q' => 'An anti-static VCI bag is used mainly for:', 'options' => ['Fruit', 'Electronics / ESD-sensitive components', 'Tarpaulins', 'Cooking'], 'correct' => 1],
                            ['q' => 'VCI Emitter 5 and Emitter 11 differ mainly by:', 'options' => ['Colour', 'The enclosure volume they protect', 'Their smell', 'Their price only'], 'correct' => 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'RUST-X — Oils, Coatings & Cleaning',
                'order' => 13,
                'description' => 'RP oils, jelly wax, sprays, Aquaflush',
                'modules' => [
                    [
                        'title' => 'Oils, Coatings & Cleaners',
                        'description' => 'RP oils, jelly wax, sprays, Aquaflush',
                        'order' => 1,
                        'questions' => [
                            ['q' => 'A "dry-film" rust preventive differs from a "non-dry" one in that it:', 'options' => ['Leaves a touch-dry film vs an oily/wet film', 'Is always cheaper', 'Contains water only', 'Cannot protect steel'], 'correct' => 0],
                            ['q' => 'VCI Jelly Wax is ideal for:', 'options' => ['Fruit coating', 'Exposed metal, flanges and threaded connections (oil & gas / outdoor)', 'Electronics cooling', 'Void fill'], 'correct' => 1],
                            ['q' => 'RUST-X Cold Zinc / Clear Coat spray provides:', 'options' => ['A thick grease', 'A dry, invisible coating for painted or machined parts', 'An edible layer', 'An air cushion'], 'correct' => 1],
                            ['q' => 'Flash point is:', 'options' => ['The melting point', 'The lowest temperature at which a liquid gives off enough vapour to ignite', 'The boiling point of water', 'A print setting'], 'correct' => 1],
                            ['q' => 'VCI Coatings and Jelly Wax are removed using:', 'options' => ['Plain water only', 'RUST-X Aqueous Cleaner or Solvent Cleaner', 'Sandpaper', 'Nothing works'], 'correct' => 1],
                            ['q' => 'Aquaflush is a:', 'options' => ['Solvent cleaner', 'Water-based, solvent-free cleaner (dosed 5–10% in water), safe for oil & gas', 'Rust-preventive oil', 'Desiccant'], 'correct' => 1],
                            ['q' => 'In the grade name "135A-EX", the "EX" indicates it is the:', 'options' => ['Extra-cheap grade', 'Export grade', 'Expired grade', 'Experimental grade'], 'correct' => 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'RUST-X — Commercial & Process',
                'order' => 14,
                'description' => 'MOQ, payment, freight, printing',
                'modules' => [
                    [
                        'title' => 'Ordering, Pricing & Lead Time',
                        'description' => 'MOQ, payment, freight, printing',
                        'order' => 1,
                        'questions' => [
                            ['q' => 'Standard payment term for a NEW customer is:', 'options' => ['30-day credit', '100% advance before production', 'Pay on delivery', 'No payment'], 'correct' => 1],
                            ['q' => 'RUST-X prices are quoted:', 'options' => ['Delivered, freight free', 'Ex-factory (EXW) — freight is chargeable to the client', 'Always inclusive of freight', 'In USD only'], 'correct' => 1],
                            ['q' => 'Ready-stock items are dispatched within about:', 'options' => ['7 working days', '3 months', '1 hour', '60 days'], 'correct' => 0],
                            ['q' => 'A custom-printing cylinder remains valid for:', 'options' => ['1 month', '2 years', '10 years', 'Forever'], 'correct' => 1],
                            ['q' => 'Total lead time for custom print (1–8 colours) can be up to:', 'options' => ['1 day', '30 days', '6 months', '1 year'], 'correct' => 1],
                            ['q' => 'A corrosion audit is:', 'options' => ['A financial check', "A systematic assessment of a customer's process to find corrosion risks and recommend solutions", 'A safety drill', 'A printing proof'], 'correct' => 1],
                            ['q' => 'Within the director-approved maximum discount, a Sales Manager:', 'options' => ['Needs extra approval each time', 'Can quote directly with no further approval', 'Cannot quote at all', "Must email the customer's bank"], 'correct' => 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'RUST-X — Compliance & Safety',
                'order' => 15,
                'description' => 'Certs, TDS/SDS, shelf life',
                'modules' => [
                    [
                        'title' => 'Compliance, Storage & Safety',
                        'description' => 'Certs, TDS/SDS, shelf life',
                        'order' => 1,
                        'questions' => [
                            ['q' => 'RUST-X VCI films and papers are formulated to be free from:', 'options' => ['Nitrites and secondary amines (and heavy metals)', 'Plastic', 'Paper', 'All chemicals'], 'correct' => 0],
                            ['q' => 'TRGS 615 is a:', 'options' => ['German occupational safety standard (nitrite-free)', 'Type of film', 'Printing machine', 'Packing box size'], 'correct' => 0],
                            ['q' => 'Which is NOT a RUST-X certification/approval?', 'options' => ['ISO 9001:2015 (TUV)', 'REACH / RoHS', 'BFSV Germany', 'USDA Organic'], 'correct' => 3],
                            ['q' => 'A TDS differs from an SDS/MSDS in that the TDS gives:', 'options' => ['Hazard & safety handling data', 'Product specs, application guidance, shelf life & storage', 'Salary details', 'Pricing'], 'correct' => 1],
                            ['q' => 'Shelf life of VCI films and bags (sealed, cool, dry) is about:', 'options' => ['2 years', '10 years', '1 week', 'Unlimited'], 'correct' => 0],
                            ['q' => 'VCI aerosol sprays must NOT be exposed to temperatures above:', 'options' => ['10°C', '50°C', '200°C', 'No limit'], 'correct' => 1],
                            ['q' => 'Ideal storage temperature for VCI packaging is:', 'options' => ['15–25°C, away from sunlight', 'Above 40°C', 'Below 0°C only', 'In direct sun'], 'correct' => 0],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Dr Bio — Compostables',
                'order' => 16,
                'description' => 'Compostable science & grades, commercial & printing.',
                'modules' => [
                    [
                        'title' => 'Dr Bio — Basics',
                        'description' => 'Compostable science & grades',
                        'order' => 1,
                        'questions' => [
                            ['q' => 'Dr Bio compostable products are made largely from:', 'options' => ['Crude oil', 'Corn starch / plant-based bio-polymer', 'Glass', 'Aluminium'], 'correct' => 1],
                            ['q' => 'Certified compostable products are approved under which standard?', 'options' => ['ISO 9001', 'ISO / IS 17088', 'ISO 27001', 'RoHS'], 'correct' => 1],
                            ['q' => 'A certified compostable bag composts within about:', 'options' => ['6 months', '100 years', '1 week', 'Never'], 'correct' => 0],
                            ['q' => 'Oxo-biodegradable plastics are a problem because they:', 'options' => ['Are too strong', 'Leave harmful microplastics (found even in the human body)', 'Cost too much', 'Are edible'], 'correct' => 1],
                            ['q' => "In India, selling uncertified 'biodegradable' bags is:", 'options' => ['Encouraged', 'Not allowed — none are CPCB-certified for performance', 'Cheaper', 'Mandatory'], 'correct' => 1],
                            ['q' => 'The bio-polymer grade used to make Dr Bio carry bags is:', 'options' => ['Dr Bio 7272', 'PVC 100', 'HDPE 50', 'Nylon 6'], 'correct' => 0],
                        ],
                    ],
                    [
                        'title' => 'Dr Bio — Commercial & Printing',
                        'description' => 'MOQ, printing, properties',
                        'order' => 2,
                        'questions' => [
                            ['q' => 'Why must Dr Bio compostable bags always be printed?', 'options' => ['For looks', 'Govt/CPCB requires the name, QR code and manufacturer address on every bag', 'To add weight', 'It is optional'], 'correct' => 1],
                            ['q' => 'Dr Bio prints using which technology (preferred over flexo)?', 'options' => ['Gravure (laser-engraved cylinders)', 'Inkjet', 'Handwriting', 'Screen only'], 'correct' => 0],
                            ['q' => 'Dr Bio films can sustain hot liquids up to about:', 'options' => ['30°C', '90°C', '250°C', '10°C'], 'correct' => 1],
                            ['q' => 'Dr Bio high-performance filler is used in PBAT at a ratio up to about:', 'options' => ['5%', '60%', '100%', '0%'], 'correct' => 1],
                            ['q' => 'Minimum order for custom single-colour printing is about:', 'options' => ['10 kg', '350 kg (multi-colour 750 kg per size)', '5,000 kg', 'No minimum'], 'correct' => 1],
                            ['q' => 'A 50-micron garbage bag typically carries a load of about:', 'options' => ['1 kg', '25 kg', '200 kg', 'No load'], 'correct' => 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Keep It Fresh — Post-Harvest',
                'order' => 17,
                'description' => 'Ethylene absorption, grape pads, scrubbers, Life Coat.',
                'modules' => [
                    [
                        'title' => 'Keep It Fresh — Basics',
                        'description' => 'Ethylene & absorber sachets',
                        'order' => 1,
                        'questions' => [
                            ['q' => 'Ethylene is:', 'options' => ['A pesticide', 'A natural plant hormone that triggers ripening', 'A preservative', 'A film'], 'correct' => 1],
                            ['q' => 'KIF ethylene absorber sachets are based on:', 'options' => ['Salt & sugar', 'Zeolite powder & potassium permanganate', 'Silica gel only', 'Chalk'], 'correct' => 1],
                            ['q' => 'KIF sachets can extend shelf life, even in high humidity, by about:', 'options' => ['No change', '3–5×', '100×', 'Only if frozen'], 'correct' => 1],
                            ['q' => 'As well as ethylene, the sachets absorb:', 'options' => ['Light', 'Excess moisture (inhibiting microbes)', 'Colour', 'Sound'], 'correct' => 1],
                            ['q' => 'Climacteric fruits such as banana and mango:', 'options' => ['Produce little ethylene', 'Produce a lot of ethylene', 'Produce oxygen', 'Produce SO₂'], 'correct' => 1],
                        ],
                    ],
                    [
                        'title' => 'KIF Advanced + Life Coat',
                        'description' => 'Grape pads, scrubbers, Life Coat',
                        'order' => 2,
                        'questions' => [
                            ['q' => 'Grape pads control Botrytis by releasing which gas, from which salt?', 'options' => ['Oxygen, from table salt', 'Sulphur dioxide, from sodium metabisulfite', 'Nitrogen, from saltpetre', 'Chlorine, from bleach'], 'correct' => 1],
                            ['q' => 'Best practice for banana export combines a sachet with:', 'options' => ['Nothing else', 'A vacuum bag & germination paper + temperature/humidity control', 'Extra ethylene', 'Direct sunlight'], 'correct' => 1],
                            ['q' => 'Life Coat is:', 'options' => ['A plastic wrap', 'A food-grade, water-based edible coating that seals produce surfaces', 'A pesticide', 'A candle wax'], 'correct' => 1],
                            ['q' => 'On tender coconut, Life Coat is marketed as:', 'options' => ['Coco Coat', 'RustGuard', 'AirFill', 'Zorbit'], 'correct' => 0],
                            ['q' => 'An ethylene scrubber machine is used for:', 'options' => ['A single fruit box', 'Cold stores & containers (high-volume continuous ethylene removal)', 'Cleaning metal', 'Inflating cushions'], 'correct' => 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Fillezy & Protective Packaging',
                'order' => 18,
                'description' => 'Air-cushion void fill, plus Zorbit desiccants and Tuffpaulin tarpaulins.',
                'modules' => [
                    [
                        'title' => 'Fillezy — Basics',
                        'description' => 'Air-cushion void fill',
                        'order' => 1,
                        'questions' => [
                            ['q' => 'Fillezy air cushions are created by:', 'options' => ['Pouring foam', 'Inflating flat film with air at the point of packing', 'Folding cardboard', 'Adding water bags'], 'correct' => 1],
                            ['q' => 'The two jobs an air cushion does inside a box are:', 'options' => ['Cooling & heating', 'Void fill & cushioning / shock absorption', 'Labelling & sealing', 'Weighing & counting'], 'correct' => 1],
                            ['q' => "Why is 'shipping air, not filler' an advantage?", 'options' => ['It is heavier', 'Flat film stores compactly and cuts storage, weight & labour', 'It looks nicer', 'It needs no machine'], 'correct' => 1],
                            ['q' => 'Which Fillezy option suits a sustainability-focused customer?', 'options' => ['Double Cushion', 'Bio-Aer (compostable) or Paper Aer void fill', 'Air Tubes only', 'None'], 'correct' => 1],
                        ],
                    ],
                    [
                        'title' => 'Fillezy + Zorbit + Tuffpaulin',
                        'description' => 'Formats, desiccants, tarpaulins',
                        'order' => 2,
                        'questions' => [
                            ['q' => 'Which is a Fillezy product format?', 'options' => ['Air quilt / air tube / hex cushion', 'Edible film', 'Grape pad', 'VCI emitter'], 'correct' => 0],
                            ['q' => 'Zorbit desiccants are available as:', 'options' => ['Clay and silica gel', 'Only paper', 'Only foam', 'Only oil'], 'correct' => 0],
                            ['q' => 'Zorbit clay desiccant works by:', 'options' => ['Releasing moisture', 'Adsorbing / neutralising moisture (natural activated clay)', 'Heating air', 'Releasing SO₂'], 'correct' => 1],
                            ['q' => 'Tuffpaulin tarpaulins are notable for:', 'options' => ['Single-use design', 'Cross-laminated, multi-layer build with ~3–15 year weatherproof life', 'Being edible', 'Containing ethylene'], 'correct' => 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'CRM & ERP Software',
                'order' => 19,
                'description' => 'Login, leads, customers, quotes, approvals, samples, reports.',
                'modules' => [
                    [
                        'title' => 'CRM / ERP — Basics',
                        'description' => 'Login, leads, customers, quotes',
                        'order' => 1,
                        'questions' => [
                            ['q' => 'The first step to use the CRM is to:', 'options' => ['Print a report', 'Log in with your credentials', 'Delete a lead', 'Close the app'], 'correct' => 1],
                            ['q' => "In a CRM, a 'lead' is:", 'options' => ['A paid invoice', 'A potential customer / enquiry not yet converted', 'A delivered order', 'A staff member'], 'correct' => 1],
                            ['q' => "The 'New Customer' form is used to:", 'options' => ["Capture a customer's master details", 'Pay salaries', 'Book leave', 'Order lunch'], 'correct' => 0],
                            ['q' => "'Bind with Tally' links the CRM customer to:", 'options' => ['Social media', 'The accounting / Tally records', 'A printer', 'A door'], 'correct' => 1],
                            ['q' => 'A Quotation / PI is:', 'options' => ['A complaint', 'A price quote / proforma invoice for the customer', 'A delivery note', 'A salary slip'], 'correct' => 1],
                        ],
                    ],
                    [
                        'title' => 'CRM / ERP — Advanced',
                        'description' => 'Approvals, samples, reports',
                        'order' => 2,
                        'questions' => [
                            ['q' => "An approval-request / 'post for approval' step is used when:", 'options' => ['Anything is free', 'An action needs authorisation first (e.g. discount, credit, sample)', 'You want to delete data', 'You log out'], 'correct' => 1],
                            ['q' => "The 'Sample' form is used to:", 'options' => ['Request / track product samples for a customer', 'Book a holiday', 'Pay a vendor', 'Reset a password'], 'correct' => 0],
                            ['q' => 'CRM reports vs ERP reports:', 'options' => ['Identical', 'CRM = customer-facing pipeline/activity; ERP = back-office orders, inventory, finance', 'CRM is HR only', 'ERP is marketing only'], 'correct' => 1],
                            ['q' => 'The Reports / Dashboard mainly helps you:', 'options' => ['Change the logo', 'Analyse sales, orders and performance', 'Delete customers', 'Send memes'], 'correct' => 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Customer Service & Communication',
                'order' => 20,
                'description' => 'Care, communication, orders, retention & conflict.',
                'modules' => [
                    [
                        'title' => 'Customer Service — Basics',
                        'description' => 'Care, communication, orders',
                        'order' => 1,
                        'questions' => [
                            ['q' => 'Good customer care starts with:', 'options' => ['Talking over the customer', 'Listening to and understanding their need', 'Ending the call fast', 'Blaming the customer'], 'correct' => 1],
                            ['q' => 'Effective communication is:', 'options' => ['One-way', 'Clear, two-way, with active listening', 'Always written', 'As long as possible'], 'correct' => 1],
                            ['q' => 'Your first response to a complaint should be to:', 'options' => ['Argue', 'Acknowledge and empathise', 'Hang up', 'Ignore it'], 'correct' => 1],
                            ['q' => 'Accurate order entry matters because:', 'options' => ['It looks tidy', 'Errors cause wrong shipments and dissatisfaction', 'Guessing is faster', 'It saves paper'], 'correct' => 1],
                        ],
                    ],
                    [
                        'title' => 'Customer Service — Advanced',
                        'description' => 'Retention & conflict',
                        'order' => 2,
                        'questions' => [
                            ['q' => "A 'high-alert' customer is one who:", 'options' => ['Is unimportant', 'Is an at-risk or key account needing extra attention', 'Never buys', 'Already left'], 'correct' => 1],
                            ['q' => 'A key step in conflict resolution is to:', 'options' => ['Win at all costs', 'Stay calm, understand both sides, find common ground', 'Raise your voice', 'Avoid them'], 'correct' => 1],
                            ['q' => 'Customer retention is valuable because:', 'options' => ['It costs more than new sales', 'Keeping a customer is usually cheaper than acquiring a new one', 'It has no effect', 'It annoys people'], 'correct' => 1],
                            ['q' => 'You should escalate an issue when:', 'options' => ['Never', 'It is beyond your authority or stays unresolved', 'The customer is polite', 'It is easy'], 'correct' => 1],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Onboarding, Orders & Policy',
                'order' => 21,
                'description' => 'Enrolment, orders, conduct',
                'modules' => [
                    [
                        'title' => 'Onboarding, Orders & Policy',
                        'description' => 'Enrolment, orders, conduct',
                        'order' => 1,
                        'questions' => [
                            ['q' => 'Order entry typically covers:', 'options' => ['Only new orders', 'Both new and repeat orders', 'Only cancellations', 'Only refunds'], 'correct' => 1],
                            ['q' => "A new field/sales employee's onboarding kit typically includes:", 'options' => ['Only a laptop', 'Offer letter, ID/visiting cards, CRM access, product training, sample/test kits', 'Nothing', 'Only a uniform'], 'correct' => 1],
                            ['q' => 'The RUST-X razor-blade sample kit is given to a new employee to:', 'options' => ['Open boxes', 'Demonstrate VCI efficacy to customers', 'Cut paper', 'Decorate the office'], 'correct' => 1],
                            ['q' => "'Compliance' in the office policy means:", 'options' => ['Ignoring rules', 'Following company rules and legal/quality standards', 'Working overtime', 'Taking leave'], 'correct' => 1],
                            ['q' => 'Distributor management involves:', 'options' => ['Only invoicing', 'Enrolment, support, inventory and performance', 'Only firing them', 'Nothing'], 'correct' => 1],
                        ],
                    ],
                ],
            ],
        ];
    }
}
