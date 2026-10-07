{{-- Beside an order status: is it backed by the Sales Report? $r is a dashboard() row (status + achieved).
     The amount is the Sales Report's own figure for the round - never typed in on the 90-Day pages. --}}
@php $oc = \App\Services\NinetyDayGoalService::orderCheck($r); @endphp
@if($oc['claimed'] && $oc['known'])
  @if($oc['confirmed'])
    <span class="badge rounded-pill ms-1 ndg-order-ok" style="font-size:.6rem;background:#dcfce7;color:#166534" title="A sale for this customer is on the Sales Report for this program's months"><i class="bx bx-check"></i> ₹{{ rtrim(rtrim(number_format($oc['amount'], 2), '0'), '.') }} L on Sales Report</span>
  @else
    <span class="badge rounded-pill ms-1 ndg-order-wait" style="font-size:.6rem;background:#fef3c7;color:#92400e" title="Marked as an order, but no sale for this customer is on the Sales Report yet. It counts once the sale is entered there."><i class="bx bx-time-five"></i> awaiting Sales Report</span>
  @endif
@endif
