@extends('tenant.sales-sources.layout')

@section('tab')
@php
  $inr = fn($v) => \App\Support\Fmt::inr((float) $v, 0);
  $plural = fn($n, $one, $many = null) => number_format($n) . ' ' . ($n == 1 ? $one : ($many ?? $one . 's'));
  $cov = $co['coverage'];
  $covText = ($cov['from'] && $cov['to']) ? \Carbon\Carbon::parse($cov['from'])->format('d M') . ' – ' . \Carbon\Carbon::parse($cov['to'])->format('d M Y') : null;
@endphp

<div class="hitech-card-white p-4 mb-3">
  <a href="{{ $back }}" class="small text-muted text-decoration-none"><i class="bx bx-arrow-back me-1"></i>Back to Compare</a>
  <h4 class="fw-bold mt-2 mb-1">{{ $name }}</h4>
  <div class="ss-sub">
    @if($co['codes'])Tally code {{ $co['codes'] }}@endif
    @if($co['tallySps'] || $co['manualSps']) &middot; {{ $co['tallySps'] ?: implode(', ', $co['manualSps']) }}@endif
    @if($co['invoiceCount']) &middot; {{ $plural($co['invoiceCount'], 'invoice') }},
      @if($co['firstDate'] === $co['lastDate']){{ \Carbon\Carbon::parse($co['lastDate'])->format('d M Y') }}@else{{ \Carbon\Carbon::parse($co['firstDate'])->format('d M Y') }} &rarr; {{ \Carbon\Carbon::parse($co['lastDate'])->format('d M Y') }}@endif
    @endif
  </div>
  @if($party && ($party->gstin || $party->pan || $party->contact_person || $party->contact_mobile))
    <div class="ss-sub mt-1">
      @if($party->gstin)<span class="ss-flag info">GSTIN {{ $party->gstin }}</span>@endif
      @if($party->pan)<span class="ss-flag info">PAN {{ $party->pan }}</span>@endif
      @if($party->contact_person)<i class="bx bx-user me-1"></i>{{ $party->contact_person }}@endif
      @if($party->contact_mobile) &middot; <i class="bx bx-phone me-1"></i>{{ $party->contact_mobile }}@endif
    </div>
  @endif
  <div class="alert alert-warning py-2 small mt-3 mb-0">
    <i class="bx bx-info-circle me-1"></i>
    This company is not in the CRM customer list, so it has no CRM Customer Profile (team, contacts, calls) yet. Below is what Tally, Setu and the Sales Report hold for it.
    @if($sameName > 1)
      {{ $sameName }} CRM customers have exactly this name, so it can't be told which one this is &mdash; they are listed at the bottom.
    @elseif($candidates)
      Possible CRM matches are listed at the bottom.
    @endif
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Tally sales</div><div class="val">₹{{ \App\Support\Fmt::lakh(array_sum($co['tally'])) }} L</div><div class="ss-sub">{{ $plural($co['invoiceCount'], 'customer invoice') }}{{ $covText ? ' · ' . $covText : '' }}</div></div></div>
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Outstanding</div><div class="val">₹{{ $inr($co['outstanding']->total ?? 0) }}</div><div class="ss-sub">{{ $plural((int) ($co['outstanding']->bills ?? 0), 'bill') }}</div></div></div>
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Sale orders</div><div class="val">₹{{ $inr($co['orders']->total ?? 0) }}</div><div class="ss-sub">{{ $plural((int) ($co['orders']->n ?? 0), 'order') }}</div></div></div>
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Last customer invoice</div><div class="val">{{ $co['lastDate'] ? \Carbon\Carbon::parse($co['lastDate'])->format('d M Y') : '—' }}</div></div></div>
  @if($party && $party->credit_limit !== null)
    <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Credit limit</div><div class="val">₹{{ $inr($party->credit_limit) }}</div><div class="ss-sub">from Tally's ledger master</div></div></div>
  @endif
</div>

<div class="hitech-card-white p-4 mb-3">
  @include('tenant.sales-sources.partials.company-sales', ['co' => $co, 'billUrl' => route('sales-sources.analytics.bill') . '?invoice_no='])
</div>

@if($candidates)
  <div class="hitech-card-white overflow-hidden mb-3">
    <div class="p-3 pb-0"><h6 class="fw-bold mb-1">Possible CRM customers</h6>
      <div class="small text-muted">Listed only when the CRM name is the same, is spelled almost the same, or is this name with a plant or division added or left off. Nothing is linked automatically &mdash; check before relying on one.</div></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover ss-table mb-0">
        <thead class="table-light">
          <tr><th style="width:210px;">Why it is listed</th><th style="min-width:260px;">CRM customer</th><th>Location</th><th>Salesperson</th><th>Stage</th><th></th></tr>
        </thead>
        <tbody>
          @foreach($candidates as $c)
            <tr>
              <td>
                @if($c['why'] === 'same')<span class="ss-flag ok">Same name</span>
                @elseif($c['why'] === 'near')<span class="ss-flag info">Spelled almost the same</span>
                @else<span class="ss-flag warn">One name is part of the other</span>@endif
              </td>
              <td>
                <div class="fw-semibold">{{ $c['name'] }}</div>
                @if($c['code'])<div class="ss-sub">CRM code {{ $c['code'] }}</div>@endif
              </td>
              <td>{{ collect([$c['city'], $c['state']])->filter()->implode(', ') ?: '—' }}</td>
              <td>{{ $c['salesperson'] ?: '—' }}</td>
              <td>{{ $c['stage'] ?: '—' }}</td>
              <td class="text-end"><a href="{{ route('crm-clients.profile', $c['id']) }}" class="btn btn-sm btn-outline-primary">Open profile</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif
@endsection

@section('tab-modals')
    @include('tenant.sales-sources.partials.invoice-modal')
@endsection
