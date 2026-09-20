<?php

namespace App\Support;

/**
 * Tally item names are mostly customer-prefixed SKUs ("ZEPTO-SC SMALL BAG-8X5X16-WHITE-P(N)",
 * "KALYANI-AL-NEUTRASAFE-RUST REMOVER LIQUID") -- 5,600 distinct names for a few
 * dozen actual product families. This buckets a name into a family so item
 * analytics is readable.
 *
 * It is a DETECTION by keyword, not master data: the first matching rule wins,
 * anything unrecognised is "Other", and the Items view shows how much value sits
 * in "Other" so a gap is visible rather than hidden. To change how something is
 * grouped, edit RULES -- it is the only place the logic lives.
 */
class ProductGroup
{
    public const OTHER = 'Other';
    public const SERVICES = 'Services & freight';

    /** Ordered: the FIRST rule that matches wins, so specific patterns sit above general ones. */
    private const RULES = [
        'Compostable & SC bags' => '/COMPOSTABLE|\bSC\b.*\bBAG\b/',
        'Compounds & masterbatch' => '/COMPOUND|MASTER ?BATCH|(^|[\s\-])MB[\s\-]|\bDANA\b|GRANULE|\bPBAT\b/',
        'PLA straws' => '/STRAW|\bPLA\b/',
        'Dr.Bio polymers' => '/DR\.? ?BIO|POLYMER/',
        'Zorbit' => '/ZORBIT/',
        'Tuffpaulin' => '/TUFF ?PAULIN/',
        'Keep It Fresh (KIF)' => '/\bKIF\b|KEEP IT FRESH|SHELF ?LIFE/',
        'Desiccants & dry pads' => '/DESICCANT|DEHUMID|DRY ?PADS?/',
        'Cleaners & rust removers' => '/NEUTRASAFE|RUST ?REMOVER|AQUAFLUSH|CLEANER/',
        'Rust preventives & oils' => '/\bRPO\b|\bRPA\b|RUST[\s\-]?X|RUST PREVENTIVE|PRESERVATIVE|\bOIL\b|\b135[\s\-]?[A-Z]\b|CL[\s\-]?005|SLIDE ?WAYS?|INSTAWASH/',
        'Fillezy & air bubble' => '/FILLE?ZE?Y|AIR ?(BUBBLE|TUBE|QUILT)|BUBBLE|CUSHION/',
        'Cleaning media' => '/CLEANING MEDIA/',
        // Hitech's numbered grades: named by their code because the item name says nothing more.
        'Numbered grades (4200 / 4500 / 19000 ...)' => '/\b(4200|4500|9000|19000|80000)\b|AMPLIFI|PRECAM/',
        // "NON VCI" must beat the VCI rules below, or plain plastic is counted as VCI.
        'Plastic sheets & bags (non-VCI)' => '/NON[\s\-]?VCI/',
        'VCI paper' => '/VCI.*PAPER|PAPER.*VCI/',
        'VCI plastic' => '/VCI/',
        'Tapes' => '/TAPE/',
        'Films & foils' => '/BARRIER|BOPP|FOIL|FILM|YOTEX|PRISM SHEET/',
        'HDPE / LDPE rolls' => '/HDPE|LDPE/',
        'Poly bags & pouches' => '/POUCH|\bBAGS?\b/',
        'Paper rolls' => '/PAPER/',
        'Machines & spares' => '/MACHINE/',
    ];

    /** [group => hex colour], in a stable order, for charts. */
    public static function palette(): array
    {
        $colors = ['#0f766e', '#2563eb', '#7c3aed', '#db2777', '#ea580c', '#ca8a04', '#16a34a', '#0891b2', '#4f46e5', '#be123c', '#65a30d', '#9333ea', '#0d9488', '#c2410c', '#475569', '#b45309', '#0369a1', '#a21caf', '#15803d', '#dc2626', '#1d4ed8', '#7e22ce'];
        $out = [];
        foreach (array_keys(self::RULES) as $i => $group) {
            $out[$group] = $colors[$i % count($colors)];
        }
        $out[self::SERVICES] = '#94a3b8';
        $out[self::OTHER] = '#cbd5e1';
        return $out;
    }

    /** Tally's HSN starting "99" is a service (e.g. 996511 = goods transport), never a product. */
    public static function detect(?string $itemName, ?string $hsn = null): string
    {
        if ($hsn !== null && str_starts_with(trim($hsn), '99')) {
            return self::SERVICES;
        }

        $name = mb_strtoupper(trim((string) $itemName));
        if ($name === '') {
            return self::OTHER;
        }
        if (preg_match('/FREIGHT|TRANSPORT|LOADING|PACKING CHARGE|COURIER/', $name)) {
            return self::SERVICES;
        }
        foreach (self::RULES as $group => $pattern) {
            if (preg_match($pattern, $name)) {
                return $group;
            }
        }
        return self::OTHER;
    }
}
