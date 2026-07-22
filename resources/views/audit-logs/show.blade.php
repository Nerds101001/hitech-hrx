@extends('layouts/layoutMaster')

@section('title', __('Audit Log Details'))

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('content')
<style>
  .ad-hero { background:linear-gradient(135deg,#0d7377 0%,#14a085 55%,#0a9396 100%);
             border-radius:16px; padding:1.6rem 1.8rem; color:#fff; box-shadow:0 8px 26px rgba(13,115,119,.25); }
  .ad-hero .who { font-size:1.25rem; font-weight:800; line-height:1.3; }
  .ad-hero .mail { color:rgba(255,255,255,.78); font-size:.83rem; }
  .ad-avatar { width:56px; height:56px; border-radius:50%; object-fit:cover;
               background:rgba(255,255,255,.18); color:#fff; font-weight:700;
               display:inline-flex; align-items:center; justify-content:center; font-size:1.05rem; }
  .ad-meta { background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.2);
             border-radius:10px; padding:.5rem .8rem; min-width:130px; }
  .ad-meta .k { font-size:.63rem; text-transform:uppercase; letter-spacing:.5px; color:rgba(255,255,255,.7); font-weight:700; }
  .ad-meta .v { font-size:.85rem; font-weight:700; }

  .ad-card { background:#fff; border:1px solid #eef2f2; border-radius:14px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
  .ad-card-h { padding:.9rem 1.2rem; border-bottom:1px solid #f0f3f3; font-weight:700; color:#0f766e; font-size:.95rem; }

  .ad-diff { width:100%; border-collapse:separate; border-spacing:0; }
  .ad-diff th { background:#f6f8f9; color:#6b7a7a; font-size:.68rem; text-transform:uppercase;
                letter-spacing:.5px; font-weight:700; padding:.6rem .9rem; border-bottom:1px solid #e6ecec; }
  .ad-diff td { padding:.6rem .9rem; font-size:.84rem; border-bottom:1px solid #f2f5f5; vertical-align:top; }
  .ad-before { color:#b02a37; background:#fdf3f2; border-radius:6px; padding:.2rem .45rem; display:inline-block; word-break:break-word; }
  .ad-after  { color:#15803d; background:#f0faf3; border-radius:6px; padding:.2rem .45rem; display:inline-block; word-break:break-word; }

  .ad-ev { display:inline-block; min-width:74px; text-align:center; font-size:.68rem; font-weight:700;
           letter-spacing:.3px; text-transform:uppercase; padding:.28rem .5rem; border-radius:6px; }
  .ad-ev.created { background:#e7f7ee; color:#15803d; }
  .ad-ev.updated { background:#e8f1fd; color:#1d4ed8; }
  .ad-ev.deleted { background:#fdecea; color:#b02a37; }
  .ad-ev.blocked { background:#fff4e5; color:#b45309; }
  .ad-ev.restored{ background:#f3e8ff; color:#6d28d9; }

  .ad-hist { width:100%; border-collapse:separate; border-spacing:0; }
  .ad-hist th { background:#f6f8f9; color:#6b7a7a; font-size:.66rem; text-transform:uppercase;
                letter-spacing:.5px; font-weight:700; padding:.55rem .8rem; border-bottom:1px solid #e6ecec; }
  .ad-hist td { padding:.55rem .8rem; font-size:.8rem; border-bottom:1px solid #f2f5f5; }
  .ad-hist tr.is-current td { background:#f0faf9; }
  .ad-hist tr:hover td { background:#fafbfb; }
  .ad-mono { font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.74rem; color:#64748b; }
</style>

<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0" style="color:#0f766e;">Audit Entry #{{ $log->id }}</h4>
    <a href="{{ route('auditLogs.index') }}" class="btn btn-sm btn-outline-secondary">
      <i class="bx bx-arrow-back"></i> Back to Audit Logs
    </a>
  </div>

  {{-- Who / when / where --}}
  <div class="ad-hero mb-4">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      @if($log->user && $log->user->getProfilePicture())
        <img src="{{ $log->user->getProfilePicture() }}" class="ad-avatar" alt="">
      @else
        <span class="ad-avatar">{{ $log->user ? $log->user->getInitials() : 'SY' }}</span>
      @endif
      <div class="me-auto">
        <div class="who">{{ $log->user ? $log->user->name : 'System' }}</div>
        <div class="mail">{{ $log->user->email ?? 'automated — no signed-in user' }}</div>
        <div class="mail">
          {{ optional(optional($log->user)->department)->name ?? 'No department' }}
          @if($log->user && $log->user->code) · {{ $log->user->code }} @endif
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <div class="ad-meta"><div class="k">Change type</div><div class="v">{{ ucfirst($log->event) }}</div></div>
        <div class="ad-meta"><div class="k">Record</div><div class="v">{{ $log->model_label }}</div></div>
        <div class="ad-meta"><div class="k">IP address</div><div class="v">{{ $log->ip_address ?: '—' }}</div></div>
        <div class="ad-meta"><div class="k">When</div><div class="v">{{ $log->created_at->format('d M Y, h:i A') }}</div></div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    {{-- What changed --}}
    <div class="col-lg-7">
      <div class="ad-card h-100">
        <div class="ad-card-h d-flex justify-content-between align-items-center">
          <span>What changed</span>
          <span class="text-muted fw-normal" style="font-size:.8rem;">{{ $log->model_label }}: {{ $log->record_label }}</span>
        </div>
        @if(count($changes))
          <div class="table-responsive">
            <table class="ad-diff">
              <thead><tr><th style="width:34%;">Field</th><th style="width:33%;">Before</th><th style="width:33%;">After</th></tr></thead>
              <tbody>
                @foreach($changes as $c)
                  <tr>
                    <td class="fw-semibold">{{ $c['field'] }}</td>
                    <td><span class="ad-before">{{ \Illuminate\Support\Str::limit($c['before'], 120) }}</span></td>
                    <td><span class="ad-after">{{ \Illuminate\Support\Str::limit($c['after'], 120) }}</span></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="p-4 text-center text-muted" style="font-size:.85rem;">
            No readable field changes on this entry.
          </div>
        @endif

        <div class="px-3 py-3 border-top" style="font-size:.78rem;">
          <div class="text-muted mb-1"><strong>Page:</strong> <span class="ad-mono">{{ $log->url ?: '—' }}</span></div>
          <div class="text-muted"><strong>Browser:</strong> <span class="ad-mono">{{ \Illuminate\Support\Str::limit($log->user_agent, 130) ?: '—' }}</span></div>
        </div>
      </div>
    </div>

    {{-- That person's recent activity --}}
    <div class="col-lg-5">
      <div class="ad-card h-100">
        <div class="ad-card-h d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span>Recent activity by this person</span>
          <form method="GET" class="d-flex align-items-center gap-1">
            @foreach(request()->except('hist') as $k => $v)
              <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <select name="hist" class="form-select form-select-sm" style="width:auto; font-size:.78rem;" onchange="this.form.submit()">
              @foreach([10,20,50] as $n)
                <option value="{{ $n }}" {{ $histCount === $n ? 'selected' : '' }}>Last {{ $n }}</option>
              @endforeach
            </select>
          </form>
        </div>
        @if($history->count())
          <div class="table-responsive" style="max-height:520px; overflow-y:auto;">
            <table class="ad-hist">
              <thead><tr><th>When</th><th>Type</th><th>What changed</th><th></th></tr></thead>
              <tbody>
                @foreach($history as $h)
                  <tr class="{{ $h->id === $log->id ? 'is-current' : '' }}">
                    <td class="text-nowrap">
                      {{ $h->created_at->format('d M') }}
                      <div class="text-muted" style="font-size:.7rem;">{{ $h->created_at->format('h:i A') }}</div>
                    </td>
                    <td><span class="ad-ev {{ $h->event }}">{{ $h->event }}</span></td>
                    <td>
                      <div class="text-truncate" style="max-width:200px;">{{ $h->model_label }}: {{ $h->record_label }}</div>
                      @if(count($h->changed_fields))
                        <div class="text-muted text-truncate" style="max-width:200px; font-size:.7rem;">
                          {{ implode(', ', array_slice($h->changed_fields, 0, 3)) }}{{ count($h->changed_fields) > 3 ? '…' : '' }}
                        </div>
                      @endif
                    </td>
                    <td class="text-end">
                      @if($h->id === $log->id)
                        <span class="badge bg-label-primary" style="font-size:.62rem;">This one</span>
                      @else
                        <a href="{{ route('auditLogs.show', $h->id) }}" class="text-decoration-none" title="Open"><i class="bx bx-chevron-right"></i></a>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="px-3 py-2 border-top text-end">
            <a href="{{ route('auditLogs.index', ['q' => $log->user->email ?? '']) }}" style="font-size:.78rem;">
              See everything by this person <i class="bx bx-right-arrow-alt"></i>
            </a>
          </div>
        @else
          <div class="p-4 text-center text-muted" style="font-size:.85rem;">
            This entry has no signed-in user, so there is no personal history to show.
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
