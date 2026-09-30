<?php

namespace App\Services;

use App\Models\CrmClientMapping;
use App\Support\NameMatcher;
use Illuminate\Support\Facades\DB;

/**
 * Works out which CRM customer (a crm_client_mappings row) a company name from
 * Tally, Setu or the Sales Report belongs to, so those pages can link to its
 * Customer Profile.
 *
 * Almost nothing stores that link, and opening the wrong company's profile is
 * worse than opening none, so resolve() only trusts two certain sources:
 *   1. a Sales Report row already linked to a CRM customer (CRM Mapping Sync);
 *   2. a CRM customer whose name is identical after NameMatcher::normalize(),
 *      and only when exactly one CRM customer carries that name.
 * Everything else is left for a person to choose from candidates().
 */
class CrmCustomerResolver
{
    /**
     * @param  string[]  $normalizedNames  NameMatcher::normalize() output
     * @return array<string,int>  normalized name => CrmClientMapping id, certain matches only
     */
    public function resolve(array $normalizedNames, ?array $crmNames = null): array
    {
        $wanted = array_values(array_unique(array_filter($normalizedNames)));
        if (!$wanted) {
            return [];
        }

        [$byName, $live] = $crmNames !== null ? $this->indexCrmNames($crmNames) : $this->fetchCrmNames();

        $found = [];

        // 1. Already linked from the Sales Report, when every linked row of that customer agrees.
        $linked = DB::table('customers AS c')
            ->join('sales_pipelines AS p', 'p.customer_id', '=', 'c.id')
            ->whereIn('c.normalized_name', $wanted)
            ->whereNull('p.deleted_at')->whereNotNull('p.crm_client_mapping_id')
            ->selectRaw('c.normalized_name AS k, MIN(p.crm_client_mapping_id) AS mid, COUNT(DISTINCT p.crm_client_mapping_id) AS n')
            ->groupBy('c.normalized_name')->get();
        foreach ($linked as $l) {
            if ((int) $l->n === 1 && isset($live[$l->mid])) {
                $found[$l->k] = (int) $l->mid;
            }
        }

        // 2. Same name, and nobody else has it.
        foreach ($wanted as $k) {
            if (!isset($found[$k]) && isset($byName[$k]) && count($byName[$k]) === 1) {
                $found[$k] = $byName[$k][0];
            }
        }

        return $found;
    }

    /**
     * The raw {id, name} list every fuzzy-match method here works from. Fetch it ONCE per request when a
     * caller needs both resolve() and its own scoring pass (e.g. a near-match suggestion) over the same
     * customers -- pass the result back into resolve()'s $crmNames parameter -- rather than letting each
     * method re-query the ~3-4k rows independently.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function crmNames(): array
    {
        return CrmClientMapping::query()->toBase()->get(['id', 'crm_company_name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->crm_company_name])->all();
    }

    /** @return array{0: array<string,int[]>, 1: array<int,bool>} [normalized name => ids, id => true] */
    private function indexCrmNames(array $crmNames): array
    {
        $byName = [];
        $live = [];
        foreach ($crmNames as $m) {
            $live[$m['id']] = true;
            $byName[NameMatcher::normalize($m['name'])][] = $m['id'];
        }
        return [$byName, $live];
    }

    private function fetchCrmNames(): array
    {
        return $this->indexCrmNames($this->crmNames());
    }

    /**
     * The normalised names whose Tally / Setu / Sales Report figures certainly
     * belong to this CRM customer, so its Customer Profile can show them: its
     * own name when no other CRM customer (or link) claims it, plus the names
     * of Sales Report customers already linked to it.
     *
     * @return array{0: string[], 1: int}  [names, how many CRM customers share this one's name]
     */
    public function keysFor(CrmClientMapping $mapping): array
    {
        $keys = [];
        $own = NameMatcher::normalize((string) $mapping->crm_company_name);
        $shared = 0;
        if ($own !== '') {
            if (($this->resolve([$own])[$own] ?? null) === (int) $mapping->id) {
                $keys[] = $own;
            } else {
                $shared = CrmClientMapping::query()->toBase()->get(['crm_company_name'])
                    ->filter(fn($m) => NameMatcher::normalize((string) $m->crm_company_name) === $own)->count();
            }
        }

        $linked = DB::table('customers AS c')->join('sales_pipelines AS p', 'p.customer_id', '=', 'c.id')
            ->where('p.crm_client_mapping_id', $mapping->id)->whereNull('p.deleted_at')
            ->distinct()->pluck('c.normalized_name')->filter()->all();

        return [array_values(array_unique(array_merge($keys, $linked))), $shared];
    }

    /**
     * One normalised name is the other with something added on the end (a plant,
     * a division, a bracketed number): "KISWOK" / "KISWOKUNIT1". The shorter one
     * must be long enough to mean something, and the whole of it must match --
     * so "HARIOMTECH" is not part of "HARIOMCONCASTPIPES" or "HARIRUBBERS".
     */
    public static function isPartOf(string $a, string $b): bool
    {
        if (min(strlen($a), strlen($b)) < 5) {
            return false;
        }
        return str_starts_with($a, $b) || str_starts_with($b, $a);
    }

    /**
     * CRM customers that could be the same company as $name, best first, for a
     * person to pick from -- never applied automatically. Listed only when the
     * CRM name is the same, is spelled almost the same (>= $nearPct similar),
     * or is the same name with something added or left off. Merely starting
     * with the same word ("HARI ...") is NOT enough: those are other companies.
     * Each item: id, name, code, city, state, stage, salesperson, why
     * ('same' | 'near' | 'part'), score (similarity %).
     *
     * @return array<int, array<string,mixed>>
     */
    public function candidates(string $name, float $nearPct = 85.0, int $limit = 10): array
    {
        $target = NameMatcher::normalize($name);
        if ($target === '') {
            return [];
        }

        $scored = [];
        foreach (CrmClientMapping::query()->toBase()->get(['id', 'crm_company_name']) as $m) {
            $label = (string) $m->crm_company_name;
            $norm = NameMatcher::normalize($label);
            if ($norm === '') {
                continue;
            }
            similar_text($target, $norm, $pct);
            $why = $pct >= 99.9 ? 'same' : ($pct >= $nearPct ? 'near' : (self::isPartOf($target, $norm) ? 'part' : null));
            if ($why) {
                $scored[] = ['id' => (int) $m->id, 'why' => $why, 'score' => round($pct, 1)];
            }
        }
        $rank = ['same' => 0, 'near' => 1, 'part' => 2];
        usort($scored, fn($a, $b) => [$rank[$a['why']], $b['score']] <=> [$rank[$b['why']], $a['score']]);
        $best = array_slice($scored, 0, $limit);
        if (!$best) {
            return [];
        }

        $rows = CrmClientMapping::with('salesperson')->whereIn('id', array_column($best, 'id'))->get()->keyBy('id');

        $out = [];
        foreach ($best as $b) {
            $m = $rows[$b['id']] ?? null;
            if (!$m) {
                continue;
            }
            $out[] = [
                'id' => $m->id,
                'name' => $m->crm_company_name,
                'code' => $m->crm_company_code,
                'city' => $m->crm_city,
                'state' => $m->crm_state,
                'stage' => $m->crm_stage,
                'salesperson' => $m->salesperson ? trim($m->salesperson->first_name . ' ' . $m->salesperson->last_name) : null,
                'why' => $b['why'],
                'score' => $b['score'],
            ];
        }
        return $out;
    }
}
