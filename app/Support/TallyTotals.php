<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Stamps three derived facts onto tally_invoices so the analytics can total them with a single-table query
 * instead of joining tally_sale_lines/tally_outstanding every time:
 *   taxable_total / tax_total  -- summed from this invoice's tally_sale_lines rows (NULL = no line/GST detail exists for it)
 *   open_amount                -- what tally_outstanding still lists as open under the same bill number (NULL = not outstanding)
 *
 * Recomputed from the CURRENT contents of those two tables, so it can never drift from them. Shared by both
 * import paths that can change tally_sale_lines/tally_outstanding/tally_invoices -- the daily-file import
 * (ImportTallyFilesCommand) and the live API push (TallyPushController) -- so there is exactly one place this
 * logic lives, whichever path last touched the data.
 */
class TallyTotals
{
    public static function stamp(): void
    {
        DB::transaction(function () {
            DB::update('UPDATE tally_invoices SET taxable_total = NULL, tax_total = NULL, open_amount = NULL');
            DB::update('UPDATE tally_invoices i
                        JOIN (SELECT invoice_no, SUM(taxable_amount) AS t, SUM(tax_amount) AS x FROM tally_sale_lines GROUP BY invoice_no) s ON s.invoice_no = i.invoice_no
                        SET i.taxable_total = s.t, i.tax_total = s.x');
            DB::update("UPDATE tally_invoices i
                        JOIN (SELECT bill_ref, SUM(amount) AS a FROM tally_outstanding WHERE ledger_group = 'Sundry Debtors' GROUP BY bill_ref) o ON o.bill_ref = i.invoice_no
                        SET i.open_amount = o.a");
        });
    }
}
