<?php

namespace App\Support;

/**
 * Fuzzy company/party-name matching, extracted from SetuReportService (the
 * Setu-vs-sales_pipelines reconciliation) so the same logic can also drive
 * the sales_pipelines-vs-CrmClientMapping reconciliation without a second,
 * silently-diverging copy of the same normalisation rules.
 */
class NameMatcher
{
    /**
     * Uppercases, unifies "&" with the word "AND" (before stripping
     * non-alphanumerics, so "Weld & Flux" and "Weld And Flux" collapse to the
     * same string), strips common corporate suffixes (PVT/LTD/CO/...), then
     * strips everything but letters and digits. Deliberately narrow: it does
     * NOT touch distinguishing tokens (Unit I/II, G-05, (N), SEZ, -Pune),
     * which survive as-is so genuinely different sub-entities never collapse
     * into one match.
     */
    public static function normalize(string $s): string
    {
        $s = mb_strtoupper($s);
        $s = preg_replace('/&/', ' AND ', $s);
        $s = preg_replace('/\b(PVT|PRIVATE|LTD|LIMITED|LMT|CO|COMPANY|CORPORATION|INDUSTRIES|IND|MFG|INDIA)\b/', ' ', $s);
        return preg_replace('/[^A-Z0-9]/', '', $s);
    }

    /**
     * Score $target's normalised name against each candidate's normalised
     * 'label', keeping only those scoring >= $minPct (similar_text, best
     * first, capped at $limit). Every candidate array is returned as-is with
     * a 'score' key added, so callers can carry whatever identifying data
     * (an id, a model) they need through to the review UI.
     *
     * @param  array<int, array{label: string}>  $candidates
     * @return array<int, array{label: string, score: float}>
     */
    public static function bestMatches(string $target, array $candidates, float $minPct = 80.0, int $limit = 5): array
    {
        $normTarget = self::normalize($target);
        if ($normTarget === '') {
            return [];
        }

        $scored = [];
        foreach ($candidates as $candidate) {
            $norm = self::normalize((string) ($candidate['label'] ?? ''));
            if ($norm === '') {
                continue;
            }
            similar_text($normTarget, $norm, $pct);
            if ($pct >= $minPct) {
                $scored[] = $candidate + ['score' => round($pct, 1)];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $limit);
    }
}
