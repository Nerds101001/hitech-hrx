@once
<style>
    @media (max-width: 767px) { .cs-tbl thead th { white-space: normal; } }
    @media (max-width: 575px) { .cs-tbl th, .cs-tbl td { padding-left: .4rem; padding-right: .4rem; } }
    @media (max-width: 399px) { .cp-xs-hide { display: none; } }
    @media (max-width: 359px) { .cs-tbl { font-size: .78rem; } .cs-tbl th, .cs-tbl td { padding-left: .25rem; padding-right: .25rem; } }
</style>
@endonce
{{-- One company's sales as HRX holds them: by month from the three sources, its Tally invoices, and its Tally receivables and orders.
     Shared by the Sales Sources company page and the CRM Customer Profile. Needs $co (App\Services\CompanySales::for()). --}}
@php
    $L = fn($v) => \App\Support\Fmt::lakh($v);
    $inr = fn($v) => \App\Support\Fmt::inr((float) $v, 0);
    $cov = $co['coverage'];
    $covText = ($cov['from'] && $cov['to'])
        ? \Carbon\Carbon::parse($cov['from'])->format('d M') . ' – ' . \Carbon\Carbon::parse($cov['to'])->format('d M Y') : null;
    $out = $co['outstanding'];
    $ord = $co['orders'];
@endphp

<h6 class="fw-bold mb-2">Sales by month <span class="text-muted fw-normal small">(₹ Lakhs)</span></h6>
@if(empty($co['monthRows']))
    <p class="text-muted small mb-3">Nothing is recorded for this company in Tally, Setu or the Sales Report.</p>
@else
    <div class="table-responsive mb-2">
        <table class="table table-sm mb-0 cs-tbl">
            <thead class="table-light">
                <tr><th>Month</th><th class="text-end">Sales Report</th><th class="text-end">Setu</th><th class="text-end">Tally</th><th class="text-end cp-xs-hide">Gap</th><th class="d-none d-sm-table-cell"></th></tr>
            </thead>
            <tbody>
                @foreach($co['monthRows'] as $r)
                    <tr>
                        <td>{{ \Carbon\Carbon::createFromFormat('!Y-m', $r['m'])->format('M Y') }}</td>
                        <td class="text-end">{{ $L($r['manual']) }}</td>
                        <td class="text-end">{{ $L($r['setu']) }}</td>
                        <td class="text-end fw-semibold">{{ $L($r['tally']) }}</td>
                        <td class="text-end cp-xs-hide {{ $r['differs'] ? 'text-danger fw-bold' : 'text-muted' }}">{{ $r['gap'] > 0 ? $L($r['gap']) : '—' }}</td>
                        <td class="d-none d-sm-table-cell">@if($r['differs'])<span class="badge bg-label-warning">Sources disagree</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="small text-muted mb-3">
        A blank means that source has nothing for the month.
        @if($covText)Tally covers {{ $covText }} only, so earlier months have no Tally figure.@endif
        A gap under &#8377;500 is treated as rounding.
    </div>
@endif

@php $shown = 15; $canOpen = !empty($billUrl ?? null); @endphp
<h6 class="fw-bold mb-2">Tally invoices
    @if($co['invoiceCount'])<span class="text-muted fw-normal small">latest {{ min($shown, $co['invoices']->count()) }} of {{ number_format($co['invoiceCount']) }} &middot; amounts include GST @if($canOpen)&middot; click an invoice to see its items @endif</span>@endif
</h6>
@if($co['branchTransfers'])
    <div class="alert alert-info py-2 small">
        {{ number_format($co['branchTransfers']['n']) }} Tally invoice{{ $co['branchTransfers']['n'] > 1 ? 's' : '' }} (₹{{ number_format($co['branchTransfers']['lakh'], 2) }} L) under this name are branch transfers &mdash; stock moved between Hi Tech's own branches &mdash; not customer sales, so they are left out of the Tally figures.
    </div>
@endif
@if($co['invoices']->isEmpty())
    <p class="text-muted small mb-3">No customer invoices under this company's name{{ $covText ? ' between ' . $covText : '' }}.</p>
@else
        <div class="table-responsive mb-2">
        <table class="table table-sm mb-0 cs-tbl">
            <thead class="table-light">
                <tr><th class="d-none d-sm-table-cell">Date</th><th>Invoice</th><th class="text-end d-none d-md-table-cell">Items</th><th class="text-end">Amount (₹)</th><th class="text-end d-none d-sm-table-cell">Still open (₹)</th><th class="d-none d-lg-table-cell">Salesperson</th><th class="d-none d-lg-table-cell">Destination</th>@if($canOpen)<th class="cp-xs-hide"></th>@endif</tr>
            </thead>
            <tbody>
                @foreach($co['invoices'] as $n => $i)
                    <tr @if($canOpen) class="cp-inv-row {{ $n >= $shown ? 'cp-inv-more d-none' : '' }}" data-invoice="{{ $i->invoice_no }}" data-url="{{ $billUrl }}" tabindex="0" role="button" title="Open the items on this invoice" @elseif($n >= $shown) class="cp-inv-more d-none" @endif>
                        <td class="text-nowrap d-none d-sm-table-cell">{{ \Carbon\Carbon::parse($i->invoice_date)->format('d M Y') }}</td>
                        <td>{{ $i->invoice_no }}<div class="d-sm-none small text-muted text-nowrap">{{ \Carbon\Carbon::parse($i->invoice_date)->format('d M Y') }}</div></td>
                        <td class="text-end d-none d-md-table-cell">{{ $i->items ?: '—' }}</td>
                        <td class="text-end fw-semibold">{{ $inr($i->amount) }}</td>
                        <td class="text-end d-none d-sm-table-cell">{{ (float) $i->open_amount != 0.0 ? $inr($i->open_amount) : '—' }}</td>
                        <td class="d-none d-lg-table-cell">{{ $i->salesperson ?: '—' }}</td>
                        <td class="d-none d-lg-table-cell">{{ $i->destination ?: '—' }}</td>
                        @if($canOpen)<td class="cp-inv-go text-end cp-xs-hide"><i class="bx bx-chevron-right"></i></td>@endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($co['invoices']->count() > $shown)
        @php $loaded = $co['invoices']->count(); $showLabel = $loaded >= $co['invoiceCount'] ? "Show all {$loaded} invoices" : "Show the latest {$loaded} invoices"; @endphp
        <button type="button" class="btn btn-sm btn-outline-secondary cp-inv-toggle mb-3" data-open="0" data-show-label="{{ $showLabel }}">{{ $showLabel }}</button>
    @endif
@endif

<div class="row g-3">
    <div class="col-12 col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="small text-muted fw-bold text-uppercase">Outstanding in Tally</div>
            @if($out && (int) $out->bills > 0)
                <div class="fs-5 fw-bold">₹{{ $inr($out->total) }}</div>
                <div class="small text-muted">
                    {{ number_format((int) $out->bills) }} bill{{ (int) $out->bills > 1 ? 's' : '' }}
                    &middot; oldest {{ \Carbon\Carbon::parse($out->oldest)->format('d M Y') }} ({{ (int) \Carbon\Carbon::parse($out->oldest)->diffInDays(now()) }} days)
                    @if((float) $out->over90 != 0.0)&middot; <span class="text-danger">₹{{ $inr($out->over90) }} older than 90 days</span>@endif
                </div>
            @else
                <div class="text-muted small">Nothing outstanding under this name.</div>
            @endif
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="small text-muted fw-bold text-uppercase">Sale orders in Tally</div>
            @if($ord && (int) $ord->n > 0)
                <div class="fs-5 fw-bold">₹{{ $inr($ord->total) }}</div>
                <div class="small text-muted">{{ number_format((int) $ord->n) }} order{{ (int) $ord->n > 1 ? 's' : '' }}</div>
            @else
                <div class="text-muted small">No sale orders under this name.</div>
            @endif
        </div>
    </div>
</div>
