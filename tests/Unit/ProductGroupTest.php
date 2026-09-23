<?php

namespace Tests\Unit;

use App\Support\ProductGroup;
use PHPUnit\Framework\TestCase;

/** Pure keyword rules -- no app boot, no database. */
class ProductGroupTest extends TestCase
{
    /** @dataProvider knownItems */
    public function test_known_item_names_land_in_the_right_family(string $item, ?string $hsn, string $expected): void
    {
        $this->assertSame($expected, ProductGroup::detect($item, $hsn));
    }

    public static function knownItems(): array
    {
        return [
            'compostable bag'   => ['ZEPTO-COMPOSTABLE MEDIUM BAG-12X16', null, 'Compostable & SC bags'],
            'SC bag'            => ['ZEPTO-SC SMALL BAG-8X5X16-WHITE-P(N)', null, 'Compostable & SC bags'],
            'compound'          => ['STRAW COMPOUND', null, 'Compounds & masterbatch'],   // "COMPOUND" outranks "STRAW"
            'PLA straw'         => ['PLA STRAW 6MM', null, 'PLA straws'],
            'rust remover'      => ['KALYANI-AL-NEUTRASAFE-RUST REMOVER LIQUID', '29152100', 'Cleaners & rust removers'],
            'RPO'               => ['HITECH-RPO 135 E', '27101990', 'Rust preventives & oils'],
            'non-VCI is not VCI' => ['SIL-NON VCI PLASTIC SHEET-100"X84"-TRANSPARENT-150MIC-NP', '39201019', 'Plastic sheets & bags (non-VCI)'],
            'VCI plastic'       => ['TRUE-VCI PLASTIC TUBE ROLL-220MM-BLUE', '39201019', 'VCI plastic'],
            'VCI paper'         => ['HITECH VCI PAPER ROLL', null, 'VCI paper'],
            'KIF pouch'         => ['RELIANCE-H-KIF SHELF LIFE EXTENSION POUCHES-225X300MM', null, 'Keep It Fresh (KIF)'],
            'grade code'        => ['HITECH 4200 - 20 GRAM', null, 'Numbered grades (4200 / 4500 / 19000 ...)'],
            'fillezy spelling A' => ['KNP-FILLEZY HEXA PAPPER ROLL', null, 'Fillezy & air bubble'],
            'fillezy spelling B' => ['MARC-FILLZEY AIR TUBES-800MM', null, 'Fillezy & air bubble'],
        ];
    }

    public function test_service_hsn_is_a_service_whatever_the_item_is_called(): void
    {
        $this->assertSame(ProductGroup::SERVICES, ProductGroup::detect('HITECH VCI PLASTIC ROLL', '996511'));
    }

    public function test_freight_words_mark_a_service(): void
    {
        $this->assertSame(ProductGroup::SERVICES, ProductGroup::detect('FREIGHT CHARGES'));
    }

    public function test_unrecognised_and_empty_names_fall_into_other_never_a_wrong_family(): void
    {
        $this->assertSame(ProductGroup::OTHER, ProductGroup::detect('PLASTIC WASTE (18%)'));
        $this->assertSame(ProductGroup::OTHER, ProductGroup::detect(''));
        $this->assertSame(ProductGroup::OTHER, ProductGroup::detect(null));
    }

    public function test_every_group_has_a_chart_colour(): void
    {
        $palette = ProductGroup::palette();
        $this->assertArrayHasKey(ProductGroup::OTHER, $palette);
        $this->assertArrayHasKey(ProductGroup::SERVICES, $palette);
        $this->assertArrayHasKey('VCI plastic', $palette);
    }
}
