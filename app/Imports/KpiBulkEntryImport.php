<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Reads the bulk-entry sheet back as raw rows.
 *
 * Deliberately does no mapping or validation: the controller owns that,
 * because only it knows which assignment ids the uploader is entitled to
 * touch. A row for somebody outside their scope has to be refused, not
 * quietly imported, and that decision cannot be made here.
 */
class KpiBulkEntryImport implements ToArray
{
    public function array(array $rows): array
    {
        return $rows;
    }
}
