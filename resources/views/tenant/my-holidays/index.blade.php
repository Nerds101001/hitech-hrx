@extends('layouts/layoutMaster')

@section('title', 'My Holidays')

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('page-style')
<style>

  .mh-hero{position:relative;overflow:hidden;border-radius:20px;padding:28px 32px;
    background:linear-gradient(135deg,var(--hitech-primary,#008080),#005a5a);color:#fff;
    display:flex;align-items:center;gap:24px;flex-wrap:wrap;margin-bottom:24px;}
  .mh-hero::after{content:'';position:absolute;right:-40px;top:-60px;width:220px;height:220px;
    border-radius:50%;background:rgba(255,255,255,0.06);}
  .mh-hero::before{content:'';position:absolute;right:60px;bottom:-70px;width:140px;height:140px;
    border-radius:50%;background:rgba(255,255,255,0.05);}
  .mh-hero-date{flex-shrink:0;background:rgba(255,255,255,0.14);border:1px solid rgba(255,255,255,0.25);
    border-radius:16px;padding:14px 20px;text-align:center;min-width:88px;backdrop-filter:blur(4px);z-index:1;}
  .mh-hero-date .d{font-size:2rem;font-weight:800;line-height:1;display:block;}
  .mh-hero-date .m{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;opacity:.85;}
  .mh-hero-body{z-index:1;flex:1;min-width:220px;}
  .mh-hero-eyebrow{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;opacity:.8;margin-bottom:4px;}
  .mh-hero-name{font-size:1.5rem;font-weight:800;margin:0 0 4px;}
  .mh-hero-meta{font-size:.85rem;opacity:.9;display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
  .mh-hero-countdown{z-index:1;text-align:center;background:rgba(255,255,255,0.14);border-radius:14px;padding:10px 18px;flex-shrink:0;}
  .mh-hero-countdown b{font-size:1.6rem;font-weight:800;display:block;line-height:1;}
  .mh-hero-countdown span{font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;opacity:.85;}
  .mh-hero-empty{padding:6px 0;}

  .mh-stats{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:26px;}
  .mh-stat{flex:1;min-width:140px;background:#fff;border:1px solid #e6ecf1;border-radius:14px;padding:14px 18px;}
  .mh-stat .v{font-size:1.4rem;font-weight:800;color:#0f172a;}
  .mh-stat .l{font-size:.74rem;color:#64748b;font-weight:600;}

  .mh-section-head{display:flex;align-items:center;gap:8px;margin:0 0 14px;}
  .mh-section-head h6{margin:0;font-weight:800;color:#0f172a;font-size:.95rem;}
  .mh-section-head .badge-hitech{font-size:.68rem;}

  .mh-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px;margin-bottom:30px;}
  .mh-card{background:#fff;border:1px solid #e6ecf1;border-radius:14px;padding:16px;display:flex;gap:14px;
    align-items:flex-start;transition:box-shadow .15s,border-color .15s;}
  .mh-card:hover{box-shadow:0 6px 18px rgba(0,0,0,.06);border-color:#d6e3e3;}
  .mh-card.is-today{border-color:var(--hitech-primary,#008080);background:var(--hitech-primary-light,rgba(0,128,128,.06));}
  .mh-card-date{flex-shrink:0;width:54px;text-align:center;background:#f1f5f9;border-radius:10px;padding:8px 0;}
  .mh-card-date .d{font-size:1.15rem;font-weight:800;color:#0f172a;line-height:1;display:block;}
  .mh-card-date .m{font-size:.62rem;font-weight:700;text-transform:uppercase;color:#64748b;}
  .mh-card.is-today .mh-card-date{background:var(--hitech-primary,#008080);}
  .mh-card.is-today .mh-card-date .d,.mh-card.is-today .mh-card-date .m{color:#fff;}
  .mh-card-body{min-width:0;flex:1;}
  .mh-card-name{font-weight:700;font-size:.9rem;color:#0f172a;margin-bottom:2px;}
  .mh-card-day{font-size:.72rem;color:#94a3b8;margin-bottom:8px;}
  .mh-card-tags{display:flex;gap:5px;flex-wrap:wrap;}
  .mh-scope-tag{display:inline-flex;align-items:center;gap:3px;font-size:.65rem;font-weight:700;color:#7c3aed;
    background:rgba(124,58,237,0.1);padding:.15rem .45rem;border-radius:6px;}

  .mh-empty{background:#fff;border:1px dashed #dbe3e8;border-radius:16px;padding:36px 20px;text-align:center;color:#94a3b8;}
  .mh-empty i{font-size:2rem;color:#0d9488;opacity:.5;}

  .mh-past-toggle{background:none;border:none;color:#64748b;font-size:.8rem;font-weight:700;
    display:flex;align-items:center;gap:6px;padding:0;cursor:pointer;}
  .mh-past-toggle:hover{color:var(--hitech-primary,#008080);}
  .mh-past-toggle .bx{transition:transform .2s;}
  .mh-past-toggle.is-open .bx{transform:rotate(90deg);}
  .mh-past-table{display:none;}
  .mh-past-table.is-open{display:block;}
  .mh-past-row{opacity:.65;}
</style>
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  <div class="px-4">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 py-2">
      <div>
        <h3 class="fw-extrabold mb-0 text-dark">My Holidays</h3>
        <p class="text-muted mb-0 small">Every holiday that applies to you — your site, your department, and every company-wide one.</p>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="{{ url('/my-holidays?year=' . ($year - 1)) }}" class="btn btn-sm btn-outline-hitech" aria-label="Previous year"><i class="bx bx-chevron-left"></i></a>
        <span class="fw-bold px-2">{{ $year }}</span>
        <a href="{{ url('/my-holidays?year=' . ($year + 1)) }}" class="btn btn-sm btn-outline-hitech" aria-label="Next year"><i class="bx bx-chevron-right"></i></a>
      </div>
    </div>

    {{-- Next holiday hero --}}
    @if($nextHoliday)
      <div class="mh-hero">
        <div class="mh-hero-date">
          <span class="d">{{ $nextHoliday->date->format('d') }}</span>
          <span class="m">{{ $nextHoliday->date->format('M') }}</span>
        </div>
        <div class="mh-hero-body">
          <div class="mh-hero-eyebrow">Your next holiday</div>
          <p class="mh-hero-name">{{ $nextHoliday->name }}</p>
          <div class="mh-hero-meta">
            <span><i class="bx bx-calendar"></i> {{ $nextHoliday->date->format('l, d M Y') }}</span>
            <span>·</span>
            <span>{{ $nextHoliday->type_label }}</span>
          </div>
        </div>
        <div class="mh-hero-countdown">
          @if($daysUntilNext === 0)
            <b>Today</b><span>Enjoy!</span>
          @elseif($daysUntilNext === 1)
            <b>1</b><span>day away</span>
          @else
            <b>{{ $daysUntilNext }}</b><span>days away</span>
          @endif
        </div>
      </div>
    @else
      <div class="mh-hero">
        <div class="mh-hero-body mh-hero-empty">
          <div class="mh-hero-eyebrow">Your next holiday</div>
          <p class="mh-hero-name mb-0">Nothing left on the calendar for {{ $year }}</p>
        </div>
      </div>
    @endif

    {{-- Quick stats --}}
    <div class="mh-stats">
      <div class="mh-stat"><div class="v">{{ $upcoming->count() + $past->count() }}</div><div class="l">Total in {{ $year }}</div></div>
      <div class="mh-stat"><div class="v">{{ $upcoming->count() }}</div><div class="l">Upcoming</div></div>
      <div class="mh-stat"><div class="v">{{ $past->count() }}</div><div class="l">Already passed</div></div>
    </div>

    {{-- Upcoming --}}
    <div class="mh-section-head">
      <h6><i class="bx bx-calendar-star me-1"></i> Upcoming</h6>
      <span class="badge badge-brand-soft badge-hitech">{{ $upcoming->count() }}</span>
    </div>
    @if($upcoming->isEmpty())
      <div class="mh-empty mb-4">
        <i class="bx bx-calendar-check d-block mb-2"></i>
        No upcoming holidays left in {{ $year }} for you.
      </div>
    @else
      <div class="mh-grid">
        @foreach($upcoming as $h)
          <div class="mh-card {{ $h->date->isToday() ? 'is-today' : '' }}">
            <div class="mh-card-date">
              <span class="d">{{ $h->date->format('d') }}</span>
              <span class="m">{{ $h->date->format('M') }}</span>
            </div>
            <div class="mh-card-body">
              <div class="mh-card-name">{{ $h->name }}</div>
              <div class="mh-card-day">
                {{ $h->date->isToday() ? 'Today · ' : '' }}{{ $h->date->format('l') }}
              </div>
              <div class="mh-card-tags">
                <span class="badge-hitech {{ $h->isFullDay() ? 'badge-hitech-success' : 'badge-brand-soft' }}">{{ $h->type_label }}</span>
                @if($h->site_id)
                  <span class="mh-scope-tag"><i class="bx bx-map-pin"></i> {{ optional($h->site)->name ?? 'Your site' }}</span>
                @endif
                @if($h->department_id)
                  <span class="mh-scope-tag"><i class="bx bx-buildings"></i> {{ optional($h->department)->name ?? 'Your dept' }}</span>
                @endif
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @endif

    {{-- Already passed (collapsed by default) --}}
    @if($past->isNotEmpty())
      <button type="button" class="mh-past-toggle mb-3" id="mhPastToggle">
        <i class="bx bx-chevron-right"></i> Already passed this year ({{ $past->count() }})
      </button>
      <div class="hitech-card mb-4 mh-past-table" id="mhPastTable">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="min-width:110px">Date</th>
                <th>Day</th>
                <th style="min-width:200px">Holiday</th>
                <th>Type</th>
              </tr>
            </thead>
            <tbody>
              @foreach($past as $h)
                <tr class="mh-past-row">
                  <td>{{ $h->date->format('d M Y') }}</td>
                  <td><small class="text-muted">{{ $h->date->format('l') }}</small></td>
                  <td>{{ $h->name }}</td>
                  <td><span class="badge-hitech badge-muted">{{ $h->type_label }}</span></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif

  </div>
</div>

<script>
(function () {
  var btn = document.getElementById('mhPastToggle');
  var table = document.getElementById('mhPastTable');
  if (!btn || !table) return;
  btn.addEventListener('click', function () {
    var open = table.classList.toggle('is-open');
    btn.classList.toggle('is-open', open);
  });
})();
</script>
@endsection
