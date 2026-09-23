<?php

namespace App\Services;

use App\Exceptions\TallyGatewayException;
use App\Support\TallyFileReader;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for TallyPrime's HTTP/XML gateway.
 *
 * Safety, because this talks to accounting software people are working in:
 *  - It can only EXPORT. Every request is built here, and send() refuses anything that is not an Export
 *    (Tally's same gateway also accepts Imports, which would change the books).
 *  - Every data request names the one company allowed (services.tally.gateway_company) and pulls refuse to start
 *    unless that company is the one Tally has loaded.
 *  - Tally is single-threaded, so requests go ONE AT A TIME, one day per request, with a short pause between them
 *    and a hard timeout. A big request freezes Tally for its users.
 *
 * What was learned about the gateway (see the sandbox tests):
 *  - A voucher collection ignores SVFROMDATE/SVTODATE; only a filter with LITERAL dates limits it.
 *  - Responses contain control characters written as &#4; and tags with an undeclared "UDF:" prefix, which strict
 *    XML parsers reject; cleanXml() removes the first, libxml tolerates the second.
 */
class TallyGateway
{
    /** Only the fields we need; a full voucher export is ~65 KB per voucher, this is ~3-5 KB. */
    private const FETCH = 'Date, VoucherNumber, VoucherTypeName, PartyLedgerName, PartyGSTIN, PlaceOfSupply, Reference, AlterID, GUID, '
        . 'AllInventoryEntries.StockItemName, AllInventoryEntries.BilledQty, AllInventoryEntries.Rate, AllInventoryEntries.Amount, '
        . 'AllInventoryEntries.GSTHSNName, AllInventoryEntries.RateDetails, '
        . 'LedgerEntries.LedgerName, LedgerEntries.Amount, AllLedgerEntries.LedgerName, AllLedgerEntries.Amount';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $expectedCompany = '',
        private readonly int $timeout = 30,
        private readonly int $pauseMs = 250,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            (string) config('services.tally.gateway_url'),
            (string) config('services.tally.gateway_company'),
            (int) config('services.tally.gateway_timeout', 30),
        );
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function expectedCompany(): string
    {
        return $this->expectedCompany;
    }

    // ------------------------------------------------------------------ talking to Tally

    /** Names of the companies Tally currently has loaded. Tally only answers for loaded companies. */
    public function companies(): array
    {
        return self::parseCompanies($this->send(self::companiesRequest()));
    }

    /**
     * Throws unless the configured company is loaded. Returns its name. Call before any data pull.
     */
    public function assertCompany(): string
    {
        if ($this->expectedCompany === '') {
            throw new TallyGatewayException('No company is configured (TALLY_GATEWAY_COMPANY). Set the exact name of the ONE company that may be read.');
        }
        $loaded = $this->companies();
        if (!in_array($this->expectedCompany, $loaded, true)) {
            throw new TallyGatewayException(sprintf(
                'Refusing to read: "%s" is not loaded in Tally. Loaded: %s.',
                $this->expectedCompany,
                $loaded ? '"' . implode('", "', $loaded) . '"' : 'nothing',
            ));
        }
        return $this->expectedCompany;
    }

    /**
     * Sales vouchers (with item lines and GST rates) for each day from..to inclusive, normalised to plain arrays.
     * One request per day, one at a time.
     *
     * @return array<int, array<string, mixed>>
     */
    public function salesVouchers(CarbonInterface $from, CarbonInterface $to, ?callable $progress = null): array
    {
        $company = $this->assertCompany();
        $out = [];
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day = $day->copy()->addDay()) {
            $t = microtime(true);
            $vouchers = self::parseVouchers($this->send(self::salesVouchersRequest($company, $day, $day)));
            if ($progress) {
                $progress($day, count($vouchers), (microtime(true) - $t) * 1000);
            }
            array_push($out, ...$vouchers);
            if ($this->pauseMs > 0 && $day->lt($to)) {
                usleep($this->pauseMs * 1000);
            }
        }
        return $out;
    }

    /**
     * The inventory master: every stock item with its group, unit, stock in hand and GST rates. One request
     * (a master list is small next to vouchers), still behind the company guard.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stockItems(): array
    {
        return self::parseStockItems($this->send(self::stockItemsRequest($this->assertCompany())));
    }

    /** POSTs an EXPORT request and returns the answer as clean UTF-8 text. Never sends anything that could change Tally's data. */
    protected function send(string $xml): string
    {
        self::assertExportOnly($xml);
        try {
            $response = Http::withBody($xml, 'text/xml')->connectTimeout(5)->timeout($this->timeout)->post($this->baseUrl);
        } catch (ConnectionException $e) {
            throw new TallyGatewayException("Cannot reach Tally at {$this->baseUrl}: is it running with its Client/Server port on? (" . $e->getMessage() . ')');
        }
        if (!$response->successful()) {
            throw new TallyGatewayException("Tally answered HTTP {$response->status()}.");
        }
        return self::decode($response->body());
    }

    public static function assertExportOnly(string $xml): void
    {
        if (!preg_match('~<TALLYREQUEST>\s*Export\s*</TALLYREQUEST>~i', $xml) || preg_match('~<TALLYREQUEST>\s*(Import|Execute|Action)~i', $xml)) {
            throw new TallyGatewayException('Blocked: only Export requests may be sent to Tally.');
        }
    }

    // ------------------------------------------------------------------ requests

    public static function companiesRequest(): string
    {
        return '<ENVELOPE><HEADER><VERSION>1</VERSION><TALLYREQUEST>Export</TALLYREQUEST><TYPE>Collection</TYPE><ID>List of Companies</ID></HEADER>'
            . '<BODY><DESC><STATICVARIABLES><SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT></STATICVARIABLES>'
            . '<TDL><TDLMESSAGE><COLLECTION NAME="List of Companies" ISMODIFY="No"><TYPE>Company</TYPE><FETCH>Name</FETCH></COLLECTION></TDLMESSAGE></TDL></DESC></BODY></ENVELOPE>';
    }

    public static function stockItemsRequest(string $company): string
    {
        $name = htmlspecialchars($company, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<ENVELOPE><HEADER><VERSION>1</VERSION><TALLYREQUEST>Export</TALLYREQUEST><TYPE>Collection</TYPE><ID>HRXStock</ID></HEADER><BODY><DESC>'
            . '<STATICVARIABLES><SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT><SVCURRENTCOMPANY>' . $name . '</SVCURRENTCOMPANY></STATICVARIABLES>'
            . '<TDL><TDLMESSAGE><COLLECTION NAME="HRXStock" ISMODIFY="No"><TYPE>StockItem</TYPE>'
            . '<FETCH>Name, Parent, Category, BaseUnits, OpeningBalance, OpeningValue, ClosingBalance, ClosingValue, ClosingRate, GSTDetails</FETCH></COLLECTION></TDLMESSAGE></TDL></DESC></BODY></ENVELOPE>';
    }

    /** Tally reads dates like 1-May-2025 inside a filter formula; the SVFROMDATE/SVTODATE variables do NOT limit a collection. */
    public static function dateLiteral(CarbonInterface $d): string
    {
        return $d->format('j-M-Y');
    }

    public static function salesVouchersRequest(string $company, CarbonInterface $from, CarbonInterface $to): string
    {
        $name = htmlspecialchars($company, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $f = self::dateLiteral($from);
        $t = self::dateLiteral($to);
        return '<ENVELOPE><HEADER><VERSION>1</VERSION><TALLYREQUEST>Export</TALLYREQUEST><TYPE>Collection</TYPE><ID>HRXSales</ID></HEADER><BODY><DESC>'
            . '<STATICVARIABLES><SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT><SVCURRENTCOMPANY>' . $name . '</SVCURRENTCOMPANY></STATICVARIABLES>'
            . '<TDL><TDLMESSAGE><COLLECTION NAME="HRXSales" ISMODIFY="No"><TYPE>Voucher</TYPE><FILTERS>HRXIsSales, HRXRange</FILTERS><FETCH>' . self::FETCH . '</FETCH></COLLECTION>'
            . '<SYSTEM TYPE="Formulae" NAME="HRXIsSales">$$IsSales:$VoucherTypeName</SYSTEM>'
            . '<SYSTEM TYPE="Formulae" NAME="HRXRange">$Date &gt;= $$Date:"' . $f . '" AND $Date &lt;= $$Date:"' . $t . '"</SYSTEM>'
            . '</TDLMESSAGE></TDL></DESC></BODY></ENVELOPE>';
    }

    // ------------------------------------------------------------------ reading answers (pure functions)

    /** Tally answers in UTF-8 or UTF-16LE (with a BOM). */
    public static function decode(string $bytes): string
    {
        if (str_starts_with($bytes, "\xFF\xFE")) {
            return mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16LE');
        }
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            return substr($bytes, 3);
        }
        return $bytes;
    }

    /** Removes what XML 1.0 forbids: control characters, whether raw or written as &#4; style references. */
    public static function cleanXml(string $text): string
    {
        $text = preg_replace('/&#(?:[0-8]|1[12]|1[4-9]|2[0-9]|3[01]);/', '', $text) ?? $text;
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? $text;
    }

    private static function load(string $xml): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string(self::cleanXml($xml), 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_PARSEHUGE);
        libxml_clear_errors();                       // the undeclared UDF: prefix produces warnings only; the tree is still built
        libxml_use_internal_errors($previous);
        if (!$doc) {
            throw new TallyGatewayException('Tally sent an answer that is not readable XML.');
        }
        return $doc;
    }

    /** @return string[] */
    public static function parseCompanies(string $xml): array
    {
        $names = [];
        foreach (self::load($xml)->xpath('//COMPANY[@NAME]') ?: [] as $c) {
            $n = trim((string) $c['NAME']);
            if ($n !== '') {
                $names[] = $n;
            }
        }
        return array_values(array_unique($names));
    }

    /** Tally's amount for stock in hand is negative; return the value the way a person reads it (and never a signed zero). */
    private static function heldValue(string $v): ?float
    {
        $a = TallyFileReader::amount($v);
        return $a === null ? null : ($a == 0.0 ? 0.0 : -$a);
    }

    /** "-18 nos" -> [-18.0, "nos"]. Stock can go negative in Tally; TallyFileReader::quantity() would return nothing for it. */
    private static function signedQuantity(string $v): array
    {
        $negative = str_starts_with($v, '-') || str_starts_with($v, '(-)');
        [$q, $unit] = TallyFileReader::quantity(ltrim(str_replace('(-)', '', $v), '-'));
        return [$q !== null && $negative ? -$q : $q, $unit];
    }

    /**
     * @return array<int, array<string, mixed>> one entry per stock item:
     *   name, group, category, unit, opening_qty, opening_value, closing_qty, closing_unit, closing_value, closing_rate,
     *   closing_rate_unit, gst {from, taxability, rates{head=>%}} (the latest applicable entry, or null)
     *
     * Values are the value of the stock HELD (quantity x rate). Tally writes stock in hand as a negative amount (its debit
     * convention, like the customer's ledger line on a sale), so the raw sign is flipped here; a negative QUANTITY is
     * stock issued beyond what was received.
     */
    public static function parseStockItems(string $xml): array
    {
        $s = fn($n) => trim((string) $n);
        $out = [];
        foreach (self::load($xml)->xpath('//STOCKITEM') ?: [] as $i) {
            $name = $s($i['NAME']);
            if ($name === '') {
                continue;                             // Tally's own header counter, not an item
            }
            [$closingQty, $closingUnit] = self::signedQuantity($s($i->CLOSINGBALANCE));
            [$openingQty] = self::signedQuantity($s($i->OPENINGBALANCE));
            [$closingRate, $rateUnit] = TallyFileReader::rate($s($i->CLOSINGRATE));

            $gst = [];
            foreach ($i->{'GSTDETAILS.LIST'} as $g) {
                $rates = [];
                foreach ($g->{'STATEWISEDETAILS.LIST'} as $state) {
                    foreach ($state->{'RATEDETAILS.LIST'} as $rd) {
                        $head = $s($rd->GSTRATEDUTYHEAD);
                        if ($head !== '') {
                            $rates[$head] = (float) $s($rd->GSTRATE);
                        }
                    }
                }
                $from = $s($g->APPLICABLEFROM);
                if ($rates === [] && $from === '' && $s($g->TAXABILITY) === '') {
                    continue;                             // the empty placeholder Tally writes for an item with no GST setup
                }
                $gst[] = [
                    'from' => preg_match('/^(\d{4})(\d{2})(\d{2})$/', $from, $m) ? "{$m[1]}-{$m[2]}-{$m[3]}" : null,
                    'taxability' => $s($g->TAXABILITY) ?: null,
                    'rates' => $rates,
                ];
            }
            usort($gst, fn($a, $b) => strcmp((string) $b['from'], (string) $a['from']));   // newest first

            $category = $s($i->CATEGORY);
            $out[] = [
                'name' => $name, 'group' => $s($i->PARENT) ?: null, 'category' => in_array($category, ['', 'Not Applicable'], true) ? null : $category,
                'unit' => in_array($s($i->BASEUNITS), ['', 'Not Applicable'], true) ? null : $s($i->BASEUNITS),
                'opening_qty' => $openingQty, 'opening_value' => self::heldValue($s($i->OPENINGVALUE)),
                'closing_qty' => $closingQty, 'closing_unit' => $closingUnit, 'closing_value' => self::heldValue($s($i->CLOSINGVALUE)),
                'closing_rate' => $closingRate, 'closing_rate_unit' => $rateUnit, 'gst' => $gst[0] ?? null,
            ];
        }
        return $out;
    }

    /**
     * @return array<int, array<string, mixed>> one entry per voucher:
     *   guid, alter_id, date (Y-m-d), number, type, party, party_gstin, place_of_supply, reference, cancelled, total,
     *   lines[] {item, qty, unit, rate, rate_unit, amount, hsn, gst{head=>rate %}}, ledgers[] {name, amount}
     */
    public static function parseVouchers(string $xml): array
    {
        $s = fn($n) => trim((string) $n);
        $out = [];
        foreach (self::load($xml)->xpath('//VOUCHER') ?: [] as $v) {
            $number = $s($v->VOUCHERNUMBER);
            $guid = $s($v->GUID);
            if ($number === '' && $guid === '') {
                continue;                             // a wrapper element, not a voucher
            }

            // Tally can send a voucher's ledger lines under BOTH tags, and they overlap (ALL... is the complete set, including the
            // sales ledger; the other repeats the party and tax lines). Read ONE of them, never both, or tax is counted twice.
            $ledgers = [];
            $ledgerTag = count($v->{'ALLLEDGERENTRIES.LIST'}) ? 'ALLLEDGERENTRIES.LIST' : 'LEDGERENTRIES.LIST';
            foreach ($v->{$ledgerTag} as $l) {
                $name = $s($l->LEDGERNAME);
                if ($name !== '') {
                    $ledgers[] = ['name' => $name, 'amount' => TallyFileReader::amount($s($l->AMOUNT))];
                }
            }

            $lines = [];
            foreach (['ALLINVENTORYENTRIES.LIST', 'INVENTORYENTRIES.LIST'] as $tag) {
                foreach ($v->{$tag} as $l) {
                    $item = $s($l->STOCKITEMNAME);
                    if ($item === '') {
                        continue;                     // an empty placeholder Tally writes for vouchers without items
                    }
                    [$qty, $unit] = TallyFileReader::quantity($s($l->BILLEDQTY) !== '' ? $s($l->BILLEDQTY) : $s($l->ACTUALQTY));
                    [$rate, $rateUnit] = TallyFileReader::rate($s($l->RATE));
                    $gst = [];
                    foreach ($l->{'RATEDETAILS.LIST'} as $rd) {
                        $head = $s($rd->GSTRATEDUTYHEAD);
                        if ($head !== '') {
                            $gst[$head] = (float) $s($rd->GSTRATE);
                        }
                    }
                    $lines[] = [
                        'item' => $item, 'qty' => $qty, 'unit' => $unit, 'rate' => $rate, 'rate_unit' => $rateUnit,
                        'amount' => TallyFileReader::amount($s($l->AMOUNT)), 'hsn' => $s($l->GSTHSNNAME) ?: null, 'gst' => $gst,
                    ];
                }
            }

            $party = $s($v->PARTYLEDGERNAME);
            $total = null;
            foreach ($ledgers as $l) {
                if ($party !== '' && $l['name'] === $party && $l['amount'] !== null) {
                    $total = abs($l['amount']);       // the customer's own ledger line carries the invoice total
                    break;
                }
            }

            $date = $s($v->DATE);
            $out[] = [
                'guid' => $guid, 'alter_id' => (int) $s($v->ALTERID), 'number' => $number, 'type' => $s($v->VOUCHERTYPENAME),
                'date' => preg_match('/^(\d{4})(\d{2})(\d{2})$/', $date, $m) ? "{$m[1]}-{$m[2]}-{$m[3]}" : null,
                'party' => $party ?: null, 'party_gstin' => $s($v->PARTYGSTIN) ?: null, 'place_of_supply' => $s($v->PLACEOFSUPPLY) ?: null,
                'reference' => $s($v->REFERENCE) ?: null, 'cancelled' => strcasecmp($s($v->ISCANCELLED), 'Yes') === 0 || strcasecmp($s($v->ISDELETED), 'Yes') === 0,
                'total' => $total, 'lines' => $lines, 'ledgers' => $ledgers,
            ];
        }
        return $out;
    }
}
