<?php

namespace App\Exports;

use App\Models\TravelClaim;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
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

    /** Column headings, in the same order as the on-screen table. */
    private function columns(): array
    {
        return [
            'Date', 'From', 'To', 'Party Visited', 'Mode of Travel',
            'Start Meter', 'End Meter', 'KMs',
            'Odometer Photo', 'Petrol Slip', 'Conveyance',
            'Auto/Taxi', 'Auto/Taxi Proof',
            'Food', 'Lodging',
            'Courier', 'Courier Proof',
            'Transport', 'Transport Proof',
            'Bills', 'Bills Proof',
            'Freight', 'Freight Proof',
            'Toll', 'Toll Proof',
            'Additional Food', 'Additional Food Proof',
            'Special Approval', 'Special Approval Proof',
            'Other',
            'Row Total', 'Penalty Applied', 'Flagged', 'Remarks',
        ];
    }

    /** Money columns, for currency formatting and the totals row. */
    private function amountColumns(): array
    {
        return ['K', 'L', 'N', 'O', 'P', 'R', 'T', 'V', 'X', 'Z', 'AB', 'AD', 'AE'];
    }

    public function array(): array
    {
        $c = $this->claim;
        $yn = fn($v) => $v ? 'Yes' : 'No';
        $n  = fn($v) => (float) ($v ?? 0);

        $rows = [
            ['TRAVEL & EXPENSE CLAIM #' . $c->id],
            ['Employee',   $c->user->name ?? '—',        '', 'Status',      ucfirst((string) $c->status)],
            ['Company',    $c->company,                  '', 'Claim Month', $c->claim_month],
            ['Bank',       trim($c->bank_account_name . ' ' . $c->bank_account_no), '', 'IFSC', $c->bank_ifsc],
            ['Gross Total', $n($c->total_amount),        '', 'Advances',    $n($c->total_advances)],
            ['Net Payable', $n($c->net_payable),         '', 'Submitted',   optional($c->created_at)->format('d M Y H:i')],
            [''],
            $this->columns(),
        ];

        foreach ($c->items as $item) {
            $rowTotal = $n($item->conveyance_amount) + $n($item->auto_taxi_amount)
                + $n($item->food_allowance) + $n($item->lodging_amount)
                + $n($item->courier_amount) + $n($item->transport_amount)
                + $n($item->bills_amount) + $n($item->freight_amount)
                + $n($item->toll_amount) + $n($item->additional_food_amount)
                + $n($item->special_approval_amount) + $n($item->other_amount);

            $rows[] = [
                $item->date ? \Carbon\Carbon::parse($item->date)->format('d M Y') : '',
                $item->from_location,
                $item->to_location,
                $item->party_visited,
                $item->mode_of_travel,
                $item->start_meter,
                $item->end_meter,
                $n($item->distance_km),
                $yn($item->photo_path),
                $yn($item->petrol_slip_proof),
                $n($item->conveyance_amount),
                $n($item->auto_taxi_amount),
                $yn($item->auto_taxi_proof),
                $n($item->food_allowance),
                $n($item->lodging_amount),
                $n($item->courier_amount),
                $yn($item->courier_proof),
                $n($item->transport_amount),
                $yn($item->transport_proof),
                $n($item->bills_amount),
                $yn($item->bills_proof),
                $n($item->freight_amount),
                $yn($item->freight_proof),
                $n($item->toll_amount),
                $yn($item->toll_proof),
                $n($item->additional_food_amount),
                $yn($item->additional_food_proof),
                $n($item->special_approval_amount),
                $yn($item->special_approval_proof),
                $n($item->other_amount),
                $rowTotal,
                $yn($item->penalty_applied),
                $yn($item->is_flagged),
                $item->remarks,
            ];
        }

        // A totals row the verifier can check against Gross Total above. If the
        // two disagree, something is wrong with the claim, and that is exactly
        // what this export exists to surface.
        $sum = fn(callable $f) => $c->items->sum($f);
        $totals = array_fill(0, count($this->columns()), null);
        $totals[0]  = 'TOTAL';
        $totals[7]  = $sum(fn($i) => (float) $i->distance_km);
        $totals[10] = $sum(fn($i) => (float) $i->conveyance_amount);
        $totals[11] = $sum(fn($i) => (float) $i->auto_taxi_amount);
        $totals[13] = $sum(fn($i) => (float) $i->food_allowance);
        $totals[14] = $sum(fn($i) => (float) $i->lodging_amount);
        $totals[15] = $sum(fn($i) => (float) $i->courier_amount);
        $totals[17] = $sum(fn($i) => (float) $i->transport_amount);
        $totals[19] = $sum(fn($i) => (float) $i->bills_amount);
        $totals[21] = $sum(fn($i) => (float) $i->freight_amount);
        $totals[23] = $sum(fn($i) => (float) $i->toll_amount);
        $totals[25] = $sum(fn($i) => (float) $i->additional_food_amount);
        $totals[27] = $sum(fn($i) => (float) $i->special_approval_amount);
        $totals[29] = $sum(fn($i) => (float) $i->other_amount);
        $totals[30] = array_sum(array_map(fn($k) => (float) ($totals[$k] ?? 0), [10,11,13,14,15,17,19,21,23,25,27,29]));
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
        return array_fill_keys(
            array_map(fn($col) => $col, $this->amountColumns()),
            '#,##0.00'
        );
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
                foreach (array_merge(['H'], $this->amountColumns()) as $col) {
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
