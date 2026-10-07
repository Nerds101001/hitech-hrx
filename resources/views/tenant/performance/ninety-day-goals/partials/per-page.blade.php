{{-- "Show 10 / 25 / 50 / 100 / All" for a customer table. Keeps every other filter in the URL
     and goes back to page 1. --}}
@php
  $ppNow = request('per_page');
  $ppNow = $ppNow === 'all' ? 'all' : (in_array((int) $ppNow, \App\Services\NinetyDayGoalService::PER_PAGE_CHOICES, true) ? (int) $ppNow : ($ppDefault ?? 10));
@endphp
<form method="GET" class="d-inline-flex align-items-center gap-2 small text-muted mb-0">
  @foreach(request()->except(['per_page', 'page']) as $k => $v)
    @if(is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
  @endforeach
  <label class="mb-0" for="ppSel{{ $ppId ?? '' }}">Show</label>
  <select id="ppSel{{ $ppId ?? '' }}" name="per_page" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
    @foreach(\App\Services\NinetyDayGoalService::PER_PAGE_CHOICES as $n)<option value="{{ $n }}" {{ $ppNow === $n ? 'selected' : '' }}>{{ $n }}</option>@endforeach
    <option value="all" {{ $ppNow === 'all' ? 'selected' : '' }}>All</option>
  </select>
  <span>entries</span>
</form>
