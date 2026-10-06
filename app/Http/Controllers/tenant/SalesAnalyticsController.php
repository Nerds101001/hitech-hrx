<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\TallyImport;
use App\Services\TallyAnalytics;
use App\Support\ProductGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sales Sources > Analytics: the Tally sales dashboard. The page is an empty shell
 * that paints instantly and then pulls its data as JSON from `data()`, one call for
 * the whole overview and one per drill-down tab; all the numbers come from
 * TallyAnalytics, which caches per import. Read-only, company-wide, so it is limited
 * to admin/HR/accounts exactly like the rest of Sales Sources.
 */
class SalesAnalyticsController extends Controller
{
    private const PANELS = ['overview', 'bills', 'items', 'customers', 'salespersons', 'gst', 'price'];
    private const EXPORTS = ['bills', 'items', 'customers', 'salespersons', 'gst', 'price'];

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR', 'accounts']), 403);
        abort_unless(\App\Http\Middleware\RestrictTallyAccess::permits($user), 404);   // the named viewer(s) only
    }

    /** Filters + list parameters from the query string. */
    private function service(Request $request): TallyAnalytics
    {
        return new TallyAnalytics(TallyAnalytics::filters($request->query()));
    }

    /** The page shell: filter choices and the quick ranges only; every number arrives later as JSON. */
    public function index(Request $request)
    {
        $this->authorizeAccess();

        $importId = TallyAnalytics::importId();
        $choices = Cache::store('file')->remember("tally.an.choices.{$importId}", 43200, fn() => [
            'salespersons' => DB::table('tally_invoices')->whereNotNull('salesperson')->where('salesperson', '!=', '')->distinct()->orderBy('salesperson')->pluck('salesperson')->all(),
            'series' => DB::table('tally_invoices')->whereNotNull('series')->distinct()->orderBy('series')->pluck('series')->all(),
        ]);

        $filters = TallyAnalytics::filters($request->query());

        return view('tenant.sales-sources.analytics', [
            'tab' => 'analytics',
            'charts' => true,
            'lastImport' => TallyImport::latestGood(),
            'lastSync' => TallyImport::latestSync(),
            'pageConfigs' => ['contentLayout' => 'wide'],
            'filters' => $filters,
            // Embedded so the first paint needs no second request (cached per import, so this is normally a few milliseconds).
            'overview' => (new TallyAnalytics($filters))->overview(),
            'presets' => TallyAnalytics::presets(),
            'bounds' => TallyAnalytics::bounds(),
            'salespersons' => $choices['salespersons'],
            'seriesChoices' => $choices['series'],
            'importId' => $importId,
            'palette' => ProductGroup::palette(),
        ]);
    }

    public function data(Request $request)
    {
        $this->authorizeAccess();
        $panel = (string) $request->query('panel', 'overview');
        abort_unless(in_array($panel, self::PANELS, true), 404);

        $a = $this->service($request);
        $p = $request->query();
        $payload = match ($panel) {
            'overview' => $a->overview(),
            'bills' => $a->bills($p),
            'items' => $a->items($p),
            'customers' => $a->customers($p),
            'salespersons' => $a->salespersons($p),
            'gst' => $a->gst($p),
            'price' => $a->price($p),
        };

        // Identical requests within a minute are answered by the browser itself; the version in the URL changes with every import.
        return response()->json($payload)->header('Cache-Control', 'private, max-age=60');
    }

    public function bill(Request $request)
    {
        $this->authorizeAccess();
        $bill = $this->service($request)->bill((string) $request->query('invoice_no'));
        abort_if($bill === null, 404);
        return response()->json($bill)->header('Cache-Control', 'private, max-age=60');
    }

    public function item(Request $request)
    {
        $this->authorizeAccess();
        $name = (string) $request->query('item');
        abort_if($name === '', 404);
        $scope = $request->query('scope') === 'all' ? 'all' : 'period';
        return response()->json($this->service($request)->item($name, (string) $request->query('unit', ''), $scope))->header('Cache-Control', 'private, max-age=60');
    }

    public function customer(Request $request)
    {
        $this->authorizeAccess();
        $c = $this->service($request)->customer((string) $request->query('code'));
        abort_if($c === null, 404);
        return response()->json($c)->header('Cache-Control', 'private, max-age=60');
    }

    /** The current table, with the current filters and search, as a spreadsheet-ready CSV. */
    public function export(Request $request)
    {
        $this->authorizeAccess();
        $panel = (string) $request->query('panel');
        abort_unless(in_array($panel, self::EXPORTS, true), 404);

        $a = $this->service($request);
        $p = $request->query();
        $table = match ($panel) {
            'bills' => $a->bills($p, true),
            'items' => $a->items($p, true),
            'customers' => $a->customers($p, true),
            'salespersons' => $a->salespersons($p, true),
            'gst' => $a->gst($p, true),
            'price' => $a->price($p, true),
        };

        $f = $a->filtersInUse();
        $name = "tally-{$panel}-{$f['from']}-to-{$f['to']}.csv";
        $columns = $table['columns'];

        return response()->streamDownload(function () use ($table, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");                      // BOM so Excel reads the rupee sign and names correctly
            fputcsv($out, array_column($columns, 'label'));
            foreach ($table['rows'] as $row) {
                $line = [];
                foreach ($columns as $c) {
                    $v = $row[$c['key']] ?? '';
                    if ($c['type'] === 'party' && !empty($row['party_code'])) {
                        $v = "{$v} ({$row['party_code']})";
                    }
                    // A cell that starts with = + - @ would be run as a formula by Excel: Tally text is not to be trusted with that.
                    $line[] = is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v;
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
