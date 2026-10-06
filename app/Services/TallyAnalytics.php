<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\SetuCustomer;
use App\Models\TallyImport;
use App\Support\ProductGroup;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read-only analytics over the Tally tables (loaded by `hrx:import-tally`).
 *
 * Speed comes from three things: every panel is computed once per
 * (import, day, filters) and cached; list panels cache the FULL sorted-later
 * row set and sort/search/page it in PHP, so paging and re-sorting never touch
 * the database; and the page asks for the whole overview in one request.
 * The cache key carries the import id, so a fresh import invalidates everything.
 *
 * What each source can honestly answer:
 *   tally_invoices       every invoice: value incl. GST, customer, salesperson, series, dispatch plant
 *   tally_invoice_lines  every invoice: item + quantity only (no value)
 *   tally_sale_lines     ~27% of invoices (~69% of value): item, HSN, GST %, rate, taxable value
 *   tally_outstanding    open bills; bill_ref equals invoice_no for some invoices
 * Anything built on tally_sale_lines is scoped to those invoices, and the
 * coverage figures returned here let the page say so.
 */
class TallyAnalytics
{
    private const TTL = 43200;   // 12 h; the key already changes with every import and every day
    private const CACHE_VERSION = 6;   // bump when the shape of a cached payload changes

    /**
     * A table with at most this many rows is sent to the browser whole and sorted, searched and paged THERE:
     * no request at all per click. Bigger ones are paged by the server.
     */
    private const LOCAL_LIMIT = 2500;

    /**
     * What makes two invoices "the same customer": the Tally party code -- except code 0, which Tally uses for several
     * unrelated parties (a sister concern, a marketplace account...). Those are told apart by name instead.
     */
    private const PARTY_KEY = "CASE WHEN i.party_code = '0' THEN CONCAT('0|', i.party_name) ELSE i.party_code END";

    private array $f;

    public function __construct(array $filters)
    {
        $this->f = $filters;
    }

    // ------------------------------------------------------------ filters & periods

    private static ?array $bounds = null;

    private static ?int $importId = null;

    public static function importId(): int
    {
        // Every cache here is keyed on this number. It used to follow only the file import, so a day's API push
        // changed the data without changing the key and the dashboards kept answering from the old cache until
        // the date rolled over. Fold in the latest successful push (as a large, distinct number) so a push
        // refreshes them too, while a quiet day (no push) still reuses the cache.
        if (self::$importId === null) {
            try {
                $push = (int) (\Illuminate\Support\Facades\DB::table('tally_push_runs')->whereIn('status', ['ok', 'partial'])->max('id') ?? 0);
            } catch (\Throwable $e) {
                $push = 0;                       // table not there yet (code ahead of its migration): file import only
            }
            self::$importId = (int) (TallyImport::latestGood()?->id ?? 0) + 1_000_000 * $push;
        }
        return self::$importId;
    }

    /** [first invoice date, last invoice date] in the loaded data. */
    public static function bounds(): array
    {
        return self::$bounds ??= self::shared('bounds', function () {
            $b = DB::table('tally_invoices')->selectRaw('MIN(invoice_date) AS lo, MAX(invoice_date) AS hi')->first();
            return [$b->lo, $b->hi];
        });
    }

    /** Quick ranges, anchored on the LAST invoice date in the data (not on today, which may be ahead of the export). */
    public static function presets(): array
    {
        [$lo, $hi] = self::bounds();
        if ($lo === null) {
            return [];
        }
        $end = Carbon::parse($hi);
        $month = $end->copy()->startOfMonth();
        $lastMonth = $month->copy()->subMonthNoOverflow();
        // Financial quarters start in Jan / Apr / Jul / Oct. Built from year+month, not setMonth(), which overflows on a 31st.
        $qStart = Carbon::create($end->year, intdiv($end->month - 1, 3) * 3 + 1, 1);
        $fyStart = Carbon::create($end->month >= 4 ? $end->year : $end->year - 1, 4, 1);

        $all = ['from' => $lo, 'to' => $hi];
        $candidates = [
            ['key' => 'mtd',    'label' => 'This month',    'from' => $month->toDateString(),     'to' => $hi],
            ['key' => 'lm',     'label' => 'Last month',    'from' => $lastMonth->toDateString(), 'to' => $lastMonth->copy()->endOfMonth()->toDateString()],
            ['key' => 'last30', 'label' => 'Last 30 days',  'from' => $end->copy()->subDays(29)->toDateString(), 'to' => $hi],
            ['key' => 'qtd',    'label' => 'This quarter',  'from' => $qStart->toDateString(),    'to' => $hi],
            ['key' => 'fytd',   'label' => 'Financial year', 'from' => $fyStart->toDateString(),  'to' => $hi],
            ['key' => 'all',    'label' => 'All data',      'from' => $lo,                        'to' => $hi],
        ];

        $out = [];
        foreach ($candidates as $c) {
            $isAll = $c['key'] === 'all';
            $sameAsAll = $c['from'] === $all['from'] && $c['to'] === $all['to'];
            if (!$isAll && ($c['from'] < $lo || $sameAsAll)) {
                continue;            // starts before the data does, or is simply "All data" again
            }
            if ($c['to'] < $lo) {
                continue;
            }
            $out[] = $c;
        }
        return $out;
    }

    /** Normalises request input to a safe filter set (dates clamped to the loaded data). */
    public static function filters(array $in): array
    {
        [$lo, $hi] = self::bounds();
        $lo ??= now()->toDateString();
        $hi ??= $lo;

        $presets = collect(self::presets())->keyBy('key');
        $key = (string) ($in['preset'] ?? 'mtd');
        if ($key !== 'custom' && isset($presets[$key])) {
            $from = $presets[$key]['from'];
            $to = $presets[$key]['to'];
        } else {
            $key = 'custom';
            $from = self::date($in['from'] ?? null) ?? ($presets['mtd']['from'] ?? $lo);
            $to = self::date($in['to'] ?? null) ?? $hi;
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $from = max($from, $lo);
        $to = min($to, $hi);
        if ($from > $to) {
            $from = $to;
        }

        $type = (string) ($in['type'] ?? 'customer');
        $series = (string) ($in['series'] ?? '');
        return [
            'preset' => $key,
            'from' => $from,
            'to' => $to,
            'type' => in_array($type, ['customer', 'branch', 'all'], true) ? $type : 'customer',
            'sp' => mb_substr(trim((string) ($in['sp'] ?? '')), 0, 120),
            'series' => preg_match('/^[A-Za-z0-9\-]{1,20}$/', $series) ? $series : '',
        ];
    }

    private static function date(mixed $d): ?string
    {
        return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4)) ? $d : null;
    }

    /**
     * The window compared against. A whole-month selection (1st..Nth) is compared
     * with the same days of the month before; anything else with the equal-length
     * window just before it. Null when that window isn't fully inside the loaded
     * data -- a "change" against half a month of data would be a lie.
     */
    public function prevRange(): ?array
    {
        $from = Carbon::parse($this->f['from']);
        $to = Carbon::parse($this->f['to']);
        if ($from->day === 1 && $from->isSameMonth($to)) {
            $pf = $from->copy()->subMonthNoOverflow()->startOfMonth();
            $pt = $pf->copy()->addDays($to->day - 1);
            if ($pt->gt($pf->copy()->endOfMonth())) {
                $pt = $pf->copy()->endOfMonth();
            }
        } else {
            $pt = $from->copy()->subDay();
            $pf = $pt->copy()->subDays($from->diffInDays($to));
        }
        [$lo] = self::bounds();
        return ($lo === null || $pf->toDateString() < $lo) ? null : [$pf->toDateString(), $pt->toDateString()];
    }

    public function filtersInUse(): array
    {
        return $this->f;
    }

    // ------------------------------------------------------------ query scopes

    /**
     * The filters as WHERE clauses on a table aliased $a. All three tables carry the
     * filter columns (the import copies them onto the line tables), so nothing here joins.
     */
    private function scope(Builder $q, bool $prev = false, string $a = 'i'): Builder
    {
        [$from, $to] = $prev ? $this->prevRange() : [$this->f['from'], $this->f['to']];
        $q->whereBetween("{$a}.invoice_date", [$from, $to]);
        if ($this->f['type'] === 'customer') {
            $q->where("{$a}.is_branch_transfer", 0)->whereNotNull("{$a}.party_code");
        } elseif ($this->f['type'] === 'branch') {
            $q->where("{$a}.is_branch_transfer", 1);
        }
        if ($this->f['sp'] !== '') {
            $q->where("{$a}.salesperson", $this->f['sp']);
        }
        if ($this->f['series'] !== '') {
            $q->where("{$a}.series", $this->f['series']);
        }
        return $q;
    }

    private function inv(bool $prev = false): Builder
    {
        return $this->scope(DB::table('tally_invoices as i'), $prev, 'i');
    }

    /** Value lines (SaleReportCRM) of the filtered invoices -- only the invoices Tally's CRM report includes. */
    private function sale(bool $prev = false): Builder
    {
        return $this->scope(DB::table('tally_sale_lines as l'), $prev, 'l');
    }

    /** Quantity-only lines (Invoice Details) of the filtered invoices -- every invoice has these. */
    private function qty(bool $prev = false): Builder
    {
        return $this->scope(DB::table('tally_invoice_lines as q'), $prev, 'q');
    }

    private function withoutPeriod(): self
    {
        [$lo, $hi] = self::bounds();
        return new self(['from' => $lo, 'to' => $hi] + $this->f);
    }

    // ------------------------------------------------------------ caching

    private function remember(string $panel, array $params, callable $compute): mixed
    {
        return self::cached($panel . '.' . md5(json_encode([$this->f, $params])), $compute);
    }

    /** The same per-import, per-day cache, for the other Tally pages: $name must identify everything the result depends on. */
    public static function memo(string $name, callable $compute): mixed
    {
        return self::cached('memo.' . $name, $compute);
    }

    /** For figures that do not depend on the filters (ageing, date bounds, notices): computed once per import and day. */
    private static function shared(string $name, callable $compute): mixed
    {
        return self::cached('shared.' . $name, $compute);
    }

    private static function cached(string $suffix, callable $compute): mixed
    {
        $key = 'tally.an.v' . self::CACHE_VERSION . '.' . self::importId() . '.' . now()->format('Ymd') . '.' . $suffix;
        $store = Cache::store('file');
        try {
            $hit = $store->get($key);
        } catch (\Throwable) {
            $hit = null;
        }
        if ($hit !== null) {
            return $hit;
        }
        $value = $compute();
        try {
            $store->put($key, $value, self::TTL);
        } catch (\Throwable) {
            // a cache that can't be written must never break the page
        }
        return $value;
    }

    /** Pre-computes the default first-paint views so the first person to open the page isn't the one who waits. */
    public static function warm(): void
    {
        self::$bounds = self::$importId = null;      // a new import has just landed
        foreach (['mtd', 'lm', 'all'] as $key) {
            (new self(self::filters(['preset' => $key])))->overview();
        }
    }

    // ------------------------------------------------------------ small helpers

    private static function f(mixed $v, int $dp = 2): float
    {
        return round((float) $v, $dp);
    }

    private static function delta(float $cur, ?float $prev): ?float
    {
        return ($prev === null || abs($prev) < 0.005) ? null : round(($cur - $prev) / abs($prev) * 100, 1);
    }

    private static function pct(float $part, float $whole): ?float
    {
        return abs($whole) < 0.005 ? null : round($part / $whole * 100, 1);
    }

    /**
     * Search + sort + page over an already-computed row set; never touches the database.
     * A small enough set is returned WHOLE ('local' => true) so the browser can do all of that itself.
     */
    private function paginate(array $rows, array $columns, array $p, string $defaultSort, array $extra = [], bool $all = false): array
    {
        $local = !$all && count($rows) <= self::LOCAL_LIMIT;
        $needle = mb_strtolower(trim((string) ($p['q'] ?? '')));
        if ($needle !== '' && !$local) {
            $rows = array_values(array_filter($rows, fn($r) => str_contains($r['_s'] ?? '', $needle)));
        }

        $sortable = array_column(array_filter($columns, fn($c) => !empty($c['sort'])), 'key');
        $sort = in_array($p['sort'] ?? '', $sortable, true) ? $p['sort'] : $defaultSort;
        $dir = ($p['dir'] ?? 'desc') === 'asc' ? 1 : -1;
        usort($rows, function ($a, $b) use ($sort, $dir) {
            $x = $a[$sort] ?? null;
            $y = $b[$sort] ?? null;
            if ($x === $y) {
                return 0;
            }
            if ($x === null) {
                return 1;                                    // blanks last, whichever way it is sorted
            }
            if ($y === null) {
                return -1;
            }
            return (is_string($x) ? strcasecmp($x, (string) $y) : $x <=> $y) * $dir;
        });

        $total = count($rows);
        $asked = (int) ($p['per'] ?? 50);
        $per = $all ? max(1, $total) : (in_array($asked, [25, 50, 100, 200], true) ? $asked : 50);
        $pages = $local ? 1 : max(1, (int) ceil($total / $per));
        $page = $local ? 1 : min(max(1, (int) ($p['page'] ?? 1)), $pages);
        $strip = function ($r) {
            unset($r['_s']);
            return $r;
        };
        $slice = array_map($strip, $local ? $rows : array_slice($rows, ($page - 1) * $per, $per));

        return ['columns' => $columns, 'rows' => $slice, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per, 'sort' => $sort, 'dir' => $dir === 1 ? 'asc' : 'desc', 'local' => $local] + $extra;
    }

    // ------------------------------------------------------------ overview (one request, first paint)

    public function overview(): array
    {
        return $this->remember('overview', [], function () {
            $prevRange = $this->prevRange();
            $cur = $this->totals(false);
            $prev = $prevRange ? $this->totals(true) : null;

            $customers = $this->inv()->whereNotNull('i.party_code')
                ->selectRaw("MAX(NULLIF(i.party_code,'0')) AS code, MAX(i.party_name) AS name, SUM(i.amount) AS v, COUNT(*) AS n")
                ->groupByRaw(self::PARTY_KEY)->orderByDesc('v')->get();
            $top10 = $customers->take(10);
            $top10Share = self::pct((float) $top10->sum('v'), $cur['sales']);

            $kpi = fn(string $k, ?float $value = null) => [
                'value' => $value ?? $cur[$k],
                'prev' => $prev ? ($prev[$k] ?? null) : null,
                'delta' => $prev ? self::delta((float) ($value ?? $cur[$k]), $prev[$k] ?? null) : null,
            ];

            $series = $this->seriesPanel();
            $coveredSeriesGap = array_values(array_filter($series, fn($s) => $s['covered_invoices'] === 0 && $s['invoices'] > 0));

            return [
                'period' => [
                    'from' => $this->f['from'], 'to' => $this->f['to'],
                    'days' => (int) Carbon::parse($this->f['from'])->diffInDays($this->f['to']) + 1,
                    'prev' => $prevRange,
                ],
                'kpis' => [
                    'sales' => $kpi('sales'),
                    'invoices' => $kpi('invoices'),
                    'customers' => $kpi('customers'),
                    'avg' => $kpi('avg'),
                    'taxable' => $kpi('taxable'),
                    'tax' => $kpi('tax'),
                    'open' => $kpi('open') + ['bills' => $cur['open_bills']],
                    'top10' => ['value' => $top10Share, 'prev' => null, 'delta' => null],
                ],
                'coverage' => [
                    'invoices' => $cur['invoices'], 'covered_invoices' => $cur['covered'],
                    'invoice_pct' => self::pct($cur['covered'], $cur['invoices']),
                    'value' => $cur['sales'], 'covered_value' => $cur['covered_value'],
                    'value_pct' => self::pct($cur['covered_value'], $cur['sales']),
                    'effective_gst' => self::pct($cur['tax'], $cur['taxable']),
                    'missing_series' => array_map(fn($s) => ['series' => $s['series'], 'invoices' => $s['invoices'], 'sales' => $s['sales'], 'top_party' => $s['top_party']], array_slice($coveredSeriesGap, 0, 6)),
                ],
                'trend' => $this->trend(),
                'families' => $this->familiesPanel(),
                'series' => array_slice($series, 0, 12),
                'customers' => $top10->map(fn($c) => ['code' => $c->code, 'name' => $c->name, 'sales' => self::f($c->v), 'invoices' => (int) $c->n, 'share' => self::pct((float) $c->v, $cur['sales'])])->all(),
                'salespersons' => array_slice($this->salespersonsPanel(), 0, 12),
                'plants' => $this->plantsPanel(),
                'cities' => $this->citiesPanel(),
                'gst' => $this->gstSlabs(),
                'sizes' => $this->sizesPanel(),
                'ageing' => $this->ageingPanel(),
                'notices' => $this->notices(),
            ];
        });
    }

    /**
     * Things about the LOADED DATA (not the filters) worth knowing before trusting a comparison:
     * an invoice series that used to bill every day but has nothing in the last week of the export
     * (marketplace channels currently: their invoices may simply not be in Tally's export yet).
     * Without this, a month that is missing a channel reads as a collapse in invoice count.
     */
    private function notices(): array
    {
        return self::shared('notices', fn() => $this->computeNotices());
    }

    private function computeNotices(): array
    {
        [, $hi] = self::bounds();
        $cut = Carbon::parse($hi)->subDays(6)->toDateString();
        $silent = DB::table('tally_invoices')->where('is_branch_transfer', 0)->groupBy('series')
            ->havingRaw('COUNT(*) >= 100 AND MAX(invoice_date) < ?', [$cut])
            ->selectRaw('series, COUNT(*) AS n, SUM(amount) AS v, MAX(invoice_date) AS last_d')->orderByDesc('n')->get();

        $top = $silent->isEmpty() ? collect() : DB::table('tally_invoices')->where('is_branch_transfer', 0)->whereNotNull('party_code')->whereIn('series', $silent->pluck('series'))
            ->groupBy('series', 'party_code')->orderByDesc('v')->selectRaw('series, MAX(party_name) AS name, SUM(amount) AS v')->get()->groupBy('series')->map(fn($g) => $g->first());

        return ['data_to' => $hi, 'silent_series' => $silent->map(fn($s) => [
            'series' => $s->series, 'invoices' => (int) $s->n, 'sales' => self::f($s->v), 'last' => $s->last_d, 'top_party' => $top[$s->series]->name ?? null,
        ])->all()];
    }

    /** Every headline number in ONE single-table query (the per-invoice totals are stamped at import). */
    private function totals(bool $prev): array
    {
        $t = $this->inv($prev)->selectRaw(
            'COUNT(*) AS n, COALESCE(SUM(i.amount),0) AS sales, COUNT(DISTINCT ' . self::PARTY_KEY . ') AS customers,
             COALESCE(SUM(i.taxable_total),0) AS taxable, COALESCE(SUM(i.tax_total),0) AS tax,
             COUNT(i.taxable_total) AS covered, COALESCE(SUM(CASE WHEN i.taxable_total IS NOT NULL THEN i.amount END),0) AS covered_value,
             COALESCE(SUM(i.open_amount),0) AS open_amt, COUNT(i.open_amount) AS open_bills'
        )->first();

        $sales = (float) $t->sales;
        $n = (int) $t->n;
        return [
            'sales' => self::f($sales), 'invoices' => $n, 'customers' => (int) $t->customers,
            'avg' => $n > 0 ? self::f($sales / $n) : 0.0,
            'taxable' => self::f($t->taxable), 'tax' => self::f($t->tax),
            'covered' => (int) $t->covered, 'covered_value' => self::f($t->covered_value),
            'open' => self::f($t->open_amt), 'open_bills' => (int) $t->open_bills,
        ];
    }

    /** Day / week / month series in one payload so the chart's grain switch needs no round trip. */
    private function trend(): array
    {
        $rows = $this->inv()->groupBy('i.invoice_date')->orderBy('i.invoice_date')
            ->selectRaw('i.invoice_date AS d, SUM(i.amount) AS v, COUNT(*) AS n')->get()->keyBy('d');

        $day = ['labels' => [], 'sales' => [], 'invoices' => []];
        $week = $month = [];
        for ($d = Carbon::parse($this->f['from']); $d->lte(Carbon::parse($this->f['to'])); $d->addDay()) {
            $r = $rows[$d->toDateString()] ?? null;
            $v = $r ? self::f($r->v) : 0.0;
            $n = $r ? (int) $r->n : 0;
            $day['labels'][] = $d->format('j M');
            $day['sales'][] = $v;
            $day['invoices'][] = $n;

            $wk = $d->copy()->startOfWeek()->format('j M');
            $week[$wk]['sales'] = ($week[$wk]['sales'] ?? 0) + $v;
            $week[$wk]['invoices'] = ($week[$wk]['invoices'] ?? 0) + $n;
            $mo = $d->format('M Y');
            $month[$mo]['sales'] = ($month[$mo]['sales'] ?? 0) + $v;
            $month[$mo]['invoices'] = ($month[$mo]['invoices'] ?? 0) + $n;
        }
        $pack = fn(array $g) => ['labels' => array_keys($g), 'sales' => array_map(fn($x) => round($x['sales'], 2), array_values($g)), 'invoices' => array_column(array_values($g), 'invoices')];

        $end = Carbon::parse($this->f['to']);
        return [
            'day' => $day, 'week' => $pack($week), 'month' => $pack($month),
            'partial_last_month' => !$end->isSameDay($end->copy()->endOfMonth()) ? $end->format('j M') : null,
        ];
    }

    private function familiesPanel(): array
    {
        $rows = $this->sale()->groupBy('l.product_group')->orderByDesc('taxable')
            ->selectRaw('l.product_group AS g, SUM(l.taxable_amount) AS taxable, COUNT(DISTINCT l.invoice_no) AS bills, COUNT(DISTINCT l.item_name) AS items')->get();
        $total = (float) $rows->sum('taxable');
        $palette = ProductGroup::palette();
        return $rows->map(fn($r) => [
            'name' => $r->g, 'taxable' => self::f($r->taxable), 'bills' => (int) $r->bills, 'items' => (int) $r->items,
            'share' => self::pct((float) $r->taxable, $total), 'color' => $palette[$r->g] ?? '#94a3b8',
        ])->all();
    }

    private function seriesPanel(): array
    {
        $rows = $this->inv()->groupBy('i.series')->orderByDesc('v')
            ->selectRaw('i.series, COUNT(*) AS n, SUM(i.amount) AS v, COUNT(DISTINCT ' . self::PARTY_KEY . ') AS customers,
                         COUNT(i.taxable_total) AS cn, COALESCE(SUM(CASE WHEN i.taxable_total IS NOT NULL THEN i.amount END),0) AS cv')->get();
        $covered = $rows->keyBy('series')->map(fn($r) => (object) ['n' => $r->cn, 'v' => $r->cv]);
        $top = $this->inv()->whereNotNull('i.party_code')->groupByRaw('i.series, ' . self::PARTY_KEY)->orderByDesc('v')
            ->selectRaw('i.series, MAX(i.party_name) AS name, SUM(i.amount) AS v')->get()->groupBy('series')->map(fn($g) => $g->first());

        return $rows->map(function ($r) use ($covered, $top) {
            $t = $top[$r->series] ?? null;
            return [
                'series' => $r->series ?: '—', 'invoices' => (int) $r->n, 'sales' => self::f($r->v), 'customers' => (int) $r->customers,
                'covered_invoices' => (int) ($covered[$r->series]->n ?? 0), 'covered_value' => self::f($covered[$r->series]->v ?? 0),
                'top_party' => $t?->name, 'top_share' => $t ? self::pct((float) $t->v, (float) $r->v) : null,
            ];
        })->all();
    }

    private function salespersonsPanel(): array
    {
        $expr = "COALESCE(NULLIF(i.salesperson,''),'(none)')";
        $rows = $this->inv()->groupByRaw($expr)->orderByDesc('v')
            ->selectRaw("{$expr} AS name, COUNT(*) AS n, COUNT(DISTINCT " . self::PARTY_KEY . ") AS customers, SUM(i.amount) AS v")->get();
        $total = (float) $rows->sum('v');
        return $rows->map(fn($r) => [
            'name' => $r->name, 'invoices' => (int) $r->n, 'customers' => (int) $r->customers,
            'sales' => self::f($r->v), 'avg' => $r->n ? self::f($r->v / $r->n) : 0.0, 'share' => self::pct((float) $r->v, $total),
        ])->all();
    }

    private function plantsPanel(): array
    {
        $expr = "COALESCE(NULLIF(i.destination,''),'(not recorded)')";
        return $this->inv()->groupByRaw($expr)->orderByDesc('v')
            ->selectRaw("{$expr} AS name, COUNT(*) AS n, SUM(i.amount) AS v")->get()
            ->map(fn($r) => ['name' => $r->name, 'invoices' => (int) $r->n, 'sales' => self::f($r->v)])->all();
    }

    private function citiesPanel(): array
    {
        $expr = "COALESCE(NULLIF(l.city,''),'(unknown)')";
        return $this->sale()->groupByRaw($expr)->orderByDesc('v')->limit(12)
            ->selectRaw("{$expr} AS name, COUNT(DISTINCT l.invoice_no) AS n, SUM(l.taxable_amount) AS v")->get()
            ->map(fn($r) => ['name' => $r->name, 'invoices' => (int) $r->n, 'taxable' => self::f($r->v)])->all();
    }

    private function gstSlabs(): array
    {
        $rows = $this->sale()->groupBy('l.gst_rate')->orderByDesc('taxable')
            ->selectRaw('l.gst_rate AS rate, SUM(l.taxable_amount) AS taxable, SUM(l.tax_amount) AS tax, COUNT(DISTINCT l.invoice_no) AS bills, COUNT(*) AS nlines')->get();
        $total = (float) $rows->sum('taxable');
        return $rows->map(fn($r) => [
            'rate' => $r->rate === null ? null : (float) $r->rate, 'taxable' => self::f($r->taxable), 'tax' => self::f($r->tax),
            'bills' => (int) $r->bills, 'lines' => (int) $r->nlines, 'share' => self::pct((float) $r->taxable, $total),
        ])->all();
    }

    private function sizesPanel(): array
    {
        $labels = ['Under ₹10K', '₹10K – 50K', '₹50K – 1L', '₹1L – 5L', '₹5L – 10L', 'Over ₹10L'];
        $rows = $this->inv()->groupBy('b')->orderBy('b')->selectRaw(
            'CASE WHEN i.amount < 10000 THEN 0 WHEN i.amount < 50000 THEN 1 WHEN i.amount < 100000 THEN 2
                  WHEN i.amount < 500000 THEN 3 WHEN i.amount < 1000000 THEN 4 ELSE 5 END AS b, COUNT(*) AS n, SUM(i.amount) AS v'
        )->get()->keyBy('b');
        return array_map(fn($label, $b) => ['label' => $label, 'invoices' => (int) ($rows[$b]->n ?? 0), 'sales' => self::f($rows[$b]->v ?? 0)], $labels, array_keys($labels));
    }

    /** Receivable ageing of ALL debtors as of today (not tied to the period filter). */
    private function ageingPanel(): array
    {
        return self::shared('ageing', fn() => $this->computeAgeing());
    }

    private function computeAgeing(): array
    {
        $r = DB::table('tally_outstanding')->where('ledger_group', 'Sundry Debtors')->selectRaw(
            "COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), bill_date) <= 30 THEN amount END),0) AS a0,
             COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), bill_date) BETWEEN 31 AND 60 THEN amount END),0) AS a1,
             COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), bill_date) BETWEEN 61 AND 90 THEN amount END),0) AS a2,
             COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), bill_date) > 90 THEN amount END),0) AS a3,
             COUNT(*) AS bills"
        )->first();
        return [
            'buckets' => [['label' => '0 – 30 days', 'amount' => self::f($r->a0)], ['label' => '31 – 60 days', 'amount' => self::f($r->a1)], ['label' => '61 – 90 days', 'amount' => self::f($r->a2)], ['label' => 'Over 90 days', 'amount' => self::f($r->a3)]],
            'total' => self::f($r->a0 + $r->a1 + $r->a2 + $r->a3), 'bills' => (int) $r->bills,
        ];
    }

    // ------------------------------------------------------------ bill-wise

    public function bills(array $p, bool $all = false): array
    {
        $asked = (int) ($p['per'] ?? 50);
        $per = $all ? 100000 : (in_array($asked, [25, 50, 100, 200], true) ? $asked : 50);
        $sorts = ['date' => 'i.invoice_date', 'invoice_no' => 'i.invoice_no', 'party' => 'i.party_name', 'amount' => 'i.amount', 'salesperson' => 'i.salesperson'];
        $sort = isset($sorts[$p['sort'] ?? '']) ? $p['sort'] : 'date';
        $dir = ($p['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $q = trim((string) ($p['q'] ?? ''));
        $page = max(1, (int) ($p['page'] ?? 1));

        return $this->remember('bills', compact('per', 'sort', 'dir', 'q', 'page'), function () use ($per, $sorts, $sort, $dir, $q, $page) {
            $base = $this->inv();
            if ($q !== '') {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                $base->where(fn($w) => $w->where('i.invoice_no', 'like', $like)->orWhere('i.party_name', 'like', $like)
                    ->orWhere('i.party_code', 'like', $like)->orWhere('i.po_number', 'like', $like)->orWhere('i.lr_no', 'like', $like));
            }
            $tot = (clone $base)->selectRaw('COUNT(*) AS n, COALESCE(SUM(i.amount),0) AS v')->first();
            $total = (int) $tot->n;
            // A small enough result goes to the browser whole (see LOCAL_LIMIT); it then sorts, searches and pages by itself.
            $local = $q === '' && $per < 100000 && $total <= self::LOCAL_LIMIT;
            if ($local) {
                $per = max(1, $total);
            }
            $pages = max(1, (int) ceil($total / $per));
            $page = min($page, $pages);

            $rows = $base->orderBy($sorts[$sort], $dir)->orderByDesc('i.id')->forPage($page, $per)
                ->get(['i.invoice_no', 'i.series', 'i.invoice_date', 'i.party_code', 'i.party_name', 'i.salesperson', 'i.amount', 'i.destination', 'i.po_number', 'i.lr_no', 'i.is_branch_transfer', 'i.taxable_total', 'i.tax_total', 'i.open_amount']);

            $items = DB::table('tally_invoice_lines')->whereIn('invoice_no', $rows->pluck('invoice_no')->all())->groupBy('invoice_no')->selectRaw('invoice_no, COUNT(*) AS n')->get()->keyBy('invoice_no');

            return [
                'columns' => [
                    ['key' => 'date', 'label' => 'Date', 'type' => 'date', 'sort' => true],
                    ['key' => 'invoice_no', 'label' => 'Invoice', 'type' => 'link', 'open' => 'bill', 'sort' => true],
                    ['key' => 'series', 'label' => 'Series', 'type' => 'text'],
                    ['key' => 'party', 'label' => 'Customer', 'type' => 'party', 'sort' => true],
                    ['key' => 'salesperson', 'label' => 'Salesperson', 'type' => 'text', 'sort' => true],
                    ['key' => 'amount', 'label' => 'Amount incl. GST (₹)', 'type' => 'money', 'sort' => true],
                    ['key' => 'taxable', 'label' => 'Taxable (₹)', 'type' => 'money'],
                    ['key' => 'tax', 'label' => 'GST (₹)', 'type' => 'money'],
                    ['key' => 'items', 'label' => 'Items', 'type' => 'num'],
                    ['key' => 'destination', 'label' => 'Dispatched from', 'type' => 'text'],
                    ['key' => 'open', 'label' => 'Still open (₹)', 'type' => 'money'],
                ],
                'rows' => $rows->map(fn($r) => [
                    'date' => $r->invoice_date, 'invoice_no' => $r->invoice_no, 'series' => $r->series,
                    'party' => $r->party_name ?: ($r->is_branch_transfer ? 'Branch transfer' : '—'), 'party_code' => $r->party_code,
                    'salesperson' => $r->salesperson, 'amount' => self::f($r->amount),
                    'taxable' => $r->taxable_total === null ? null : self::f($r->taxable_total),
                    'tax' => $r->tax_total === null ? null : self::f($r->tax_total),
                    'items' => (int) ($items[$r->invoice_no]->n ?? 0), 'destination' => $r->destination,
                    'open' => $r->open_amount === null ? null : self::f($r->open_amount),
                    'po' => $r->po_number, 'lr' => $r->lr_no,          // not shown; kept so the in-browser search can find them
                ])->all(),
                'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per, 'sort' => $sort, 'dir' => $dir, 'local' => $local,
                'summary' => ['amount' => self::f($tot->v), 'invoices' => $total],
            ];
        });
    }

    /** Everything about one invoice: header, item lines with GST, how it reconciles, what is still open. */
    public function bill(string $no): ?array
    {
        $inv = DB::table('tally_invoices')->where('invoice_no', $no)->first();
        if (!$inv) {
            return null;
        }
        $sale = DB::table('tally_sale_lines')->where('invoice_no', $no)->orderBy('id')->get();
        $qty = DB::table('tally_invoice_lines')->where('invoice_no', $no)->orderBy('id')->get();
        $open = DB::table('tally_outstanding')->where('ledger_group', 'Sundry Debtors')->where('bill_ref', $no)->get();

        $taxable = (float) $sale->sum('taxable_amount');
        $tax = (float) $sale->sum('tax_amount');
        $diff = (float) $inv->amount - ($taxable + $tax);
        $lines = $sale->isNotEmpty()
            ? $sale->map(fn($l) => [
                'item' => $l->item_name, 'family' => $l->product_group, 'hsn' => $l->hsn, 'gst' => $l->gst_rate === null ? null : (float) $l->gst_rate,
                'qty' => $l->quantity === null ? null : (float) $l->quantity, 'unit' => $l->unit, 'rate' => $l->rate === null ? null : (float) $l->rate, 'rate_unit' => $l->rate_unit,
                'taxable' => self::f($l->taxable_amount), 'tax' => $l->tax_amount === null ? null : self::f($l->tax_amount),
            ])->all()
            : $qty->map(fn($l) => ['item' => $l->item_name, 'family' => $l->product_group, 'hsn' => null, 'gst' => null, 'qty' => $l->quantity === null ? null : (float) $l->quantity, 'unit' => $l->unit, 'rate' => null, 'rate_unit' => null, 'taxable' => null, 'tax' => null])->all();

        $first = $sale->first();
        return [
            'invoice' => [
                'invoice_no' => $inv->invoice_no, 'series' => $inv->series, 'date' => $inv->invoice_date, 'amount' => self::f($inv->amount),
                'party' => $inv->party_name, 'party_code' => $inv->party_code, 'salesperson' => $inv->salesperson, 'po' => $inv->po_number,
                'lr_no' => $inv->lr_no, 'transporter' => $inv->transporter, 'transporter_phone' => $inv->transporter_phone, 'plant' => $inv->destination,
                'city' => $first?->city, 'due_date' => $first?->due_date, 'credit_terms' => $first?->credit_terms,
                'branch_transfer' => (bool) $inv->is_branch_transfer,
            ],
            'lines' => $lines,
            'has_value_detail' => $sale->isNotEmpty(),
            'totals' => ['taxable' => self::f($taxable), 'tax' => self::f($tax), 'lines_total' => self::f($taxable + $tax), 'diff' => self::f($diff)],
            'reconciles' => $sale->isEmpty() ? null : abs($diff) <= 1.0,
            'open' => $open->map(fn($o) => ['amount' => self::f($o->amount), 'date' => $o->bill_date, 'age' => (int) Carbon::parse($o->bill_date)->startOfDay()->diffInDays(now()->startOfDay()), 'credit_days' => $o->credit_days])->all(),
        ];
    }

    // ------------------------------------------------------------ item-wise

    public function items(array $p, bool $all = false): array
    {
        $basis = ($p['basis'] ?? 'value') === 'qty' ? 'qty' : 'value';
        $group = ($p['group'] ?? 'item') === 'family' ? 'family' : 'item';
        $family = (string) ($p['family'] ?? '');
        $data = $this->remember('items', compact('basis', 'group', 'family'), fn() => $this->itemRows($basis, $group, $family));
        return $this->paginate($data['rows'], $data['columns'], $p, $data['default_sort'], ['summary' => $data['summary'], 'basis' => $basis, 'group' => $group, 'family' => $family], $all);
    }

    private function itemRows(string $basis, string $group, string $family): array
    {
        $money = fn($k, $l) => ['key' => $k, 'label' => $l, 'type' => 'money', 'sort' => true];
        $num = fn($k, $l) => ['key' => $k, 'label' => $l, 'type' => 'num', 'sort' => true];

        if ($basis === 'value' && $group === 'item') {
            $q = $this->sale()->when($family !== '', fn($w) => $w->where('l.product_group', $family))->groupBy('l.item_name', 'l.unit')
                ->selectRaw('l.item_name, l.unit, MAX(l.product_group) AS grp, MAX(l.hsn) AS hsn, MAX(l.gst_rate) AS gst, SUM(l.quantity) AS qty, SUM(l.taxable_amount) AS taxable,
                             SUM(l.tax_amount) AS tax, COUNT(DISTINCT l.invoice_no) AS bills, COUNT(DISTINCT l.party_code) AS customers, MIN(l.rate) AS rmin, MAX(l.rate) AS rmax, MAX(l.invoice_date) AS last_date')->get();
            $total = (float) $q->sum('taxable');
            $rows = $q->map(fn($r) => [
                'item' => $r->item_name, 'unit' => $r->unit, 'family' => $r->grp, 'hsn' => $r->hsn, 'gst' => $r->gst === null ? null : (float) $r->gst,
                'qty' => self::f($r->qty, 3), 'taxable' => self::f($r->taxable), 'tax' => self::f($r->tax), 'bills' => (int) $r->bills, 'customers' => (int) $r->customers,
                'avg_rate' => $r->qty > 0 ? self::f($r->taxable / $r->qty) : null, 'min_rate' => $r->rmin === null ? null : self::f($r->rmin), 'max_rate' => $r->rmax === null ? null : self::f($r->rmax),
                'share' => self::pct((float) $r->taxable, $total), 'last' => $r->last_date,
                '_s' => mb_strtolower($r->item_name . ' ' . $r->hsn . ' ' . $r->grp),
            ])->all();
            return [
                'rows' => $rows, 'default_sort' => 'taxable',
                'columns' => [
                    ['key' => 'item', 'label' => 'Item', 'type' => 'link', 'open' => 'item', 'sort' => true], ['key' => 'family', 'label' => 'Family', 'type' => 'text', 'sort' => true],
                    ['key' => 'hsn', 'label' => 'HSN', 'type' => 'text'], ['key' => 'gst', 'label' => 'GST', 'type' => 'pct0', 'sort' => true], ['key' => 'unit', 'label' => 'Unit', 'type' => 'text'],
                    $num('qty', 'Quantity'), $num('bills', 'Bills'), $num('customers', 'Customers'), $money('taxable', 'Taxable (₹)'), $money('tax', 'GST (₹)'),
                    ['key' => 'avg_rate', 'label' => 'Avg rate (₹)', 'type' => 'money2', 'sort' => true], ['key' => 'min_rate', 'label' => 'Lowest (₹)', 'type' => 'money2'], ['key' => 'max_rate', 'label' => 'Highest (₹)', 'type' => 'money2'],
                    ['key' => 'share', 'label' => 'Share', 'type' => 'share', 'sort' => true],
                ],
                'summary' => ['label' => 'Taxable value', 'value' => self::f($total), 'rows' => count($rows)],
            ];
        }

        if ($basis === 'value') {                       // by family
            $q = $this->sale()->groupBy('l.product_group')->selectRaw('l.product_group AS g, SUM(l.taxable_amount) AS taxable, SUM(l.tax_amount) AS tax, COUNT(DISTINCT l.invoice_no) AS bills,
                             COUNT(DISTINCT l.party_code) AS customers, COUNT(DISTINCT l.item_name) AS items')->get();
            $total = (float) $q->sum('taxable');
            $palette = ProductGroup::palette();
            $rows = $q->map(fn($r) => [
                'family' => $r->g, 'items' => (int) $r->items, 'bills' => (int) $r->bills, 'customers' => (int) $r->customers, 'taxable' => self::f($r->taxable), 'tax' => self::f($r->tax),
                'share' => self::pct((float) $r->taxable, $total), 'color' => $palette[$r->g] ?? '#94a3b8', '_s' => mb_strtolower($r->g),
            ])->all();
            return [
                'rows' => $rows, 'default_sort' => 'taxable',
                'columns' => [['key' => 'family', 'label' => 'Family', 'type' => 'link', 'open' => 'family', 'sort' => true], $num('items', 'Items'), $num('bills', 'Bills'), $num('customers', 'Customers'), $money('taxable', 'Taxable (₹)'), $money('tax', 'GST (₹)'), ['key' => 'share', 'label' => 'Share', 'type' => 'share', 'sort' => true]],
                'summary' => ['label' => 'Taxable value', 'value' => self::f($total), 'rows' => count($rows)],
            ];
        }

        if ($group === 'item') {                        // quantity only, every invoice
            $q = $this->qty()->when($family !== '', fn($w) => $w->where('q.product_group', $family))->groupBy('q.item_name', 'q.unit')
                ->selectRaw('q.item_name, q.unit, MAX(q.product_group) AS grp, SUM(q.quantity) AS qty, COUNT(DISTINCT q.invoice_no) AS bills, COUNT(DISTINCT q.party_code) AS customers, MAX(q.invoice_date) AS last_date')->get();
            $rows = $q->map(fn($r) => [
                'item' => $r->item_name, 'unit' => $r->unit, 'family' => $r->grp, 'qty' => self::f($r->qty, 3), 'bills' => (int) $r->bills, 'customers' => (int) $r->customers, 'last' => $r->last_date,
                '_s' => mb_strtolower($r->item_name . ' ' . $r->grp),
            ])->all();
            return [
                'rows' => $rows, 'default_sort' => 'bills',
                'columns' => [['key' => 'item', 'label' => 'Item', 'type' => 'link', 'open' => 'item', 'sort' => true], ['key' => 'family', 'label' => 'Family', 'type' => 'text', 'sort' => true], ['key' => 'unit', 'label' => 'Unit', 'type' => 'text'], $num('qty', 'Quantity'), $num('bills', 'Bills'), $num('customers', 'Customers'), ['key' => 'last', 'label' => 'Last sold', 'type' => 'date', 'sort' => true]],
                'summary' => ['label' => 'Distinct items', 'value' => count($rows), 'rows' => count($rows)],
            ];
        }

        $q = $this->qty()->groupBy('q.product_group')->selectRaw('q.product_group AS g, COUNT(DISTINCT q.item_name) AS items, COUNT(DISTINCT q.invoice_no) AS bills, COUNT(DISTINCT q.party_code) AS customers')->get();
        $rows = $q->map(fn($r) => ['family' => $r->g ?: ProductGroup::OTHER, 'items' => (int) $r->items, 'bills' => (int) $r->bills, 'customers' => (int) $r->customers, '_s' => mb_strtolower((string) $r->g)])->all();
        return [
            'rows' => $rows, 'default_sort' => 'bills',
            'columns' => [['key' => 'family', 'label' => 'Family', 'type' => 'link', 'open' => 'family', 'sort' => true], $num('items', 'Items'), $num('bills', 'Bills'), $num('customers', 'Customers')],
            'summary' => ['label' => 'Families', 'value' => count($rows), 'rows' => count($rows)],
        ];
    }

    /** Price history, customers and recent bills for one item. $scope: 'period' (current filters) or 'all' (all loaded data). */
    public function item(string $name, ?string $unit, string $scope): array
    {
        $a = $scope === 'all' ? $this->withoutPeriod() : $this;
        return $a->remember('item', ['n' => $name, 'u' => $unit, 's' => $scope], function () use ($a, $name, $unit) {
            $lines = $a->sale()->where('l.item_name', $name)->when($unit !== null && $unit !== '', fn($w) => $w->where('l.unit', $unit), fn($w) => $w->where(fn($x) => $x->whereNull('l.unit')->orWhere('l.unit', '')))
                ->orderBy('l.invoice_date')->orderBy('l.id')
                ->get(['l.invoice_no', 'l.invoice_date', 'l.party_code', 'l.party_name', 'l.hsn', 'l.gst_rate', 'l.quantity', 'l.unit', 'l.rate', 'l.rate_unit', 'l.taxable_amount', 'l.tax_amount', 'l.product_group']);

            if ($lines->isEmpty()) {
                $q = $a->qty()->where('q.item_name', $name)->when($unit !== null && $unit !== '', fn($w) => $w->where('q.unit', $unit));
                $t = (clone $q)->selectRaw('COUNT(DISTINCT q.invoice_no) AS bills, COUNT(DISTINCT q.party_code) AS customers, SUM(q.quantity) AS qty')->first();
                $recent = $q->orderByDesc('q.invoice_date')->limit(25)->get(['q.invoice_no', 'q.invoice_date', 'q.party_name', 'q.quantity', 'q.unit']);
                return [
                    'item' => $name, 'unit' => $unit, 'family' => ProductGroup::detect($name), 'value_detail' => false,
                    'kpis' => ['bills' => (int) $t->bills, 'customers' => (int) $t->customers, 'qty' => self::f($t->qty, 3)],
                    'recent' => $recent->map(fn($r) => ['date' => $r->invoice_date, 'invoice_no' => $r->invoice_no, 'party' => $r->party_name, 'qty' => $r->quantity === null ? null : (float) $r->quantity, 'unit' => $r->unit])->all(),
                    'range' => [$a->f['from'], $a->f['to']],
                ];
            }

            $qty = (float) $lines->sum('quantity');
            $taxable = (float) $lines->sum('taxable_amount');
            $rates = $lines->pluck('rate')->filter(fn($r) => $r !== null)->map(fn($r) => (float) $r);
            $last = $lines->last();

            $byDay = $lines->groupBy('invoice_date')->map(function ($g) {
                $q = (float) $g->sum('quantity');
                $r = $g->pluck('rate')->filter(fn($x) => $x !== null)->map(fn($x) => (float) $x);
                return ['avg' => $q > 0 ? round((float) $g->sum('taxable_amount') / $q, 2) : ($r->isNotEmpty() ? round($r->avg(), 2) : null), 'min' => $r->isNotEmpty() ? $r->min() : null, 'max' => $r->isNotEmpty() ? $r->max() : null];
            });

            $byParty = $lines->groupBy(fn($l) => $l->party_code ?: $l->party_name)->map(function ($g) {
                $q = (float) $g->sum('quantity');
                return ['code' => $g->first()->party_code, 'name' => $g->first()->party_name, 'qty' => round($q, 3), 'taxable' => self::f($g->sum('taxable_amount')),
                    'avg_rate' => $q > 0 ? round((float) $g->sum('taxable_amount') / $q, 2) : null, 'last' => $g->max('invoice_date'), 'bills' => $g->pluck('invoice_no')->unique()->count()];
            })->sortByDesc('taxable')->values();

            return [
                'item' => $name, 'unit' => $unit, 'family' => $lines->first()->product_group, 'hsn' => $lines->first()->hsn, 'gst' => $lines->first()->gst_rate === null ? null : (float) $lines->first()->gst_rate,
                'value_detail' => true,
                'kpis' => [
                    'taxable' => self::f($taxable), 'tax' => self::f($lines->sum('tax_amount')), 'qty' => round($qty, 3),
                    'bills' => $lines->pluck('invoice_no')->unique()->count(), 'customers' => $byParty->count(),
                    'avg_rate' => $qty > 0 ? self::f($taxable / $qty) : null, 'min_rate' => $rates->isNotEmpty() ? self::f($rates->min()) : null, 'max_rate' => $rates->isNotEmpty() ? self::f($rates->max()) : null,
                    'latest_rate' => $last->rate === null ? null : self::f($last->rate), 'latest_date' => $last->invoice_date,
                ],
                'history' => ['labels' => $byDay->keys()->map(fn($d) => Carbon::parse($d)->format('j M'))->all(), 'avg' => $byDay->pluck('avg')->all(), 'min' => $byDay->pluck('min')->all(), 'max' => $byDay->pluck('max')->all()],
                'customers' => $byParty->take(15)->all(),
                'recent' => $lines->reverse()->take(25)->map(fn($l) => ['date' => $l->invoice_date, 'invoice_no' => $l->invoice_no, 'party' => $l->party_name, 'qty' => $l->quantity === null ? null : (float) $l->quantity, 'unit' => $l->unit, 'rate' => $l->rate === null ? null : (float) $l->rate, 'taxable' => self::f($l->taxable_amount)])->values()->all(),
                'range' => [$a->f['from'], $a->f['to']],
            ];
        });
    }

    // ------------------------------------------------------------ customer-wise

    public function customers(array $p, bool $all = false): array
    {
        $data = $this->remember('customers', [], fn() => $this->customerRows());
        return $this->paginate($data['rows'], $data['columns'], $p, 'sales', ['summary' => $data['summary']], $all);
    }

    private function customerRows(): array
    {
        $cur = $this->inv()->whereNotNull('i.party_code')->groupByRaw(self::PARTY_KEY)->selectRaw(
            self::PARTY_KEY . ' AS gk, MAX(NULLIF(i.party_code, \'0\')) AS code, MAX(i.party_name) AS name, MAX(i.normalized_name) AS norm, GROUP_CONCAT(DISTINCT i.salesperson ORDER BY i.salesperson SEPARATOR ", ") AS sps,
             COUNT(*) AS n, SUM(i.amount) AS v, MAX(i.invoice_date) AS last_date'
        )->get();
        $prevRange = $this->prevRange();
        $prev = $prevRange ? $this->inv(true)->whereNotNull('i.party_code')->groupByRaw(self::PARTY_KEY)->selectRaw(self::PARTY_KEY . ' AS gk, SUM(i.amount) AS v')->pluck('v', 'gk') : collect();

        $owed = DB::table('tally_outstanding')->where('ledger_group', 'Sundry Debtors')->groupBy('party_code')
            ->selectRaw('party_code, SUM(amount) AS due, SUM(CASE WHEN DATEDIFF(CURDATE(), bill_date) > 90 THEN amount ELSE 0 END) AS over90')->get()->keyBy('party_code');
        $norms = $cur->pluck('norm')->filter()->unique()->all();
        $inHrx = Customer::whereIn('normalized_name', $norms)->pluck('normalized_name')->flip();
        $inSetu = SetuCustomer::whereIn('normalized_name', $norms)->pluck('normalized_name')->flip();

        $total = (float) $cur->sum('v');
        $rows = $cur->map(fn($r) => [
            'code' => $r->code, 'name' => $r->name, 'salespersons' => $r->sps, 'invoices' => (int) $r->n, 'sales' => self::f($r->v),
            'avg' => $r->n ? self::f($r->v / $r->n) : 0.0, 'share' => self::pct((float) $r->v, $total), 'prev' => $prevRange ? self::f($prev[$r->gk] ?? 0) : null,
            'delta' => $prevRange ? self::delta((float) $r->v, isset($prev[$r->gk]) ? (float) $prev[$r->gk] : 0.0) : null, 'last' => $r->last_date,
            'due' => $r->code !== null && isset($owed[$r->code]) ? self::f($owed[$r->code]->due) : null, 'over90' => $r->code !== null && isset($owed[$r->code]) && $owed[$r->code]->over90 != 0 ? self::f($owed[$r->code]->over90) : null,
            'in_hrx' => isset($inHrx[$r->norm]) ? 'Yes' : '—', 'in_setu' => isset($inSetu[$r->norm]) ? 'Yes' : '—',
            '_s' => mb_strtolower($r->name . ' ' . $r->code . ' ' . $r->sps),
        ])->all();

        return [
            'rows' => $rows,
            'columns' => array_values(array_filter([
                ['key' => 'name', 'label' => 'Customer', 'type' => 'link', 'open' => 'customer', 'sort' => true],
                ['key' => 'salespersons', 'label' => 'Salesperson', 'type' => 'text'],
                ['key' => 'invoices', 'label' => 'Bills', 'type' => 'num', 'sort' => true],
                ['key' => 'sales', 'label' => 'Sales incl. GST (₹)', 'type' => 'money', 'sort' => true],
                ['key' => 'share', 'label' => 'Share', 'type' => 'share', 'sort' => true],
                ['key' => 'avg', 'label' => 'Avg bill (₹)', 'type' => 'money', 'sort' => true],
                $prevRange ? ['key' => 'delta', 'label' => 'vs previous', 'type' => 'delta', 'sort' => true] : null,
                ['key' => 'last', 'label' => 'Last bill', 'type' => 'date', 'sort' => true],
                ['key' => 'due', 'label' => 'Owes now (₹)', 'type' => 'money', 'sort' => true],
                ['key' => 'over90', 'label' => 'Over 90 d (₹)', 'type' => 'money', 'sort' => true],
                ['key' => 'in_hrx', 'label' => 'In HRX', 'type' => 'text'],
                ['key' => 'in_setu', 'label' => 'In Setu', 'type' => 'text'],
            ])),
            'summary' => ['label' => 'Customers', 'value' => count($rows), 'rows' => count($rows)],
        ];
    }

    public function customer(string $code): ?array
    {
        return $this->remember('customer', ['c' => $code], function () use ($code) {
            $inv = DB::table('tally_invoices')->where('party_code', $code);
            $head = (clone $inv)->selectRaw('MAX(party_name) AS name, MAX(normalized_name) AS norm, MIN(invoice_date) AS first_d, MAX(invoice_date) AS last_d, COUNT(*) AS n, SUM(amount) AS v')->first();
            if (!$head || !$head->n) {
                return null;
            }
            $period = (clone $this->inv())->where('i.party_code', $code);
            $p = (clone $period)->selectRaw('COUNT(*) AS n, COALESCE(SUM(i.amount),0) AS v')->first();
            $periodSales = (float) $this->inv()->sum('i.amount');

            $monthly = (clone $inv)->groupByRaw("DATE_FORMAT(invoice_date, '%Y-%m')")->orderByRaw("DATE_FORMAT(invoice_date, '%Y-%m')")
                ->selectRaw("DATE_FORMAT(invoice_date, '%Y-%m') AS m, SUM(amount) AS v, COUNT(*) AS n")->get();

            $items = $this->sale()->where('l.party_code', $code)->groupBy('l.item_name', 'l.unit')->orderByDesc('taxable')->limit(12)
                ->selectRaw('l.item_name, l.unit, MAX(l.product_group) AS grp, SUM(l.quantity) AS qty, SUM(l.taxable_amount) AS taxable, COUNT(DISTINCT l.invoice_no) AS bills, MAX(l.rate) AS rmax, MIN(l.rate) AS rmin')->get();
            $families = $this->sale()->where('l.party_code', $code)->groupBy('l.product_group')->orderByDesc('taxable')->selectRaw('l.product_group AS g, SUM(l.taxable_amount) AS taxable')->get();
            $recent = (clone $period)->orderByDesc('i.invoice_date')->orderByDesc('i.id')->limit(12)->get(['i.invoice_no', 'i.invoice_date', 'i.amount', 'i.salesperson']);

            $bills = DB::table('tally_outstanding')->where('ledger_group', 'Sundry Debtors')->where('party_code', $code)->orderBy('bill_date')->get();
            $hrx = $head->norm ? Customer::where('normalized_name', $head->norm)->first(['id', 'canonical_name', 'setu_match_status']) : null;

            return [
                'code' => $code, 'name' => $head->name,
                'city' => DB::table('tally_sale_lines')->where('party_code', $code)->whereNotNull('city')->value('city'),
                'salespersons' => (clone $inv)->whereNotNull('salesperson')->distinct()->orderBy('salesperson')->pluck('salesperson')->all(),
                'first' => $head->first_d, 'last' => $head->last_d,
                'kpis' => ['period_sales' => self::f($p->v), 'period_bills' => (int) $p->n, 'avg' => $p->n ? self::f($p->v / $p->n) : 0.0, 'share' => self::pct((float) $p->v, $periodSales), 'all_sales' => self::f($head->v), 'all_bills' => (int) $head->n],
                'monthly' => ['labels' => $monthly->map(fn($m) => Carbon::createFromFormat('!Y-m', $m->m)->format('M Y'))->all(), 'sales' => $monthly->map(fn($m) => self::f($m->v))->all(), 'bills' => $monthly->pluck('n')->map(fn($n) => (int) $n)->all()],
                'items' => $items->map(fn($r) => ['item' => $r->item_name, 'unit' => $r->unit, 'family' => $r->grp, 'qty' => self::f($r->qty, 3), 'taxable' => self::f($r->taxable), 'bills' => (int) $r->bills, 'rmin' => $r->rmin === null ? null : self::f($r->rmin), 'rmax' => $r->rmax === null ? null : self::f($r->rmax)])->all(),
                'families' => $families->map(fn($r) => ['name' => $r->g, 'taxable' => self::f($r->taxable), 'color' => ProductGroup::palette()[$r->g] ?? '#94a3b8'])->all(),
                'recent' => $recent->map(fn($r) => ['invoice_no' => $r->invoice_no, 'date' => $r->invoice_date, 'amount' => self::f($r->amount), 'salesperson' => $r->salesperson])->all(),
                'outstanding' => [
                    'total' => self::f($bills->sum('amount')), 'over90' => self::f($bills->filter(fn($b) => Carbon::parse($b->bill_date)->diffInDays(now()) > 90)->sum('amount')),
                    'bills' => $bills->take(30)->map(fn($b) => ['ref' => $b->bill_ref, 'date' => $b->bill_date, 'age' => (int) Carbon::parse($b->bill_date)->startOfDay()->diffInDays(now()->startOfDay()), 'amount' => self::f($b->amount)])->all(),
                    'count' => $bills->count(),
                ],
                'hrx' => $hrx ? ['name' => $hrx->canonical_name, 'status' => $hrx->setu_match_status] : null,
                'in_setu' => $head->norm ? SetuCustomer::where('normalized_name', $head->norm)->exists() : false,
            ];
        });
    }

    // ------------------------------------------------------------ salesperson-wise

    public function salespersons(array $p, bool $all = false): array
    {
        $data = $this->remember('salespersons', [], function () {
            $rows = $this->salespersonsPanel();
            $prevRange = $this->prevRange();
            $prev = $prevRange ? $this->inv(true)->groupByRaw("COALESCE(NULLIF(i.salesperson,''),'(none)')")->selectRaw("COALESCE(NULLIF(i.salesperson,''),'(none)') AS name, SUM(i.amount) AS v")->pluck('v', 'name') : collect();

            $lineExpr = "COALESCE(NULLIF(l.salesperson,''),'(none)')";
            $fam = $this->sale()->groupByRaw("{$lineExpr}, l.product_group")->selectRaw("{$lineExpr} AS sp, l.product_group AS g, SUM(l.taxable_amount) AS v")->get()->groupBy('sp')
                ->map(fn($g) => $g->sortByDesc('v')->first());
            $expr = "COALESCE(NULLIF(i.salesperson,''),'(none)')";
            $cov = $this->inv()->groupByRaw($expr)->selectRaw("{$expr} AS sp, COUNT(i.taxable_total) AS n")->pluck('n', 'sp');

            return [
                'rows' => array_map(fn($r) => $r + [
                    'delta' => $prevRange ? self::delta($r['sales'], isset($prev[$r['name']]) ? (float) $prev[$r['name']] : 0.0) : null,
                    'top_family' => isset($fam[$r['name']]) ? $fam[$r['name']]->g : null, 'detail_pct' => self::pct((float) ($cov[$r['name']] ?? 0), (float) $r['invoices']),
                    '_s' => mb_strtolower($r['name']),
                ], $rows),
                'columns' => array_values(array_filter([
                    ['key' => 'name', 'label' => 'Salesperson (as named in Tally)', 'type' => 'link', 'open' => 'salesperson', 'sort' => true],
                    ['key' => 'invoices', 'label' => 'Bills', 'type' => 'num', 'sort' => true], ['key' => 'customers', 'label' => 'Customers', 'type' => 'num', 'sort' => true],
                    ['key' => 'sales', 'label' => 'Sales incl. GST (₹)', 'type' => 'money', 'sort' => true], ['key' => 'share', 'label' => 'Share', 'type' => 'share', 'sort' => true],
                    ['key' => 'avg', 'label' => 'Avg bill (₹)', 'type' => 'money', 'sort' => true],
                    $prevRange ? ['key' => 'delta', 'label' => 'vs previous', 'type' => 'delta', 'sort' => true] : null,
                    ['key' => 'top_family', 'label' => 'Biggest family', 'type' => 'text'], ['key' => 'detail_pct', 'label' => 'Item detail', 'type' => 'share', 'sort' => true],
                ])),
                'summary' => ['label' => 'Salespeople', 'value' => count($rows), 'rows' => count($rows)],
            ];
        });
        return $this->paginate($data['rows'], $data['columns'], $p, 'sales', ['summary' => $data['summary']], $all);
    }

    // ------------------------------------------------------------ GST & HSN

    public function gst(array $p = [], bool $all = false): array
    {
        $data = $this->remember('gst', [], function () {
            $slabs = $this->gstSlabs();
            $hsnRaw = $this->sale()->groupBy('l.hsn', 'l.gst_rate', 'l.product_group')->selectRaw('l.hsn, l.gst_rate AS rate, l.product_group AS g, SUM(l.taxable_amount) AS taxable, SUM(l.tax_amount) AS tax, COUNT(*) AS nlines, COUNT(DISTINCT l.invoice_no) AS bills')->get();
            $hsn = $hsnRaw->groupBy(fn($r) => ($r->hsn ?? '') . '|' . ($r->rate ?? ''))->map(function ($g) {
                $top = $g->sortByDesc('taxable')->first();
                return [
                    'hsn' => $top->hsn ?: '—', 'gst' => $top->rate === null ? null : (float) $top->rate, 'family' => $top->g, 'taxable' => self::f($g->sum('taxable')), 'tax' => self::f($g->sum('tax')),
                    'lines' => (int) $g->sum('nlines'), 'bills' => (int) $g->sum('bills'), '_s' => mb_strtolower(($top->hsn ?? '') . ' ' . $top->g),
                ];
            })->values()->all();
            $total = array_sum(array_column($hsn, 'taxable'));
            $hsn = array_map(fn($r) => $r + ['share' => self::pct($r['taxable'], (float) $total)], $hsn);

            $monthly = $this->sale()->groupByRaw("DATE_FORMAT(l.invoice_date, '%Y-%m')")->orderByRaw("DATE_FORMAT(l.invoice_date, '%Y-%m')")
                ->selectRaw("DATE_FORMAT(l.invoice_date, '%Y-%m') AS m, SUM(l.taxable_amount) AS taxable, SUM(l.tax_amount) AS tax")->get();
            $mismatch = $this->sale()->whereNotNull('l.gst_rate')->whereNotNull('l.tax_amount')->whereRaw('ABS(l.taxable_amount * l.gst_rate / 100 - l.tax_amount) > 1.5')->count();
            $checked = $this->sale()->whereNotNull('l.gst_rate')->whereNotNull('l.tax_amount')->count();

            return [
                'slabs' => $slabs,
                'monthly' => ['labels' => $monthly->map(fn($m) => Carbon::createFromFormat('!Y-m', $m->m)->format('M Y'))->all(), 'taxable' => $monthly->map(fn($m) => self::f($m->taxable))->all(), 'tax' => $monthly->map(fn($m) => self::f($m->tax))->all()],
                'check' => ['lines' => $checked, 'mismatch' => $mismatch],
                'hsn_rows' => $hsn,
                'hsn_columns' => [
                    ['key' => 'hsn', 'label' => 'HSN / SAC', 'type' => 'text', 'sort' => true], ['key' => 'gst', 'label' => 'GST', 'type' => 'pct0', 'sort' => true], ['key' => 'family', 'label' => 'Main family', 'type' => 'text'],
                    ['key' => 'bills', 'label' => 'Bills', 'type' => 'num', 'sort' => true], ['key' => 'lines', 'label' => 'Lines', 'type' => 'num', 'sort' => true],
                    ['key' => 'taxable', 'label' => 'Taxable (₹)', 'type' => 'money', 'sort' => true], ['key' => 'tax', 'label' => 'GST (₹)', 'type' => 'money', 'sort' => true], ['key' => 'share', 'label' => 'Share', 'type' => 'share', 'sort' => true],
                ],
            ];
        });
        $table = $this->paginate($data['hsn_rows'], $data['hsn_columns'], $p, 'taxable', [], $all);
        return ['slabs' => $data['slabs'], 'monthly' => $data['monthly'], 'check' => $data['check']] + $table;
    }

    // ------------------------------------------------------------ price analysis

    public function price(array $p, bool $all = false): array
    {
        $vary = ($p['vary'] ?? '1') !== '0';
        $data = $this->remember('price', compact('vary'), function () use ($vary) {
            $lines = $this->sale()->whereNotNull('l.rate')->where('l.quantity', '>', 0)->orderBy('l.invoice_date')->orderBy('l.id')
                ->get(['l.item_name', 'l.rate_unit', 'l.rate', 'l.invoice_date', 'l.party_code', 'l.quantity', 'l.taxable_amount', 'l.product_group']);

            $rows = [];
            foreach ($lines->groupBy(fn($l) => $l->item_name . '|' . $l->rate_unit) as $g) {
                $rates = $g->pluck('rate')->map(fn($r) => (float) $r);
                if ($vary && ($g->count() < 2 || abs($rates->max() - $rates->min()) < 0.005)) {
                    continue;
                }
                $first = $g->first();
                $last = $g->last();
                $qty = (float) $g->sum('quantity');
                $min = $rates->min();
                $rows[] = [
                    'item' => $first->item_name, 'unit' => $first->rate_unit, 'family' => $first->product_group, 'lines' => $g->count(), 'customers' => $g->pluck('party_code')->unique()->count(),
                    'min' => self::f($min), 'avg' => $qty > 0 ? self::f((float) $g->sum('taxable_amount') / $qty) : self::f($rates->avg()), 'max' => self::f($rates->max()),
                    'spread' => $min > 0 ? round(($rates->max() - $min) / $min * 100, 1) : null,
                    'first_rate' => self::f($first->rate), 'latest' => self::f($last->rate), 'change' => self::delta((float) $last->rate, (float) $first->rate),
                    'last' => $last->invoice_date, 'value' => self::f($g->sum('taxable_amount')),
                    '_s' => mb_strtolower($first->item_name . ' ' . $first->product_group),
                ];
            }
            return [
                'rows' => $rows,
                'columns' => [
                    ['key' => 'item', 'label' => 'Item', 'type' => 'link', 'open' => 'item', 'sort' => true], ['key' => 'family', 'label' => 'Family', 'type' => 'text', 'sort' => true], ['key' => 'unit', 'label' => 'Per', 'type' => 'text'],
                    ['key' => 'lines', 'label' => 'Sales', 'type' => 'num', 'sort' => true], ['key' => 'customers', 'label' => 'Customers', 'type' => 'num', 'sort' => true],
                    ['key' => 'min', 'label' => 'Lowest (₹)', 'type' => 'money2', 'sort' => true], ['key' => 'avg', 'label' => 'Avg (₹)', 'type' => 'money2', 'sort' => true], ['key' => 'max', 'label' => 'Highest (₹)', 'type' => 'money2', 'sort' => true],
                    ['key' => 'spread', 'label' => 'Spread', 'type' => 'pct1', 'sort' => true], ['key' => 'first_rate', 'label' => 'First (₹)', 'type' => 'money2'], ['key' => 'latest', 'label' => 'Latest (₹)', 'type' => 'money2', 'sort' => true],
                    ['key' => 'change', 'label' => 'Change', 'type' => 'delta', 'sort' => true], ['key' => 'value', 'label' => 'Taxable (₹)', 'type' => 'money', 'sort' => true], ['key' => 'last', 'label' => 'Last sold', 'type' => 'date', 'sort' => true],
                ],
                'summary' => ['label' => 'Items shown', 'value' => count($rows), 'rows' => count($rows), 'lines' => $lines->count()],
            ];
        });
        return $this->paginate($data['rows'], $data['columns'], $p, $vary ? 'spread' : 'value', ['summary' => $data['summary'], 'vary' => $vary], $all);
    }
}
