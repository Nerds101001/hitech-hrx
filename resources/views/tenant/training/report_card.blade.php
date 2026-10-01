@extends('layouts.layoutMaster')

@section('title', 'Training Report Card - ' . $user->name)

@section('page-style')
<style>
    /* The same sheet as the PDF (partials/report-card-sheet), sized for the screen. */
    .rc-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; max-width: 1040px; margin: 0 auto 16px; }
    .rc-toolbar h4 { margin: 0; font-weight: 800; color: #1e293b; }
    .rc-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .rc-btn { display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 18px; border-radius: 10px; font-weight: 600; font-size: .88rem; text-decoration: none; border: 1px solid #d5dfe3; background: #fff; color: #1e293b; cursor: pointer; }
    .rc-btn i { font-size: 1.2rem; }
    .rc-btn:hover { border-color: #005a5a; color: #005a5a; }
    .rc-btn.primary { background: #005a5a; border-color: #005a5a; color: #fff; }
    .rc-btn.primary:hover { background: #004646; color: #fff; }
    .rc-btn.success { background: #16a34a; border-color: #16a34a; color: #fff; }
    .rc-final { display: inline-flex; align-items: center; gap: 6px; background: #dcfce7; color: #15803d; font-weight: 700; font-size: .82rem; border-radius: 8px; padding: 8px 14px; }

    .rc-paper { max-width: 1040px; margin: 0 auto; background: #fff; border: 1px solid #e3ebee; border-radius: 12px; box-shadow: 0 8px 28px rgba(15, 23, 42, .06); overflow-x: auto; }
    .rc-sheet { color: #1f2937; font-size: .84rem; }
    .rc-sheet table { width: 100%; border-collapse: collapse; }
    .rc-sheet .muted { color: #94a3b8; }
    .rc-sheet .small { font-size: .9rem; }
    .rc-sheet .band { background: #005a5a; color: #fff; padding: 18px 26px 16px; border-bottom: 4px solid #007a7a; }
    .rc-sheet .band .kicker { font-size: .72rem; letter-spacing: 1px; color: #cfe6e5; text-transform: uppercase; }
    .rc-sheet .band h1 { font-size: 1.55rem; font-weight: 800; margin: 4px 0 2px; letter-spacing: .5px; color: #fff; }
    .rc-sheet .band .sub { font-size: .76rem; color: #cfe6e5; }
    .rc-sheet > .section, .rc-sheet > table, .rc-sheet > .verdict, .rc-sheet > .foot { margin-left: 26px; margin-right: 26px; width: calc(100% - 52px); }
    .rc-sheet .section { color: #005a5a; font-weight: 800; font-size: .8rem; letter-spacing: .8px; text-transform: uppercase; padding: 3px 0 3px 10px; border-left: 3px solid #007a7a; border-bottom: 2px solid #cbd5e1; margin-top: 18px; margin-bottom: 8px; }
    .rc-sheet .info { table-layout: fixed; }
    .rc-sheet .info td { border: 1px solid #e2e8f0; padding: 7px 10px; vertical-align: top; }
    .rc-sheet .info .k { background: #f8fafc; color: #475569; width: 11%; font-size: .7rem; text-transform: uppercase; letter-spacing: .3px; }
    .rc-sheet .info .v { width: 22.3%; font-weight: 700; color: #0f172a; word-wrap: break-word; }
    .rc-sheet .tiles { margin-top: 12px; border-collapse: separate; border-spacing: 8px 0; margin-left: 18px; margin-right: 18px; width: calc(100% - 36px); }
    .rc-sheet .tiles td { width: 16.66%; text-align: center; border: 1px solid #dee2e6; border-bottom: 3px solid #007a7a; border-radius: 8px; padding: 12px 6px; background: #fff; }
    .rc-sheet .tiles .n { font-size: 1.45rem; font-weight: 800; color: #005a5a; line-height: 1.2; }
    .rc-sheet .tiles .l { font-size: .68rem; color: #64748b; text-transform: uppercase; letter-spacing: .4px; margin-top: 2px; }
    .rc-sheet .grid th { background: #f1f5f9; color: #475569; font-size: .7rem; text-transform: uppercase; letter-spacing: .3px; text-align: left; padding: 8px 10px; border: 1px solid #e2e8f0; border-bottom: 2px solid #cbd5e1; white-space: nowrap; }
    .rc-sheet .grid td { border: 1px solid #e2e8f0; padding: 7px 10px; vertical-align: middle; }
    .rc-sheet .grid tr.alt td { background: #fbfcfd; }
    .rc-sheet .grid tr.kra td { background: #f0f7f6; font-weight: 800; color: #00706e; padding: 7px 10px; }
    .rc-sheet .grid .kra-meta { font-weight: 500; color: #475569; font-size: .76rem; }
    .rc-sheet .grid .total td { background: #005a5a; color: #fff; font-weight: 800; border-color: #005a5a; padding: 9px 10px; }
    .rc-sheet .grid .empty { font-style: italic; }
    .rc-sheet .grid th.num, .rc-sheet .num { text-align: right; white-space: nowrap; }
    .rc-sheet .grid th.ctr, .rc-sheet .ctr { text-align: center; }
    .rc-sheet .good { background: #ebf3f3; color: #005a5a; }
    .rc-sheet .warn { background: #fbf1e6; color: #d97706; }
    .rc-sheet .bad  { background: #ffeded; color: #e5484d; }
    .rc-sheet .idle { background: #f1f5f9; color: #475569; }
    .rc-sheet .chip { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
    .rc-sheet .verdict { margin-top: 14px; border: 1px solid #cfe3e2; border-left: 4px solid #007a7a; background: #f0f7f6; padding: 12px 16px; font-size: .88rem; border-radius: 0 8px 8px 0; }
    .rc-sheet .verdict .note { color: #475569; font-size: .8rem; margin-top: 5px; }
    .rc-sheet .notes td { padding: 0; height: 30px; vertical-align: bottom; }
    .rc-sheet .notes .who { width: 17%; color: #475569; font-size: .72rem; text-transform: uppercase; letter-spacing: .3px; }
    .rc-sheet .notes .ruled { border-bottom: 1px solid #cbd5e1; }
    .rc-sheet .sign { margin-top: 36px; }
    .rc-sheet .sign td { width: 33%; padding: 0 22px 0 0; vertical-align: bottom; }
    .rc-sheet .sign .line { border-top: 1px solid #94a3b8; padding-top: 5px; color: #475569; font-size: .74rem; }
    .rc-sheet .foot { margin-top: 18px; margin-bottom: 18px; border-top: 1px solid #e2e8f0; padding-top: 6px; color: #64748b; font-size: .72rem; }

    @media (max-width: 767.98px) {
        .rc-sheet { min-width: 720px; }   /* the table needs its columns: the sheet scrolls sideways instead */
        .rc-sheet .band { padding: 14px 14px 12px; }
        .rc-sheet .tiles td { padding: 8px 2px; }
        .rc-sheet .tiles .n { font-size: 1.05rem; }
    }
    /* Print just the sheet. */
    @media print {
        body * { visibility: hidden !important; }
        .rc-paper, .rc-paper * { visibility: visible !important; }
        .rc-paper { position: absolute; left: 0; top: 0; width: 100%; max-width: none; border: 0; box-shadow: none; border-radius: 0; }
        .rc-sheet .band, .rc-sheet .grid .total td, .rc-sheet .kra td, .rc-sheet .chip, .rc-sheet .good, .rc-sheet .warn, .rc-sheet .bad, .rc-sheet .idle { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endsection

@section('content')
<div class="rc-toolbar">
    <h4>Training Report Card</h4>
    <div class="rc-actions">
        <a href="{{ request()->fullUrlWithQuery(['pdf' => 1]) }}" class="rc-btn primary"><i class="bx bxs-file-pdf"></i> Download PDF</a>
        <button type="button" class="rc-btn" onclick="window.print()"><i class="bx bx-printer"></i> Print</button>
        @if($user->training_status !== 'completed' && ($canApprove ?? false))
            <button type="button" class="rc-btn success" id="approve-btn"><i class="bx bx-check-circle"></i> Approve &amp; Finalize Training</button>
        @elseif($user->training_status === 'completed')
            <span class="rc-final"><i class="bx bx-badge-check"></i> Training Finalized</span>
        @endif
    </div>
</div>

<div class="rc-paper">
    @include('tenant.training.partials.report-card-sheet', ['t' => $t, 'company' => $companyName ?? null])
</div>
@endsection

@section('page-script')
<script>
    document.getElementById('approve-btn')?.addEventListener('click', function() {
        if (confirm('Are you sure you want to approve and finalize this employee\'s training? This will grant them access to the main dashboard.')) {
            fetch('{{ route("training.approve", $user->id) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                }
            });
        }
    });
</script>
@endsection
