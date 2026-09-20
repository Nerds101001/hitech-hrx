<?php

namespace Tests\Unit;

use App\Exceptions\TallyGatewayException;
use App\Services\TallyGateway;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * The gateway client talks to accounting software, so its two promises are tested hardest: it can only READ, and it
 * only reads the ONE configured company. Pure tests: real (sandbox) Tally output kept as a fixture, and a stand-in
 * for the network -- nothing here boots the app, touches a database or reaches Tally.
 *
 * tests/Fixtures/tally_sandbox_vouchers.xml is a genuine answer from a Tally sandbox, trimmed to four vouchers, with
 * the quirks that broke a naive parser left in: "&#4;" control characters, tags with an undeclared "UDF:" prefix,
 * and Tally's own <VOUCHER>0</VOUCHER> counter in the header, which is not a voucher.
 */
class TallyGatewayTest extends TestCase
{
    private function fixture(): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/tally_sandbox_vouchers.xml');
    }

    /** A gateway whose "network" is a list of canned answers; records everything it is asked. */
    private function fakeGateway(string $company, array $loaded, string $voucherAnswer = '<ENVELOPE/>'): TallyGateway
    {
        return new class ('http://tally.test', $company, 30, 0, $loaded, $voucherAnswer) extends TallyGateway {
            public array $sent = [];

            public function __construct(string $url, string $company, int $timeout, int $pause, private array $loaded, private string $answer)
            {
                parent::__construct($url, $company, $timeout, $pause);
            }

            protected function send(string $xml): string
            {
                self::assertExportOnly($xml);            // the real send() enforces this too
                $this->sent[] = $xml;
                if (str_contains($xml, 'List of Companies')) {
                    return '<ENVELOPE><BODY><DATA><COLLECTION>' . implode('', array_map(fn($n) => '<COMPANY NAME="' . htmlspecialchars($n) . '"/>', $this->loaded)) . '</COLLECTION></DATA></BODY></ENVELOPE>';
                }
                return $this->answer;
            }
        };
    }

    // ------------------------------------------------------------------ reading answers

    public function test_control_characters_are_removed_however_tally_writes_them(): void
    {
        $this->assertSame('<A> Not Applicable</A>', TallyGateway::cleanXml('<A>&#4; Not Applicable</A>'));
        $this->assertSame('<A>ab</A>', TallyGateway::cleanXml("<A>a\x04b</A>"));
        $this->assertSame("<A>tab\tnew\nline</A>", TallyGateway::cleanXml("<A>tab\tnew\nline</A>"));       // legal whitespace stays
        $this->assertSame('<A>&amp; &#233;</A>', TallyGateway::cleanXml('<A>&amp; &#233;</A>'));            // ordinary references stay
    }

    public function test_answers_in_utf16_or_with_a_utf8_bom_are_decoded(): void
    {
        $this->assertSame('<A>é₹</A>', TallyGateway::decode("\xFF\xFE" . mb_convert_encoding('<A>é₹</A>', 'UTF-16LE', 'UTF-8')));
        $this->assertSame('<A>é₹</A>', TallyGateway::decode("\xEF\xBB\xBF<A>é₹</A>"));
        $this->assertSame('<A>é₹</A>', TallyGateway::decode('<A>é₹</A>'));
    }

    public function test_the_header_counter_is_not_a_voucher_and_the_quirky_answer_still_parses(): void
    {
        $vouchers = TallyGateway::parseVouchers($this->fixture());

        $this->assertSame(['1', '8', '6', '35'], array_column($vouchers, 'number'));
    }

    public function test_an_accounting_voucher_has_its_total_from_the_party_line_and_no_item_lines(): void
    {
        $v = TallyGateway::parseVouchers($this->fixture())[0];

        $this->assertSame('Amar Enterprises', $v['party']);
        $this->assertSame('2025-05-01', $v['date']);
        $this->assertSame(250000.0, $v['total']);            // Tally writes the customer's line as a negative (debit)
        $this->assertSame([], $v['lines']);
        $this->assertFalse($v['cancelled']);
    }

    public function test_item_lines_split_quantity_and_rate_into_number_and_unit(): void
    {
        $line = TallyGateway::parseVouchers($this->fixture())[1]['lines'][0];

        $this->assertSame('Item1', $line['item']);
        $this->assertSame(100.0, $line['qty']);
        $this->assertSame('nos', $line['unit']);
        $this->assertSame(213.0, $line['rate']);
        $this->assertSame('nos', $line['rate_unit']);
        $this->assertSame(21300.0, $line['amount']);
        $this->assertNull($line['hsn']);
    }

    public function test_a_gst_invoice_gives_hsn_rates_by_tax_head_and_its_figures_reconcile(): void
    {
        $v = TallyGateway::parseVouchers($this->fixture())[3];
        $line = $v['lines'][0];

        $this->assertSame('4820', $line['hsn']);
        $this->assertSame(2.5, $line['gst']['CGST']);
        $this->assertSame(2.5, $line['gst']['SGST/UTGST']);
        $this->assertSame(5.0, $line['gst']['IGST']);

        // taxable x rate = the tax ledger lines, and taxable + tax = what the customer owes
        $ledger = array_column($v['ledgers'], 'amount', 'name');
        $this->assertSame(round($line['amount'] * 2.5 / 100, 2), $ledger['CGST']);
        $this->assertSame(round($line['amount'] * 2.5 / 100, 2), $ledger['SGST']);
        $this->assertSame($line['amount'] + $ledger['CGST'] + $ledger['SGST'], $v['total']);
    }

    public function test_ledger_lines_are_read_once_when_tally_sends_them_under_both_tags(): void
    {
        // A real invoice made in the sandbox (Butter, 500 nos @ 1.50, GST 18%). Tally sent its ledger lines under
        // BOTH ALLLEDGERENTRIES.LIST (4 lines) and LEDGERENTRIES.LIST (3 of the same lines again).
        $xml = file_get_contents(__DIR__ . '/../Fixtures/tally_sandbox_invoice_butter.xml');
        $this->assertSame(4, substr_count($xml, '<ALLLEDGERENTRIES.LIST>'));
        $this->assertSame(3, substr_count($xml, '<LEDGERENTRIES.LIST>'));

        $v = TallyGateway::parseVouchers($xml);

        $this->assertCount(1, $v);
        $this->assertSame(
            ['Chanda Enterprises' => -885.0, 'GST Sales' => 750.0, 'CGST' => 67.5, 'SGST' => 67.5],
            array_column($v[0]['ledgers'], 'amount', 'name')
        );
        $this->assertCount(4, $v[0]['ledgers']);                                       // not 7: nothing counted twice
        $this->assertEqualsWithDelta(0.0, array_sum(array_column($v[0]['ledgers'], 'amount')), 0.005);   // double entry: the lines balance
    }

    public function test_a_butter_invoice_reads_end_to_end_and_reconciles(): void
    {
        $v = TallyGateway::parseVouchers(file_get_contents(__DIR__ . '/../Fixtures/tally_sandbox_invoice_butter.xml'))[0];
        $line = $v['lines'][0];

        $this->assertSame('39', $v['number']);
        $this->assertSame('2026-03-01', $v['date']);
        $this->assertSame('Chanda Enterprises', $v['party']);
        $this->assertSame(885.0, $v['total']);
        $this->assertSame(['item' => 'Butter', 'qty' => 500.0, 'unit' => 'nos', 'rate' => 1.5, 'rate_unit' => 'nos', 'amount' => 750.0], array_intersect_key($line, array_flip(['item', 'qty', 'unit', 'rate', 'rate_unit', 'amount'])));
        $this->assertSame(9.0, $line['gst']['CGST']);                                   // 18% from the item master, split CGST/SGST
        $this->assertSame(9.0, $line['gst']['SGST/UTGST']);
        $this->assertSame(18.0, $line['gst']['IGST']);
        $this->assertSame($line['qty'] * $line['rate'], $line['amount']);               // quantity x rate = taxable value
    }

    public function test_tags_with_an_undeclared_prefix_do_not_stop_a_voucher_being_read(): void
    {
        $this->assertStringContainsString('UDF:', $this->fixture());
        $six = array_values(array_filter(TallyGateway::parseVouchers($this->fixture()), fn($v) => $v['number'] === '6'));

        $this->assertCount(1, $six);
        $this->assertSame(21300.0, $six[0]['total']);
    }

    public function test_an_answer_that_is_not_xml_is_reported_not_guessed_at(): void
    {
        $this->expectException(TallyGatewayException::class);
        TallyGateway::parseVouchers('Tally is busy, try later');
    }

    public function test_loaded_company_names_are_read_and_blanks_ignored(): void
    {
        $xml = '<ENVELOPE><BODY><DATA><COLLECTION><COMPANY NAME="Bhrama Enterprises"/><COMPANY NAME=""/><COMPANY NAME="Bhrama Enterprises"/></COLLECTION></DATA></BODY></ENVELOPE>';

        $this->assertSame(['Bhrama Enterprises'], TallyGateway::parseCompanies($xml));
    }

    // ------------------------------------------------------------------ inventory (stock items)

    private function stockItems(): array
    {
        $items = TallyGateway::parseStockItems(file_get_contents(__DIR__ . '/../Fixtures/tally_sandbox_stock_items.xml'));

        return array_column($items, null, 'name');            // the header counter <STOCKITEM>0</STOCKITEM> must not appear
    }

    public function test_stock_items_read_group_unit_and_stock_in_hand(): void
    {
        $items = $this->stockItems();

        $this->assertSame(['Coffee Powder', 'Apple MacBook Pro Laptop', 'GST Coffee', 'Computer 50', 'Monitor 11'], array_keys($items));
        $coffee = $items['Coffee Powder'];
        $this->assertSame('Coffee', $coffee['group']);
        $this->assertSame('nos', $coffee['unit']);
        $this->assertSame(200.0, $coffee['closing_qty']);
        $this->assertSame('nos', $coffee['closing_unit']);
        $this->assertSame(20.0, $coffee['closing_rate']);
    }

    public function test_stock_value_reads_the_way_a_person_expects_not_in_tallys_debit_sign(): void
    {
        $items = $this->stockItems();

        // Tally writes 200 nos in hand as -4000; it is worth +4000 (quantity x rate)
        $this->assertSame(4000.0, $items['Coffee Powder']['closing_value']);
        $this->assertSame($items['Coffee Powder']['closing_qty'] * $items['Coffee Powder']['closing_rate'], $items['Coffee Powder']['closing_value']);
        // negative stock (issued more than received) keeps its sign: -18 nos x 150,000
        $this->assertSame(-18.0, $items['Apple MacBook Pro Laptop']['closing_qty']);
        $this->assertSame(-2700000.0, $items['Apple MacBook Pro Laptop']['closing_value']);
    }

    public function test_items_with_no_movement_or_no_unit_have_blanks_not_zeros_or_placeholders(): void
    {
        $items = $this->stockItems();

        $this->assertNull($items['Computer 50']['closing_qty']);
        $this->assertNull($items['Computer 50']['closing_value']);
        $this->assertNull($items['Monitor 11']['unit']);       // Tally says "Not Applicable"
        $this->assertNull($items['Computer 50']['gst']);       // no GST set up: no empty GST record either
    }

    public function test_an_items_gst_rates_are_read_by_tax_head_with_the_date_they_apply_from(): void
    {
        $gst = $this->stockItems()['GST Coffee']['gst'];

        $this->assertSame('Taxable', $gst['taxability']);
        $this->assertSame('2025-04-01', $gst['from']);
        $this->assertSame(2.5, $gst['rates']['CGST']);
        $this->assertSame(2.5, $gst['rates']['SGST/UTGST']);
        $this->assertSame(5.0, $gst['rates']['IGST']);
    }

    public function test_the_inventory_request_is_export_only_and_names_the_company(): void
    {
        $xml = TallyGateway::stockItemsRequest('Acme & Sons');

        TallyGateway::assertExportOnly($xml);
        $this->assertStringContainsString('<SVCURRENTCOMPANY>Acme &amp; Sons</SVCURRENTCOMPANY>', $xml);
        $this->assertStringContainsString('<TYPE>StockItem</TYPE>', $xml);
        $this->assertNotNull(simplexml_load_string($xml));
    }

    public function test_the_inventory_is_not_read_from_a_company_that_is_not_the_allowed_one(): void
    {
        $g = $this->fakeGateway('Hi Tech Group', ['Bhrama Enterprises']);

        try {
            $g->stockItems();
            $this->fail('Expected a refusal');
        } catch (TallyGatewayException $e) {
            $this->assertStringContainsString('is not loaded', $e->getMessage());
        }
        $this->assertCount(1, $g->sent);                       // only the "which companies?" question was sent
    }

    // ------------------------------------------------------------------ the two promises: read-only, one company

    public function test_only_export_requests_can_be_sent(): void
    {
        TallyGateway::assertExportOnly(TallyGateway::companiesRequest());
        TallyGateway::assertExportOnly(TallyGateway::salesVouchersRequest('X', Carbon::create(2025, 5, 1), Carbon::create(2025, 5, 1)));
        $this->addToAssertionCount(2);

        foreach (['Import', 'Execute', 'Action'] as $kind) {
            try {
                TallyGateway::assertExportOnly("<ENVELOPE><HEADER><TALLYREQUEST>{$kind}</TALLYREQUEST></HEADER></ENVELOPE>");
                $this->fail("A {$kind} request must be blocked");
            } catch (TallyGatewayException $e) {
                $this->assertStringContainsString('only Export', $e->getMessage());
            }
        }

        $this->expectException(TallyGatewayException::class);        // no request type at all is not "export" either
        TallyGateway::assertExportOnly('<ENVELOPE><BODY/></ENVELOPE>');
    }

    public function test_the_sales_request_names_the_company_and_limits_by_literal_dates(): void
    {
        $xml = TallyGateway::salesVouchersRequest('Acme & "Sons" <Ltd>', Carbon::create(2025, 5, 1), Carbon::create(2025, 5, 31));

        $this->assertStringContainsString('<SVCURRENTCOMPANY>Acme &amp; &quot;Sons&quot; &lt;Ltd&gt;</SVCURRENTCOMPANY>', $xml);   // escaped, cannot break out of the tag
        $this->assertStringContainsString('$$Date:"1-May-2025"', $xml);
        $this->assertStringContainsString('$$Date:"31-May-2025"', $xml);
        $this->assertStringNotContainsString('SVFROMDATE', $xml);                                                                 // Tally ignores those for a collection
        $this->assertNotNull(simplexml_load_string($xml));                                                                        // and the request itself is valid XML
    }

    public function test_a_company_must_be_configured_before_anything_is_read(): void
    {
        $g = $this->fakeGateway('', ['Bhrama Enterprises']);

        try {
            $g->assertCompany();
            $this->fail('Expected a refusal');
        } catch (TallyGatewayException $e) {
            $this->assertStringContainsString('No company is configured', $e->getMessage());
            $this->assertCount(0, $g->sent);                          // it did not even ask Tally
        }
    }

    public function test_a_pull_is_refused_if_the_allowed_company_is_not_the_one_loaded(): void
    {
        $g = $this->fakeGateway('Hi Tech Group', ['Bhrama Enterprises'], '<ENVELOPE/>');

        try {
            $g->salesVouchers(Carbon::create(2025, 6, 1), Carbon::create(2025, 6, 3));
            $this->fail('Expected a refusal');
        } catch (TallyGatewayException $e) {
            $this->assertStringContainsString('"Hi Tech Group" is not loaded', $e->getMessage());
            $this->assertStringContainsString('Bhrama Enterprises', $e->getMessage());
        }
        $this->assertCount(1, $g->sent);                              // only the "which companies?" question; no data request left the building
        $this->assertStringContainsString('List of Companies', $g->sent[0]);
    }

    public function test_a_pull_makes_one_request_per_day_in_order_each_naming_the_company(): void
    {
        $g = $this->fakeGateway('Bhrama Enterprises', ['Bhrama Enterprises', 'Some Other Co'], $this->fixture());

        $vouchers = $g->salesVouchers(Carbon::create(2025, 6, 1), Carbon::create(2025, 6, 3));

        $this->assertCount(4, $g->sent);                              // 1 company check + 3 days
        foreach ([1, 2, 3] as $i => $day) {
            $this->assertStringContainsString('<SVCURRENTCOMPANY>Bhrama Enterprises</SVCURRENTCOMPANY>', $g->sent[$i + 1]);
            $this->assertStringContainsString('$Date &gt;= $$Date:"' . $day . '-Jun-2025" AND $Date &lt;= $$Date:"' . $day . '-Jun-2025"', $g->sent[$i + 1]);
        }
        $this->assertCount(12, $vouchers);                            // 4 vouchers in the canned answer x 3 days
    }
}
