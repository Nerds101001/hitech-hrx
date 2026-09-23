{{-- Filters + the full weekly-status grid. @include'd from both Overview and Customers — same
     $paged/$filters/$weeks the parent view already built, so it always matches the tab it's
     rendered into. --}}
<div class="hitech-card mb-4">
  <div class="card-body p-3">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="w" value="{{ $selectedWeek }}">
      <div class="col-md-3"><input type="text" name="customer" value="{{ $filters['customer'] }}" class="form-control" placeholder="Search customer, product…"></div>
      <div class="col-md-2">
        <select name="status" class="form-select form-select-hitech" onchange="this.form.submit()">
          <option value="">All Status</option>
          @foreach($statuses as $s)<option value="{{ $s }}" {{ $filters['status'] === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach
        </select>
      </div>
      <div class="col-md-2">
        <select name="brand" class="form-select form-select-hitech" onchange="this.form.submit()">
          <option value="">All Products</option>
          @foreach($brands as $b)<option value="{{ $b }}" {{ $filters['brand'] === $b ? 'selected' : '' }}>{{ $b }}</option>@endforeach
        </select>
      </div>
      <div class="col-md-2">
        <select name="user_id" class="form-select form-select-hitech" onchange="this.form.submit()">
          <option value="">All Salespersons</option>
          @foreach($people as $p)<option value="{{ $p->id }}" {{ (int) $filters['user_id'] === $p->id ? 'selected' : '' }}>{{ $p->first_name }} {{ $p->last_name }}</option>@endforeach
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn-hitech rounded-pill px-4 flex-grow-1">Apply</button>
        <a href="{{ route('performance.ninety-day-goals.program', $program->id) }}" class="btn btn-hitech-cancel rounded-pill px-3">Clear</a>
        <a href="{{ route('performance.ninety-day-goals.admin-view.export', array_merge($filters, ['program_id' => $program->id])) }}" class="btn btn-hitech-cancel rounded-pill px-3"><i class="bx bx-download"></i></a>
      </div>
    </form>
  </div>
</div>

<div class="hitech-card mb-6">
  <div class="card-body p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="fw-bold text-dark">Customer Tracking &amp; Weekly Updates ({{ $rows->count() }} Customers)</div>
    <div class="d-flex flex-wrap gap-3 small text-muted">
      @foreach($G::STATUS_COLORS as $st => [$bg, $fg])
        <span class="d-inline-flex align-items-center gap-1"><span style="width:9px;height:9px;border-radius:50%;display:inline-block;background:{{ $fg }}"></span>{{ $st }}</span>
      @endforeach
    </div>
  </div>
  <div class="card-body p-0 ndg-mx-scroll">
    <table class="ndg-mx" style="font-size:.74rem">
      <thead><tr>
        <th class="ndg-mx-l" style="left:0">#</th><th class="ndg-mx-l" style="left:30px">Customer</th><th>Product</th><th>Salesperson</th><th>Target ₹</th><th>Expected</th>
        @for($w = 0; $w < $weeks; $w++)<th>W{{ $w }}</th>@endfor
        <th>Actual ₹</th><th>Ach.%</th><th>Status</th><th>Last Comment</th>
      </tr></thead>
      <tbody>
      @forelse($paged as $i => $r)
        @php
          $cks = $r->goal->checkins->keyBy('week_no');
          $lastComment = $r->goal->checkins->whereNotNull('comment')->sortByDesc('week_no')->first();
        @endphp
        <tr>
          <td class="ndg-mx-l" style="left:0">{{ ($paged->currentPage() - 1) * $paged->perPage() + $i + 1 }}</td>
          <td class="ndg-mx-l" style="left:30px;font-weight:600">{{ $r->goal->client_name }}</td>
          <td>{{ $r->goal->product }}</td>
          <td class="small text-muted">{{ $name($r->user) }}</td>
          <td>{{ number_format($r->goal->monthly_potential * 100000 / 100, 0) }}</td>
          <td class="small text-muted">{{ $r->goal->expected_order_date ? $r->goal->expected_order_date->format('d M Y') : '—' }}</td>
          @for($w = 0; $w < $weeks; $w++)
            @php
              $ck = $cks->get($w);
              $st = optional($ck)->status;
              [$bg] = $st ? ($G::STATUS_COLORS[$st] ?? ['#94a3b8']) : [null];
            @endphp
            <td class="ndg-mx-c" style="{{ $bg ? 'background:' . $bg : '' }}" title="{{ $st ?: '—' }}"></td>
          @endfor
          <td class="fw-semibold">{{ number_format($r->achieved * 100000 / 100, 0) }}</td>
          <td style="color:{{ $pctColor($r->pct) }}">{{ $pctText($r->pct) }}</td>
          <td>@if($r->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@else<span class="ndg-none">·</span>@endif</td>
          <td class="small text-muted" style="max-width:160px;white-space:normal">{{ $lastComment ? $lastComment->comment : '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="{{ 9 + $weeks }}" class="text-center text-muted p-5">
          No customers have been added to this program yet — {{ $participants }} {{ \Illuminate\Support\Str::plural('participant', $participants) }} enrolled, none have added a target customer.
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-body pt-3">{{ $paged->links() }}</div>
</div>
