<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Training Report Card - {{ trim($t->user->first_name . ' ' . $t->user->last_name) }}</title>
<style>
  /* Same sheet as the KPI scorecard PDF: HRX portal colours (teal #005a5a /
     #007a7a, slate headers #f1f5f9 on #475569, #e2e8f0 rules), dompdf, A4
     portrait, one page per person. A long card (many modules and sessions)
     tightens rather than spilling onto a second page. */
  @page { margin: 16px 20px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.2pt; color: #1f2937; margin: 0; }
  table { width: 100%; border-collapse: collapse; }
  .info { table-layout: fixed; }
  .muted { color: #94a3b8; }
  .small { font-size: 8pt; }

  .band { background: #005a5a; color: #fff; padding: 8px 14px 7px; border-bottom: 3px solid #007a7a; }
  .band .kicker { font-size: 6.4pt; letter-spacing: 1px; color: #cfe6e5; text-transform: uppercase; }
  .band h1 { font-size: 13.5pt; margin: 2px 0 1px; letter-spacing: .4px; }
  .band .sub { font-size: 6.6pt; color: #cfe6e5; }

  .section { color: #005a5a; font-weight: bold; font-size: 7.4pt; letter-spacing: .8px; text-transform: uppercase;
             padding: 2px 0 2px 8px; border-left: 3px solid #007a7a; border-bottom: 2px solid #cbd5e1; margin-top: 8px; }

  .info td { border: 1px solid #e2e8f0; padding: 3.5px 7px; vertical-align: top; }
  .info .k { background: #f8fafc; color: #475569; width: 11%; font-size: 6.4pt; text-transform: uppercase; letter-spacing: .3px; }
  .info .v { width: 22.3%; font-weight: bold; color: #0f172a; word-wrap: break-word; }

  .tiles { margin-top: 6px; }
  .tiles td { width: 16.66%; text-align: center; border: 1px solid #dee2e6; border-bottom: 3px solid #007a7a; padding: 5px 3px; background: #fff; }
  .tiles .n { font-size: 11.5pt; font-weight: bold; color: #005a5a; }
  .tiles .l { font-size: 6pt; color: #64748b; text-transform: uppercase; letter-spacing: .4px; margin-top: 1px; }

  .grid th { background: #f1f5f9; color: #475569; font-size: 6.4pt; text-transform: uppercase; letter-spacing: .3px; text-align: left;
             padding: 3px 6px; border: 1px solid #e2e8f0; border-bottom: 2px solid #cbd5e1; }
  .grid td { border: 1px solid #e2e8f0; padding: 3px 6px; vertical-align: top; }
  .grid tr.alt td { background: #fbfcfd; }
  .grid tr.kra td { background: #f0f7f6; font-weight: bold; color: #00706e; padding: 2.5px 6px; }
  .grid .kra-meta { font-weight: normal; color: #475569; font-size: 6.4pt; }
  .grid .total td { background: #005a5a; color: #fff; font-weight: bold; border-color: #005a5a; padding: 4px 6px; }
  .grid .empty { font-style: italic; }
  .grid th.num, .num { text-align: right; white-space: nowrap; }
  .grid th.ctr, .ctr { text-align: center; }

  .good { background: #ebf3f3; color: #005a5a; }
  .warn { background: #fbf1e6; color: #d97706; }
  .bad  { background: #ffeded; color: #ff4d49; }
  .idle { background: #f1f5f9; color: #475569; }
  .chip { display: inline-block; padding: 1px 5px; font-size: 6pt; font-weight: bold; }

  .verdict { margin-top: 7px; border: 1px solid #cfe3e2; border-left: 3px solid #007a7a; background: #f0f7f6; padding: 5px 9px; font-size: 7.4pt; }
  .verdict .note { color: #475569; font-size: 6.6pt; margin-top: 3px; }

  .notes td { padding: 0; height: 18px; vertical-align: bottom; }
  .notes .who { width: 17%; color: #475569; font-size: 6.4pt; text-transform: uppercase; letter-spacing: .3px; }
  .notes .ruled { border-bottom: 1px solid #cbd5e1; }
  .sign { margin-top: 18px; }
  .sign td { width: 33%; padding: 0 16px 0 0; vertical-align: bottom; }
  .sign .line { border-top: 1px solid #94a3b8; padding-top: 3px; color: #475569; font-size: 6.4pt; }
  .foot { margin-top: 8px; border-top: 1px solid #e2e8f0; padding-top: 3px; color: #64748b; font-size: 6pt; }

  /* A long card still has to fit one page. */
  .tight { font-size: 6.6pt; }
  .tight .grid td { padding: 1.5px 5px; }
  .tight .grid th { padding: 2px 5px; }
  .tight .info td { padding: 2px 6px; }
  .tight .tiles td { padding: 3px 3px; }
  .tight .tiles .n { font-size: 10pt; }
  .tight .section { margin-top: 5px; }
  .tight .notes td { height: 14px; }
  .tight .sign { margin-top: 10px; }
</style>
</head>
<body>
@php $rowCount = collect($t->groups)->sum(fn ($g) => max(1, count($g->rows)) + 1); @endphp
<div class="{{ $rowCount > 24 ? 'tight' : '' }}">
  @include('tenant.training.partials.report-card-sheet', ['t' => $t, 'company' => $companyName ?? null])
</div>
</body>
</html>
