<?php

namespace App\Excel;

use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Every Excel export goes through here (config/excel.php value_binder).
 *
 * The stock binder turns any text that starts with "=" into a live formula, so
 * a name, remark or customer typed as =HYPERLINK(...)/=WEBSERVICE(...) ran in
 * the spreadsheet of whoever opened the export. Such text is now written as
 * plain text. The one kind of formula the exports build on purpose - a
 * =HYPERLINK("url","label") to this site or Google Maps - is still let through
 * when it has exactly that shape (no quote inside, so nothing can be chained).
 */
class SafeValueBinder extends DefaultValueBinder
{
    private const LINK = '~^=HYPERLINK\("(https?://[^"]+)","([^"]*)"\)$~i';

    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value) && $value !== '' && $value[0] === '=' && !$this->isOwnLink($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    private function isOwnLink(string $value): bool
    {
        if (!preg_match(self::LINK, $value, $m)) {
            return false;
        }
        $host = strtolower((string) parse_url($m[1], PHP_URL_HOST));
        $allowed = array_filter([
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            app()->runningInConsole() ? null : strtolower(request()->getHost()),
            'www.google.com',
        ]);

        return in_array($host, $allowed, true);
    }
}
