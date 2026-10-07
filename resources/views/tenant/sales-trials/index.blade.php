@extends('layouts/layoutMaster')
@section('title', 'Trials')

@section('content')
<div class="container-fluid">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bx bx-test-tube me-1"></i> Trials</h4>
      <div class="text-muted small">Product trials — booked by CCare/New Biz or lodged by the salesperson in the field.</div>
    </div>
    <div class="d-flex gap-2">
      @if($canBook)<a href="{{ route('sales-visits.trials.book') }}" class="btn btn-outline-primary"><i class="bx bx-calendar-plus me-1"></i> Book Trial</a>@endif
      @if($canLodge)<a href="{{ route('sales-visits.trials.create') }}" class="btn btn-success"><i class="bx bx-plus me-1"></i> Lodge Trial</a>@endif
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif

  <form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-6 col-md-2"><label class="form-label small mb-1">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">All</option>
        @foreach(['booked','completed','cancelled'] as $s)<option value="{{ $s }}" {{ ($filters['status']??'')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>@endforeach
      </select></div>
    @if($isAdmin)
      <div class="col-6 col-md-3"><label class="form-label small mb-1">Salesperson</label>
        <select name="salesperson_id" class="form-select form-select-sm"><option value="">All</option>
          @foreach($people as $p)<option value="{{ $p->id }}" {{ ($filters['salesperson_id']??'')==$p->id?'selected':'' }}>{{ $p->first_name }} {{ $p->last_name }}</option>@endforeach
        </select></div>
    @endif
    <div class="col-6 col-md-2"><label class="form-label small mb-1">From</label><input type="date" name="from" value="{{ $filters['from']??'' }}" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1">To</label><input type="date" name="to" value="{{ $filters['to']??'' }}" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-2 d-flex gap-2"><button class="btn btn-sm btn-primary w-100">Filter</button><a href="{{ route('sales-visits.trials.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a></div>
  </form>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr class="small text-muted text-uppercase">
          <th class="ps-3">Company</th><th>Salesperson</th><th>Topic / Product</th><th>Date</th><th>Source</th><th>Photos</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        @forelse($trials as $t)
          <tr>
            <td class="ps-3 fw-semibold">{{ $t->company_name ?: '—' }}</td>
            <td class="small">{{ optional($t->salesperson)->first_name }} {{ optional($t->salesperson)->last_name }}<br><span class="text-muted">{{ optional($t->salesperson)->code }}</span></td>
            <td class="small">{{ $t->topic ?: '—' }}<br><span class="text-muted">{{ $t->product }}</span></td>
            <td class="small">{{ optional($t->trial_date)->format('d M Y') ?: '—' }}<br><span class="text-muted">{{ $t->trial_time ? substr((string)$t->trial_time,0,5) : '' }}</span></td>
            <td class="small">{{ $t->sourceLabel() }}</td>
            <td class="small">{{ $t->images->count() }}</td>
            <td><span class="badge {{ $t->statusBadge() }}">{{ ucfirst($t->status) }}</span></td>
            <td class="text-end pe-3"><a href="{{ route('sales-visits.trials.show', $t->id) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4">No trials yet. @if($canLodge)Use <b>Lodge Trial</b> to add one.@endif</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    @if($trials->hasPages())<div class="card-footer bg-transparent">{{ $trials->links() }}</div>@endif
  </div>
</div>
@endsection
