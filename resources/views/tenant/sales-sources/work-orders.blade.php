@extends('tenant.sales-sources.layout')

@section('tab')
@php $inr = fn($v) => \App\Support\Fmt::inr($v); @endphp

<ul class="nav ss-tabs mb-3" style="border-bottom:1px solid #e8edf3;">
  @foreach(['all' => "All ({$allOrdersCount})", 'pending' => "Pending ({$pendingCount})", 'dispatch' => "Dispatch ({$dispatchCount})", 'financial' => "Financial ({$financialHoldCount})"] as $key => $label)
    <li class="nav-item">
      <a class="nav-link {{ $subTab === $key ? 'active' : '' }}" href="{{ route('sales-sources.work-orders', ['tab' => $key]) }}">{{ $label }}</a>
    </li>
  @endforeach
</ul>

@if($subTab === 'financial')
  @include('tenant.sales-sources.partials.work-orders-financial')
@else
  @include('tenant.sales-sources.partials.work-orders-panel', ['panelTab' => $subTab])
@endif

<p class="small text-muted mb-4">
  Pending qty and dispatch status are matched from Delivery Note vouchers Tally links back to the order — never an estimated date (Tally records none for this company).
  Financial Approval and Remarks are real Tally fields (see the Financial tab) — Production Approval is not: confirmed absent from every Tally voucher of every type, not even as a custom field.
  Transporter/vehicle/docket detail is also usually blank: checked a full day of real Delivery Notes and none of them had any transport detail recorded in Tally at all — it isn't a sync gap, your team just isn't entering it there yet.
  Tracking details for a fully-dispatched order can be added below even when Tally itself has none recorded; these are stored separately and are never overwritten by the sync.
</p>
@endsection

@section('tab-modals')
  @include('tenant.sales-sources.partials.work-order-modal')
@endsection
