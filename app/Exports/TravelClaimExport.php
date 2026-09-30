<?php

namespace App\Exports;

use App\Models\TravelClaim;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * One claim, as the verifier needs to check it offline.
 *
 * The on-screen table shows "View" buttons where a proof exists; a spreadsheet
 * cannot click, so each proof column carries Yes / No instead — that is the
 * thing being verified (was a bill attached for this amount?), and it filters
 * and totals, which a button never could.
 *
 * Amounts are written as numbers, not "₹1,234.00" strings, so the verifier can
 * sum and sort them. Currency formatting is applied by the sheet.
 *
 * Columns come from one ordered list (columnDefs()) instead of being typed
 * out separately in the headings, the row loop, the totals row and the
 * currency-format list - that duplication is exactly how this sheet ended up
 * exporting Auto/Taxi, Transport, Bills and Freight columns years after the
 * entry form stopped collecting them (they were hardcoded to 0 server-side,
 * but nobody removed their export columns). Add or remove a field once, here.
 */
class TravelClaimExport implements FromArray, WithTitle, ShouldAutoSize, WithEvents, WithColumnFormatting
{
    private const HEADER_ROWS = 7;   // title + 5 detail lines + blank

    public function __construct(private TravelClaim $claim)
    {
        $this->claim->loadMissing(['user', 'items', 'advances']);
    }

    public function title(): string
    {
        return 'Claim ' . $this->claim->id;
    }

    /**
     * One entry per exported column, in on-screen order. 'sum' rolls the
     * column into the TOTAL row; 'money' additionally applies currency
     * formatting and folds into Row Total. 'bool' renders the value as
     * Yes/No. 'value' is null only for the one column (Row Total) computed
     * from the others rather than read straight off the item.
     */
    private function columnDefs(): array
    {
        return [
            ['label' => 'Date',                    'value' => fn ($i) => $i->date ? \Carbon\Carbon::parse($i->date)->format('d M Y') : ''],
            ['label' => 'From',                     'value' => fn ($i) => $i->from_location],
            ['label' => 'To',                       'value' => fn ($i) => $i->to_location],
            ['label' => 'Party Visited',             'value' => fn ($i) => $i->party_visited],
            ['label' => 'Mode of Travel',            'value' => fn ($i) => $i->mode_of_travel],
            ['label' => 'Currency',                  'value' => fn ($i) => $i->currency ?: 'INR'],
            ['label' => 'Start Meter',               'value' => fn ($i) => $i->start_meter],
            ['label' => 'End Meter',                 'value' => fn ($i) => $i->end_meter],
            ['label' => 'KMs',                       'value' => fn ($i) => (float) $i->distance_km, 'sum' => true],
            ['label' => 'Conveyance',                'value' => fn ($i) => (float) $i->conveyance_amount, 'sum' => true, 'money' => true],
            ['label' => 'Food',                      'value' => fn ($i) => (float) $i->food_allowance, 'sum' => true, 'money' => true],
            ['label' => 'Lodging',                   'value' => fn ($i) => (float) $i->lodging_amount, 'sum' => true, 'money' => true],
            ['label' => 'Courier',                   'value' => fn ($i) => (float) $i->courier_amount, 'sum' => true, 'money' => true],
            ['label' => 'Courier Proof',              'value' => fn ($i) => $i->courier_proof, 'bool' => true],
            ['label' => 'Toll',                      'value' => fn ($i) => (float) $i->toll_amount, 'sum' => true, 'money' => true],
            ['label' => 'Toll Proof',                 'value' => fn ($i) => $i->toll_proof, 'bool' => true],
            ['label' => 'Special Approval',          'value' => fn ($i) => (float) $i->special_approval_amount, 'sum' => true, 'money' => true],
            ['label' => 'Special Approval Proof',     'value' => fn ($i) => $i->special_approval_proof, 'bool' => true],
            ['label' => 'Other',                     'value' => fn ($i) => (float) $i->other_amount, 'sum' => true, 'money' => true],
            ...$this->legacyAmountDefs(),
            ['label' => 'Row Total',                 'value' => null, 'sum' => true, 'money' => true],
            ['label' => 'Penalty Applied',            'value' => fn ($i) => $i->penalty_applied, 'bool' => true],
            ['label' => 'Flagged',                   'value' => fn ($i) => $i->is_flagged, 'bool' => true],
            ['label' => 'Remarks',                   'value' => fn ($i) => $i->remarks],
        ];
    }

    /**
     * Fields the entry form no longer collects (saved as 0 since). They stay off
     * the sheet - unless a claim actually carries an amount in one (an older or
     * imported claim), in which case its column comes back. Dropping it would
     * make the sheet total silently disagree with the claim's Gross Total.
     */
    private const LEGACY_AMOUNTS = [
        'auto_taxi_amount'       => 'Auto/Taxi',
        'transport_amount'       => 'Transport',
        'bills_amount'           => 'Bills',
        'freight_amount'         => 'Freight',
        'additional_food_amount' => 'Additional Food',
    ];

    private function legacyAmountDefs(): array
    {
        $defs = [];
        foreach (self::LEGACY_AMOUNTS as $field => $label) {
            if ($this->claim->items->contains(fn ($i) => (float) ($i->{$field} ?? 0) != 0.0)) {
                $defs[] = ['label' => $label, 'value' => fn ($i) => (float) ($i->{$field} ?? 0), 'sum' => true, 'money' => true];
            }
        }
        return $defs;
    }

    /** Spreadsheet column letters (A, B, ... AA, AB...) for every 'money' column. */
    private function amountColumns(): array
    {
        return $this->columnLettersWhere(fn ($d) => !empty($d['money']));
    }

    /** Same idea for every column rolled into the TOTAL row - KMs included, which isn't money. */
    private function sumColumns(): array
    {
        return $this->columnLettersWhere(fn ($d) => !empty($d['sum']));
    }

    private function columnLettersWhere(callable $predicate): array
    {
        $letters = [];
        foreach ($this->columnDefs() as $idx => $def) {
            if ($predicate($def)) {
                $letters[] = Coordinate::stringFromColumnIndex($idx + 1);
            }
        }
        return $letters;
    }

    public function array(): array
    {
        $c = $this->claim;
        $yn = fn ($v) => $v ? 'Yes' : 'No';
        $n  = fn ($v) => (float) ($v ?? 0);
        $defs = $this->columnDefs();

        $rows = [
            ['TRAVEL & EXPENSE CLAIM #' . $c->id],
            ['Employee',   $c->user->name ?? '—',        '', 'Status',      ucfirst((string) $c->status)],
            ['Company',    $c->company,                  '', 'Claim Month', $c->claim_month],
            ['Bank',       trim($c->bank_account_name . ' ' . $c->bank_account_no), '', 'IFSC', $c->bank_ifsc],
            ['Gross Total', $n($c->total_amount),        '', 'Advances',    $n($c->total_advances)],
            ['Net Payable', $n($c->net_payable),         '', 'Submitted',   optional($c->created_at)->format('d M Y H:i')],
            [''],
            array_map(fn ($d) => $d['label'], $defs),
        ];

        // Row Total is the sum of every money column on the sheet - the same
        // list, so a column shown is always a column counted.
        $moneyDefs = array_filter($defs, fn ($d) => !empty($d['money']) && $d['label'] !== 'Row Total');
        $rowTotal = fn ($i) => array_sum(array_map(fn ($d) => $n(($d['value'])($i)), $moneyDefs));

        foreach ($c->items as $item) {
            $rows[] = array_map(function ($d) use ($item, $rowTotal, $yn) {
                if ($d['label'] === 'Row Total') {
                    return $rowTotal($item);
                }
                $value = ($d['value'])($item);
                return !empty($d['bool']) ? $yn($value) : $value;
            }, $defs);
        }

        // A totals row the verifier can check against Gross Total above. If the
        // two disagree, something is wrong with the claim, and that is exactly
        // what this export exists to surface.
        $totals = array_fill(0, count($defs), null);
        $totals[0] = 'TOTAL';
        foreach ($defs as $idx => $d) {
            if (empty($d['sum']) || $d['label'] === 'Row Total') {
                continue;
            }
            $totals[$idx] = $c->items->sum($d['value']);
        }
        $rowTotalIdx = array_search('Row Total', array_column($defs, 'label'), true);
        $totals[$rowTotalIdx] = $c->items->sum($rowTotal);
        $rows[] = [''];
        $rows[] = $totals;

        if ($c->advances && $c->advances->count()) {
            $rows[] = [''];
            $rows[] = ['ADVANCES DRAWN'];
            $rows[] = ['Date', 'Type', 'Amount', 'Remarks'];
            foreach ($c->advances as $a) {
                $rows[] = [
                    $a->advance_date ? \Carbon\Carbon::parse($a->advance_date)->format('d M Y') : '',
                    $a->advance_type,
                    $n($a->amount),
                    $a->remarks,
                ];
            }
        }

        return $rows;
    }

    public function columnFormats(): array
    {
        return array_fill_keys($this->amountColumns(), '#,##0.00');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet    = $event->sheet->getDelegate();
                $lastCol  = $sheet->getHighestColumn();
                $headRow  = self::HEADER_ROWS + 1;              // the column-heading row
                $firstRow = $headRow + 1;
                $lastRow  = $sheet->getHighestRow();

                // Title
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)
                      ->getStartColor()->setRGB('0D7377');
                $sheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(24);

                // Header block labels
                $sheet->getStyle('A2:A6')->getFont()->setBold(true);
                $sheet->getStyle('D2:D6')->getFont()->setBold(true);
                $sheet->getStyle('A6:B6')->getFont()->setBold(true)->setSize(12);

                // Column headings
                $sheet->getStyle("A{$headRow}:{$lastCol}{$headRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
                    'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($headRow)->setRowHeight(30);

                // Borders over the data grid
                if ($lastRow >= $firstRow) {
                    $sheet->getStyle("A{$headRow}:{$lastCol}{$lastRow}")->applyFromArray([
                        'borders' => ['allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ]],
                    ]);
                }

                // The writer drops a 0.0 as an empty cell, which on a
                // verification sheet reads as "no figure given" rather than
                // "nil" — and leaves gaps in any SUM or filter the verifier
                // applies. Put the zeros back explicitly.
                foreach ($this->sumColumns() as $col) {
                    for ($row = $firstRow; $row <= $lastRow; $row++) {
                        if ($sheet->getCell("A{$row}")->getValue() === '') {
                            continue;                       // spacer row
                        }
                        if ($sheet->getCell("{$col}{$row}")->getValue() === null) {
                            $sheet->setCellValueExplicit("{$col}{$row}", 0,
                                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        }
                    }
                }

                // The same freeze the user asked for on screen: heading row and
                // the first three columns stay put while scrolling.
                $sheet->freezePane('D' . $firstRow);
                $sheet->setAutoFilter("A{$headRow}:{$lastCol}{$headRow}");
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
            },
        ];
    }
}
