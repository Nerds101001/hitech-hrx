<?php

namespace Database\Seeders;

use App\Models\TrainingModule;
use App\Models\TrainingQuestion;
use Illuminate\Database\Seeder;

/**
 * Tops up the product-catalogue question banks so each catalogue module holds
 * at least ~35 questions. Every question is grounded in the actual catalogue
 * PDF content (I-MIX/Release-X, Dr Bio, Keep It Fresh, Fillezy, Tuffpaulin).
 * Rust-X already has 42 and is left untouched.
 *
 * Attaches directly to the existing catalogue modules — no new phases/modules.
 * Idempotent (keyed on module_id + question text). Safe to re-run.
 *
 * Run with: php artisan db:seed --class=CatalogueQuestionTopUpSeeder
 */
class CatalogueQuestionTopUpSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->bank() as $titlePattern => $questions) {
            $module = TrainingModule::where('content_type', 'catalog')
                ->where('title', 'like', $titlePattern)
                ->orderBy('id')
                ->first();

            if (!$module) {
                $this->command?->warn("Catalogue not found for pattern: {$titlePattern} — skipped.");
                continue;
            }

            foreach ($questions as $q) {
                TrainingQuestion::updateOrCreate(
                    ['module_id' => $module->id, 'question' => $q['q']],
                    [
                        'options' => $q['options'],
                        'correct_option_index' => $q['correct'],
                        'category' => null,
                        'marks' => 1,
                    ]
                );
            }

            $this->command?->info("{$module->title}: now {$module->questions()->count()} questions.");
        }
    }

    private function bank(): array
    {
        return [
            '%Dr%Bio%'    => $this->drBio(),
            '%KIF%'       => $this->kif(),
            '%Fillezy%'   => $this->fillezy(),
            '%Tuff%'      => $this->tuffpaulin(),
            '%Eva%'       => $this->evaBags(),
        ];
    }

    // ── Dr Bio — +20 (target 35) ──────────────────────────────────────────
    private function drBio(): array
    {
        return [
            ['q' => 'Which company first manufactured the Dr Bio plant-based biopolymer in India?', 'options' => ['Hi-Tech International', 'A foreign importer', 'A petrochemical refinery', 'A paper mill'], 'correct' => 0],
            ['q' => 'Dr Bio is designed to replace which kind of products?', 'options' => ['Glass bottles', 'Single-use and multi-use plastic products', 'Metal cans', 'Wooden crates'], 'correct' => 1],
            ['q' => 'Which of these can Dr Bio compostable material be made into?', 'options' => ['Straws, cups, cutlery and polybags', 'Steel coils', 'Rust-preventive oil', 'Tarpaulins'], 'correct' => 0],
            ['q' => 'Dr Bio bio-polymer is largely derived from:', 'options' => ['Petroleum only', 'Plant-based starch (e.g. corn) blends', 'Recycled PVC', 'Aluminium'], 'correct' => 1],
            ['q' => 'A genuinely compostable Dr Bio bag breaks down leaving:', 'options' => ['Toxic residue', 'No toxic residue', 'Microplastics', 'Metal fragments'], 'correct' => 1],
            ['q' => 'The key certification standard for compostable products is:', 'options' => ['IS / ISO 17088', 'ISO 27001', 'RoHS only', 'IATF 16949'], 'correct' => 0],
            ['q' => 'In India, only which products may legally be sold as compostable?', 'options' => ['Any biodegradable-labelled bag', 'CPCB-certified compostable products', 'Oxo-biodegradable bags', 'Unmarked bags'], 'correct' => 1],
            ['q' => 'Why are oxo-biodegradable plastics discouraged compared to compostables?', 'options' => ['They cost more', 'They fragment into harmful microplastics', 'They are too strong', 'They compost too fast'], 'correct' => 1],
            ['q' => 'Approximately how long does a certified compostable product take to compost?', 'options' => ['About 6 months', '100 years', '1 week', 'It never composts'], 'correct' => 0],
            ['q' => 'Dr Bio compostable bags must be printed because regulation requires:', 'options' => ['A decorative logo', 'Name, QR code and manufacturer address on every bag', 'A barcode only', 'Nothing — printing is optional'], 'correct' => 1],
            ['q' => 'Which printing method does Dr Bio prefer for accuracy and QR readability?', 'options' => ['Gravure with laser-engraved cylinders', 'Handwriting', 'Old flexo rubber-pad printing', 'Inkjet only'], 'correct' => 0],
            ['q' => 'A custom-printing cylinder for Dr Bio bags is typically valid for:', 'options' => ['2 years', '1 month', '10 years', 'Forever'], 'correct' => 0],
            ['q' => 'The approximate minimum order for custom single-colour printing is:', 'options' => ['350 kg', '10 kg', '5,000 kg', 'No minimum'], 'correct' => 0],
            ['q' => 'For multi-colour custom printing the MOQ per size rises to about:', 'options' => ['750 kg', '50 kg', '10 kg', 'No minimum'], 'correct' => 0],
            ['q' => 'Dr Bio films can typically sustain hot liquids up to about:', 'options' => ['90°C', '30°C', '250°C', '10°C'], 'correct' => 0],
            ['q' => 'The Dr Bio high-performance filler can be used in PBAT at a ratio up to about:', 'options' => ['60%', '5%', '100%', '0%'], 'correct' => 0],
            ['q' => 'A 50-micron Dr Bio garbage bag typically carries a load of about:', 'options' => ['25 kg', '1 kg', '200 kg', 'No load'], 'correct' => 0],
            ['q' => 'The bio-polymer grade commonly used for Dr Bio carry bags is:', 'options' => ['Dr Bio 7272', 'HDPE 50', 'PVC 100', 'Nylon 6'], 'correct' => 0],
            ['q' => "Dr Bio's core environmental benefit is that its products are:", 'options' => ['Compostable and plant-based', 'Made from crude oil', 'Non-recyclable', 'Single-use plastic'], 'correct' => 0],
            ['q' => 'Compared to conventional plastic, Dr Bio compostables mainly help reduce:', 'options' => ['Plastic waste and pollution', 'Shipping speed', 'Metal corrosion', 'Ethylene gas'], 'correct' => 0],
        ];
    }

    // ── Keep It Fresh — +22 (target 35) ───────────────────────────────────
    private function kif(): array
    {
        return [
            ['q' => 'KIF sachets simultaneously cause ethylene absorption, moisture absorption and:', 'options' => ['Volatile Bacterial Inhibition (VBI)', 'Rust prevention', 'Static dissipation', 'Void filling'], 'correct' => 0],
            ['q' => 'KIF sachet contents are held in which breathable membrane?', 'options' => ['Tyvek & DuPont membrane', 'Aluminium foil', 'PVC film', 'Cotton wool'], 'correct' => 0],
            ['q' => 'KIF sachets are available in gram sizes of:', 'options' => ['2, 5 and 10 gram', '100 and 200 gram', '1 kg only', '0.5 gram only'], 'correct' => 0],
            ['q' => 'When ethylene is oxidised inside a KIF sachet it converts to:', 'options' => ['Water and carbon dioxide', 'Methane', 'Sulphur dioxide', 'Nitrogen'], 'correct' => 0],
            ['q' => 'The VBI option in KIF sachets uses which active ingredient?', 'options' => ['A natural plant extract', 'Chlorine bleach', 'Table salt', 'Silica gel'], 'correct' => 0],
            ['q' => 'KIF MAP bags claim shelf-life enhancement of fresh produce in the range of:', 'options' => ['40% to 400%', '5% to 10%', '1000%+', 'No change'], 'correct' => 0],
            ['q' => 'KIF film shows up to how many times increase in banana shelf life?', 'options' => ['Up to 5 times', 'No change', '100 times', 'Half'], 'correct' => 0],
            ['q' => 'KIF Grape Pads release SO₂ from which salt?', 'options' => ['Sodium metabisulfite', 'Sodium chloride', 'Potassium nitrate', 'Calcium carbonate'], 'correct' => 0],
            ['q' => 'KIF Grape Pads help control which main post-harvest disease of table grapes?', 'options' => ['Botrytis cinerea', 'Powdery mildew of wheat', 'Potato blight', 'Citrus canker'], 'correct' => 0],
            ['q' => 'How are KIF Grape Pads activated for a quick release of SO₂?', 'options' => ['By sprinkling water on the printed surface', 'By heating to 100°C', 'By freezing', 'By adding oil'], 'correct' => 0],
            ['q' => 'KIF Grape Pads can extend grape life up to about:', 'options' => ['60 days', '2 days', '1 year', '5 days'], 'correct' => 0],
            ['q' => 'KIF-DRY / KIF-SORB absorbent pads are designed mainly for which produce?', 'options' => ['Soft fruits like raspberries and blackberries', 'Steel parts', 'Cut flowers only', 'Grains'], 'correct' => 0],
            ['q' => 'KIF Jiffy Pads are made by meshing recycled paper with:', 'options' => ['Industrial cotton', 'Steel wool', 'Glass fibre', 'Plastic pellets'], 'correct' => 0],
            ['q' => 'Besides cushioning, KIF Jiffy Pads also provide:', 'options' => ['Ethylene absorption', 'Rust prevention', 'UV protection', 'Static control'], 'correct' => 0],
            ['q' => 'KIF Curtains are used primarily in:', 'options' => ['Reefer containers and cold storages', 'Metal warehouses', 'Printing presses', 'Petrol stations'], 'correct' => 0],
            ['q' => 'For best performance, a KIF Curtain should be placed:', 'options' => ['Near the intake airflow', 'On the floor', 'Outside the container', 'Inside the fruit box'], 'correct' => 0],
            ['q' => 'The KIF ethylene scrubber can reduce ethylene levels by up to about:', 'options' => ['98.9%', '10%', '50%', '25%'], 'correct' => 0],
            ['q' => 'Each KIF scrubber unit holds how much media (KIF power balls)?', 'options' => ['50 kg', '5 kg', '500 kg', '1 kg'], 'correct' => 0],
            ['q' => 'KIF Germination Paper has a water absorption capacity of up to about:', 'options' => ['200% of its weight', '10% of its weight', '1000% of its weight', 'None'], 'correct' => 0],
            ['q' => 'KIF O₂ absorbers (oxygen scavengers) help prevent:', 'options' => ['Myco/aflatoxin development and chemical oxidation', 'Rust on steel', 'Static build-up', 'Ethylene release'], 'correct' => 0],
            ['q' => 'KIF Flora Fresh is a product used to:', 'options' => ['Hydrate and nourish fresh cut flowers', 'Prevent metal rust', 'Fill packaging voids', 'Clean machinery'], 'correct' => 0],
            ['q' => 'Keep It Fresh has manufacturing facilities in USA, India, China, Italy, Japan and:', 'options' => ['Africa', 'Antarctica', 'Only India', 'Nowhere else'], 'correct' => 0],
        ];
    }

    // ── Fillezy — +27 (target 35) ─────────────────────────────────────────
    private function fillezy(): array
    {
        return [
            ['q' => 'Fillezy AER cushioning products are based on paper, recyclable plastic film and:', 'options' => ['Corn-starch based biodegradable film', 'Glass fibre', 'Metal foil', 'Rubber'], 'correct' => 0],
            ['q' => 'A single wrap of Fillezy bubble offers cushioning equal to how many wraps of normal bubble?', 'options' => ['3–4 wraps', '10 wraps', 'Half a wrap', 'The same 1 wrap'], 'correct' => 0],
            ['q' => 'Fillezy claims it is roughly how much cheaper to use than normal bubbles?', 'options' => ['50% cheaper', '5% cheaper', '200% dearer', 'Same cost'], 'correct' => 0],
            ['q' => 'Storing flat Fillezy film instead of pre-made bubble gives floor-space saving of about:', 'options' => ['90%', '10%', 'None', '5%'], 'correct' => 0],
            ['q' => 'Fillezy protective packaging can reduce transit damages by up to:', 'options' => ['100%', '10%', '25%', 'None'], 'correct' => 0],
            ['q' => 'One roll of AER cushions can expand to roughly:', 'options' => ['A mini truck load of cushions', 'One shoebox', 'A single envelope', 'A matchbox'], 'correct' => 0],
            ['q' => 'The Fillezy Hexa paper is a honeycomb-shaped 3D paper made from:', 'options' => ['Recycled (FSC-compliant) paper', 'Virgin plastic', 'Aluminium', 'Glass'], 'correct' => 0],
            ['q' => 'Hexa paper is claimed to be how much less costly than plastic bubble?', 'options' => ['60% less costly', '5% less costly', 'Same cost', 'Twice as costly'], 'correct' => 0],
            ['q' => 'Hexa paper offers roughly what level of cushioning versus ordinary wrap?', 'options' => ['2X better cushioning', 'Half the cushioning', 'No cushioning', 'Identical'], 'correct' => 0],
            ['q' => 'The Fillezy Paper Shark / Eco machine dispenses paper that is:', 'options' => ['100% recyclable and compostable', 'Non-recyclable plastic', 'Metal-coated', 'Styrofoam'], 'correct' => 0],
            ['q' => 'The Paper Shark machine runs effectively at a speed of about:', 'options' => ['50–150 m/min', '1 m/min', '500 m/min', '5 m/hour'], 'correct' => 0],
            ['q' => 'The Fillezy Rapid machine can seal and fill AER in film thicknesses of:', 'options' => ['15, 22, 35 & 50 microns', '1 mm only', '5 microns only', '1 cm'], 'correct' => 0],
            ['q' => 'The Rapid machine has a production speed of about:', 'options' => ['37 metres per minute', '1 metre per minute', '500 metres per minute', '2 metres per hour'], 'correct' => 0],
            ['q' => 'Fillezy machines typically carry a warranty of:', 'options' => ['5 years', '6 months', '1 month', '20 years'], 'correct' => 0],
            ['q' => 'The Rapid machine is CE-approved and assembled in India in collaboration with partners from:', 'options' => ['The USA', 'Japan', 'Brazil', 'Russia'], 'correct' => 0],
            ['q' => 'Fillezy Bio AER cushions are made from which compostable raw materials?', 'options' => ['Corn and cassava starch', 'Crude oil', 'Fibreglass', 'Recycled tyres'], 'correct' => 0],
            ['q' => 'The compostable biopolymer used in Bio AER is manufactured by which group company?', 'options' => ['Hi-Tech International', 'A foreign supplier', 'A paper mill', 'An oil refinery'], 'correct' => 0],
            ['q' => 'Fillezy Paper AER inflated pouches consist of roughly:', 'options' => ['3% paper + 97% air (AER)', '50% paper + 50% air', '100% paper', '100% plastic'], 'correct' => 0],
            ['q' => 'Paper AER is made from paper that is:', 'options' => ['FSC-compliant, compostable and degradable', 'Non-recyclable plastic', 'Metal-laminated', 'Glass-reinforced'], 'correct' => 0],
            ['q' => 'Fillezy AER inflatable films are described as Oxo-degradable, recyclable and:', 'options' => ['REACH approved', 'Toxic', 'Non-degradable', 'Single-use only'], 'correct' => 0],
            ['q' => 'Which of these is a genuine Fillezy AER film format?', 'options' => ['Cushion / Bubble / Quilt / Tube / Wave / Hex', 'Grape pad', 'VCI emitter', 'Desiccant sachet'], 'correct' => 0],
            ['q' => 'Fillezy AER films come in thickness options of:', 'options' => ['15, 22, 35 & 50 microns', '1 mm only', '200 microns only', '5 microns only'], 'correct' => 0],
            ['q' => 'The Fillezy group is described as being how many years old?', 'options' => ['40 years', '2 years', '100 years', '5 years'], 'correct' => 0],
            ['q' => 'Fillezy operates across how many countries globally?', 'options' => ['45 countries', '2 countries', '150 countries', '5 countries'], 'correct' => 0],
            ['q' => 'The main purpose of a void-fill air cushion inside a carton is to:', 'options' => ['Fill empty space and cushion the product', 'Add shipping weight', 'Cool the product', 'Label the box'], 'correct' => 0],
            ['q' => 'A sustainability-focused customer should be offered which Fillezy option?', 'options' => ['Bio-AER (compostable) or Paper AER', 'Only plastic bubble', 'Styrofoam', 'Metal cages'], 'correct' => 0],
            ['q' => 'Fillezy air cushions are produced by:', 'options' => ['Inflating flat film with air at the point of packing', 'Pouring liquid foam', 'Folding cardboard', 'Filling water bags'], 'correct' => 0],
        ];
    }

    // ── Tuffpaulin — +31 (target 35) ──────────────────────────────────────
    private function tuffpaulin(): array
    {
        return [
            ['q' => 'Tuffpaulin film is manufactured using which core technology?', 'options' => ['Cross lamination and cross bonding', 'Single-layer blow moulding', 'Hand weaving', 'Injection moulding'], 'correct' => 0],
            ['q' => 'A regular Tuffpaulin film is how much stronger than a regular polyethylene film?', 'options' => ['150–250% stronger', '10% stronger', 'Weaker', 'The same'], 'correct' => 0],
            ['q' => 'Because it is stronger, roughly what proportion of the weight is needed for the same result?', 'options' => ['About 50% of the weight', '200% of the weight', 'The same weight', '500% of the weight'], 'correct' => 0],
            ['q' => 'Tuffpaulin sheets are joined by heat-sealing, making them:', 'options' => ['100% leakproof', 'Porous', 'Weak at joints', 'Non-repairable'], 'correct' => 0],
            ['q' => 'Which property does Tuffpaulin NOT claim?', 'options' => ['Rots quickly when wet', 'Waterproof', 'Weatherproof', 'UV stabilised'], 'correct' => 0],
            ['q' => 'Tuffpaulin eyelets are described as ultrasonically welded and made of:', 'options' => ['Strong plastic or aluminium', 'Paper', 'Rubber', 'Wood'], 'correct' => 0],
            ['q' => 'An accidental tear in Tuffpaulin film:', 'options' => ['Does not expand and is easily repairable', 'Spreads rapidly', 'Cannot be repaired', 'Dissolves the sheet'], 'correct' => 0],
            ['q' => 'Tuffpaulin is chemically:', 'options' => ['Inert to most chemicals, alkalis and acids', 'Highly reactive with acids', 'Soluble in water', 'Corrosive'], 'correct' => 0],
            ['q' => 'Tuffpaulin carries which quality management certification?', 'options' => ['ISO 9001', 'USDA Organic', 'FSSAI only', 'None'], 'correct' => 0],
            ['q' => 'Tuffpaulin conforms to which Indian Standard?', 'options' => ['IS 14611', 'IS 456', 'IS 800', 'IS 383'], 'correct' => 0],
            ['q' => 'Tuffpaulin has been tested/approved by which laboratory body?', 'options' => ['CIPET', 'FSSAI', 'BIS-only with no lab', 'None'], 'correct' => 0],
            ['q' => 'Tuffpaulin is available across which GSM range?', 'options' => ['70 to 300 GSM', '5 to 10 GSM', '1000 to 2000 GSM', 'Only 50 GSM'], 'correct' => 0],
            ['q' => 'Which is a listed agricultural application of Tuffpaulin?', 'options' => ['Drying of spices and fumigation of tobacco leaves', 'Rust prevention on steel', 'Void-fill packaging', 'Ethylene absorption'], 'correct' => 0],
            ['q' => 'The steel-mill grade with rust protection is called:', 'options' => ['VCI Tuffpaulin', 'Poultry Curtain', 'Vermibed', 'Bike Cover'], 'correct' => 0],
            ['q' => 'VCI Tuffpaulin is used for packaging of:', 'options' => ['Steel coils, sheets and wire rod', 'Fresh fruit', 'Cut flowers', 'Cooking oil'], 'correct' => 0],
            ['q' => 'Which of these is a Tuffpaulin product variant?', 'options' => ['Pond liner', 'VCI emitter', 'Grape pad', 'Air cushion'], 'correct' => 0],
            ['q' => 'Tuffpaulin is manufactured by which company?', 'options' => ['Hi-Tech International', 'A foreign importer', 'A steel mill', 'A paper mill'], 'correct' => 0],
            ['q' => 'Which large government body is a listed Tuffpaulin client?', 'options' => ['Food Corporation of India (FCI)', 'A private grocery shop', 'A local bakery', 'A cinema chain'], 'correct' => 0],
            ['q' => 'Tuffpaulin is notable for a weatherproof service life of approximately:', 'options' => ['3–15 years', '1 week', '50 years', 'A single use'], 'correct' => 0],
            ['q' => 'A key general application of Tuffpaulin is:', 'options' => ['Truck tarpaulin and machinery covers', 'Edible food coating', 'Rust-preventive oil', 'Desiccant sachets'], 'correct' => 0],
            ['q' => 'Tuffpaulin protects covered goods against:', 'options' => ['Sun, rain, dew and dust', 'Only sunlight', 'Only dust', 'Nothing'], 'correct' => 0],
            ['q' => 'Being UV stabilised means Tuffpaulin resists degradation from:', 'options' => ['Sunlight exposure', 'Acid rain only', 'Cold only', 'Vibration'], 'correct' => 0],
            ['q' => 'Which specialised Tuffpaulin cover is used to trap gases during pest control?', 'options' => ['Fumigation cover', 'Bike cover', 'Car cover', 'Brick cover'], 'correct' => 0],
            ['q' => 'Tuffpaulin car covers are offered in sizes such as:', 'options' => ['Hatchback, Sedan and SUV', 'Small and Large only', 'One universal size', 'Truck only'], 'correct' => 0],
            ['q' => 'The criss-cross layers in Tuffpaulin film make the bonding:', 'options' => ['Perfect and practically un-tearable', 'Weak and brittle', 'Water-soluble', 'Sticky'], 'correct' => 0],
            ['q' => 'Tuffpaulin is a better alternative to HDPE fabrics and:', 'options' => ['Ordinary polyethylene sheets', 'Steel plates', 'Glass panels', 'Concrete'], 'correct' => 0],
            ['q' => 'Which is a listed Tuffpaulin agricultural use besides crop drying?', 'options' => ['Farm ponds and fish farming', 'Rust prevention', 'Void fill', 'Flower food'], 'correct' => 0],
            ['q' => 'Poultry curtains are one of the Tuffpaulin products priced by:', 'options' => ['Finish size and GSM', 'Litres', 'Kilograms of fruit', 'Colour only'], 'correct' => 0],
            ['q' => 'Tuffpaulin is described as being light in weight yet:', 'options' => ['Tough and strong', 'Fragile', 'Heavy', 'Brittle'], 'correct' => 0],
            ['q' => 'Being flexible, Tuffpaulin is convenient for covering:', 'options' => ['Irregular shapes and sizes', 'Only flat surfaces', 'Only spheres', 'Nothing curved'], 'correct' => 0],
            ['q' => 'Tuffpaulin has plants in India plus USA, France, Germany, Mexico and:', 'options' => ['China', 'Antarctica', 'Only India', 'Nowhere else'], 'correct' => 0],
        ];
    }

    // ── Eva Bags (I-MIX / Release-X) — +35 (target 35) ────────────────────
    private function evaBags(): array
    {
        return [
            ['q' => 'I-MIX EVA bags are specialty polymer bags used for adding chemicals into:', 'options' => ['Rubber during processing', 'Fresh fruit', 'Steel coils', 'Cooking oil'], 'correct' => 0],
            ['q' => 'During which rubber-processing step are I-MIX bags typically added?', 'options' => ['Banbury or kneader mixing', 'Fruit ripening', 'Steel rolling', 'Paper printing'], 'correct' => 0],
            ['q' => 'Unlike ordinary polyethylene bags, I-MIX EVA bags are:', 'options' => ['Highly compatible with rubber and mix at normal processing temperatures', 'Insoluble in rubber', 'Only for food', 'Made of metal'], 'correct' => 0],
            ['q' => 'Compared with ordinary EVA, I-MIX EVA:', 'options' => ['Melts and blends faster forming a compatible system', 'Takes much longer to melt', 'Never melts', 'Contaminates the batch'], 'correct' => 0],
            ['q' => 'Ordinary EVA is a problem because it can cause:', 'options' => ['Weak areas of LDPE contamination', 'Faster blending', 'Better dosing', 'Cleaner rubber'], 'correct' => 0],
            ['q' => 'A key advantage of I-MIX bags is that they allow:', 'options' => ['Exact dosing of solid and liquid chemicals into rubber', 'Random dosing', 'No dosing control', 'Only liquid dosing'], 'correct' => 0],
            ['q' => 'I-MIX bags reduce which loss during chemical addition?', 'options' => ['Fly loss of chemicals into the atmosphere', 'Heat loss', 'Weight of rubber', 'Colour loss'], 'correct' => 0],
            ['q' => 'Pre-weighing I-MIX bags allows which department to control ratios?', 'options' => ['The quality department', 'The sales department', 'The HR department', 'The finance department'], 'correct' => 0],
            ['q' => 'Using pre-weighed I-MIX bags results in:', 'options' => ['A consistent batch of material every time', 'Random batches', 'Weaker rubber', 'More waste'], 'correct' => 0],
            ['q' => 'I-MIX EVA bags can also be used as liners inside:', 'options' => ['Chemical HDPE bags thrown whole into the Banbury', 'Glass jars', 'Metal drums only', 'Paper envelopes'], 'correct' => 0],
            ['q' => 'The I-MIX grade "IMIX 73" refers to a melting point of about:', 'options' => ['73°C', '110°C', '40°C', '150°C'], 'correct' => 0],
            ['q' => 'Which I-MIX grade has the highest melting point (~110°C)?', 'options' => ['IMIX 110', 'IMIX 73', 'IMIX 83', 'IMIX 40'], 'correct' => 0],
            ['q' => 'The softening point of grade IMIX 73 is about:', 'options' => ['63°C', '100°C', '73°C', '110°C'], 'correct' => 0],
            ['q' => 'I-MIX EVA bags are available in a thickness range of about:', 'options' => ['40 to 150 microns', '1 to 5 microns', '500 to 1000 microns', '1 to 2 mm'], 'correct' => 0],
            ['q' => 'I-MIX EVA bag sizes range from about:', 'options' => ['2"×2" to 50"×50"', '1"×1" to 5"×5"', '10 ft to 20 ft', 'One fixed size'], 'correct' => 0],
            ['q' => 'I-MIX EVA film can be supplied on rolls for which machines?', 'options' => ['Automatic weigh-metric form-fill-seal machines', 'Printing presses', 'Steel rollers', 'Fruit sorters'], 'correct' => 0],
            ['q' => 'The tagline for I-MIX EVA highlights that it is:', 'options' => ['Easily digested by rubber', 'Edible by humans', 'Fireproof', 'Waterproof only'], 'correct' => 0],
            ['q' => 'Release-X embossed films are manufactured from:', 'options' => ['100% virgin low-density polyethylene', 'Recycled steel', 'Corn starch', 'Glass fibre'], 'correct' => 0],
            ['q' => 'The embossing design on Release-X film is:', 'options' => ['Diamond shaped', 'Circular', 'Hexagonal', 'Plain (no design)'], 'correct' => 0],
            ['q' => 'Release-X embossed film comes in a thickness range of about:', 'options' => ['60–150 microns', '5–10 microns', '500–600 microns', '1–2 mm'], 'correct' => 0],
            ['q' => 'Release-X film is described as anti-tacky and:', 'options' => ['High-melt, protecting rubber bales from sticking', 'Low-melt and sticky', 'Water soluble', 'Edible'], 'correct' => 0],
            ['q' => 'A primary function of Release-X film is to act as:', 'options' => ['Separators and liners for rubber sheets', 'A food wrap', 'A rust inhibitor', 'A desiccant'], 'correct' => 0],
            ['q' => 'Release-X film protects raw rubber from:', 'options' => ['Dust, dirt and contamination during storage and transport', 'Sunlight only', 'Ethylene gas', 'Corrosion'], 'correct' => 0],
            ['q' => 'When EVA film is wrapped on raw rubber, before usage it:', 'options' => ['Need not be taken off — it blends in', 'Must always be removed and discarded', 'Must be washed', 'Must be heated separately'], 'correct' => 0],
            ['q' => 'Which industry is a listed user of Release-X embossed film?', 'options' => ['Tyre and conveyor-belt manufacturers', 'Bakeries', 'Airlines', 'Banks'], 'correct' => 0],
            ['q' => 'Release-X film is available in standard colours such as:', 'options' => ['Natural, milky white, blue, red, yellow', 'Only black', 'Only transparent', 'Only gold'], 'correct' => 0],
            ['q' => 'A benefit of Release-X being easy to remove before processing is:', 'options' => ['Cost, manpower and time saving', 'Higher waste', 'Slower processing', 'More contamination'], 'correct' => 0],
            ['q' => 'I-MIX is a brand belonging to which group?', 'options' => ['Hi-Tech Group', 'A foreign conglomerate', 'A rubber estate', 'A tyre retailer'], 'correct' => 0],
            ['q' => 'The I-MIX company profile states it manufactures over how many specialized products?', 'options' => ['Over 300', 'Over 5', 'Over 5,000', 'Exactly 10'], 'correct' => 0],
            ['q' => 'The I-MIX group serves approximately how many customers?', 'options' => ['3,000+', '30', '300,000', '10'], 'correct' => 0],
            ['q' => 'The group behind I-MIX promises worldwide delivery of products in about:', 'options' => ['10–12 days', '1 hour', '6 months', '2 years'], 'correct' => 0],
            ['q' => 'Which of these is listed as a sister brand alongside I-MIX?', 'options' => ['Rust-X / AquaFlush / Doctorrust', 'Coca-Cola', 'Amazon', 'Tata Steel'], 'correct' => 0],
            ['q' => 'The I-MIX group has how many manufacturing plants (per the profile)?', 'options' => ['9 units', '1 unit', '100 units', 'None'], 'correct' => 0],
            ['q' => 'Using EVA liners means the chemical inside:', 'options' => ['Need not be emptied out — the whole liner goes in', 'Must be poured out first', 'Is wasted', 'Cannot be dosed'], 'correct' => 0],
            ['q' => 'The overall value proposition of I-MIX EVA bags for a rubber plant is:', 'options' => ['Consistent batches, exact dosing and cleaner work environment', 'Cheaper fruit storage', 'Faster steel rolling', 'Rust prevention'], 'correct' => 0],
        ];
    }
}
