@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Scorecard Details')

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <a href="{{ route('performance.scorecards.index') }}" class="text-muted small d-inline-flex align-items-center gap-1 mb-2">
        <i class="bx bx-chevron-left"></i> Back to Scorecards
      </a>
      <h3 class="fw-extrabold mb-1 text-dark">{{ $scorecard->user->first_name ?? '' }} {{ $scorecard->user->last_name ?? '' }}</h3>
      <p class="text-muted mb-0 small">
        Annual Performance Review &middot; {{ $scorecard->cycle->name ?? 'N/A' }} &middot; Individual Appraisal Report
      </p>
    </div>
  </div>

  <div class="px-4">

    {{-- Summary strip: the "header" - employee/cycle context plus the
         two headline figures, kept as plain readable stat tiles rather
         than restyled away, matching what the request called out as
         already fine. --}}
    <div class="row row-cols-2 row-cols-md-4 g-6 mb-6 mx-0">
      <div class="col animate__animated animate__fadeInUp" style="animation-delay:.05s">
        <div class="hitech-stat-card dashboard-variant card-blue uniform-card">
          <div class="stat-card-header mb-2">
            <div class="stat-icon-wrap icon-blue" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-id-card"></i></div>
          </div>
          <div><h3 class="stat-value mb-0" style="font-size:1.1rem;">{{ $scorecard->user->code ?? 'N/A' }}</h3><span class="stat-label">Employee Code</span></div>
        </div>
      </div>
      <div class="col animate__animated animate__fadeInUp" style="animation-delay:.1s">
        <div class="hitech-stat-card dashboard-variant card-teal uniform-card">
          <div class="stat-card-header mb-2">
            <div class="stat-icon-wrap icon-teal" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-calendar"></i></div>
          </div>
          <div><h3 class="stat-value mb-0" style="font-size:1.1rem;">{{ $scorecard->cycle->name ?? 'N/A' }}</h3><span class="stat-label">Cycle</span></div>
        </div>
      </div>
      <div class="col animate__animated animate__fadeInUp" style="animation-delay:.15s">
        <div class="hitech-stat-card dashboard-variant card-amber uniform-card">
          <div class="stat-card-header mb-2">
            <div class="stat-icon-wrap icon-amber" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-trophy"></i></div>
          </div>
          <div><h3 class="stat-value mb-0">{{ $scorecard->total_score }} / 100</h3><span class="stat-label">Total Score</span></div>
        </div>
      </div>
      <div class="col animate__animated animate__fadeInUp" style="animation-delay:.2s">
        @php
          $gradeLetter = strtoupper(substr($scorecard->grade ?? '', 0, 1));
          $gradeVariant = ['A' => 'card-teal', 'B' => 'card-blue', 'C' => 'card-amber', 'D' => 'card-red'][$gradeLetter] ?? 'card-blue';
          $gradeIcon = ['A' => 'icon-teal', 'B' => 'icon-blue', 'C' => 'icon-amber', 'D' => 'icon-red'][$gradeLetter] ?? 'icon-blue';
        @endphp
        <div class="hitech-stat-card dashboard-variant {{ $gradeVariant }} uniform-card">
          <div class="stat-card-header mb-2">
            <div class="stat-icon-wrap {{ $gradeIcon }}" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-medal"></i></div>
          </div>
          <div><h3 class="stat-value mb-0" style="font-size:1rem;">{{ $scorecard->grade }}</h3><span class="stat-label">Grade</span></div>
        </div>
      </div>
    </div>

    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom d-flex align-items-center gap-2">
        <div class="hitech-icon-wrap" style="background: rgba(15, 118, 110, 0.1); color: #0f766e; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
          <i class="bx bx-bar-chart-alt-2 fs-5"></i>
        </div>
        <h6 class="mb-0 fw-bold">Performance Scorecard</h6>
      </div>
      <div class="table-responsive rounded-3">
        <table class="table table-hover align-middle mb-0" id="scorecardParamsTable">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Parameter</th>
              <th>Value Achieved</th>
              <th class="text-end" style="min-width:120px">Points Earned</th>
            </tr>
          </thead>
          <tbody>
            @php
              $rows = [
                ['Revenue Performance (Bingo)', number_format($scorecard->revenue_achieved) . ' / ' . number_format($scorecard->revenue_target), $scorecard->revenue_score],
                ['Pipeline Strength', number_format($scorecard->pipeline_value), $scorecard->pipeline_score],
                ['YoY Revenue Growth', $scorecard->yoy_growth_percentage . '%', $scorecard->yoy_growth_score],
                ['Trial Activity / Demos', $scorecard->demos_conducted . ' conducted', $scorecard->demos_score],
                ['New Customer Wins (%)', $scorecard->new_customer_revenue_percentage . '%', $scorecard->new_customer_score],
                ['90-Day Goals', $scorecard->ninety_day_goal_achievement . '% success on 12-week grid', $scorecard->ninety_day_goal_score],
                ['Sales Pitch Score', $scorecard->sales_pitch_score_raw . ' / 20', $scorecard->sales_pitch_score],
                ['Learn@HiTech', $scorecard->learn_hitech_programs . ' programs', $scorecard->learn_hitech_score],
                ['Seminars / Presentations', $scorecard->seminars_conducted . ' seminars', $scorecard->seminars_score],
                ['Competitor Insights', $scorecard->competitor_insights_count . ' insights', $scorecard->competitor_insights_score],
                ['New Product Ideas', $scorecard->product_ideas_count . ' ideas', $scorecard->product_ideas_score],
                ['Cost of Selling (COS%)', $scorecard->cost_of_selling_percentage . '%', $scorecard->cost_of_selling_score],
                ['Cross-Sell / Basket Mix', $scorecard->cross_sell_dominant_percentage . '% dominant product', $scorecard->cross_sell_score],
                ['Market Share %', $scorecard->market_share_percentage . '%', $scorecard->market_share_score],
              ];
            @endphp
            @foreach($rows as $i => $row)
              <tr>
                <td class="text-muted">{{ $i + 1 }}</td>
                <td class="fw-medium">{{ $row[0] }}</td>
                <td class="text-muted">{{ $row[1] }}</td>
                <td class="text-end">
                  <span class="badge-hitech {{ $row[2] > 0 ? 'badge-hitech-success' : 'badge-muted' }}">{{ $row[2] }}</span>
                </td>
              </tr>
            @endforeach
            <tr>
              <td class="text-muted">-</td>
              <td class="fw-medium text-danger">Attrition Penalty</td>
              <td class="text-muted">{{ $scorecard->attrition_rate }}%</td>
              <td class="text-end">
                <span class="badge-hitech badge-hitech-danger">{{ $scorecard->attrition_penalty }}</span>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="fw-bold" style="background: rgba(15, 118, 110, 0.06);">
              <td colspan="3" class="text-end">TOTAL SCORE</td>
              <td class="text-end">{{ $scorecard->total_score }} / 100</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    @if(auth()->user()->hasRole(['admin', 'hr', 'manager']))
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom d-flex align-items-center gap-2">
        <div class="hitech-icon-wrap" style="background: rgba(15, 118, 110, 0.1); color: #0f766e; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
          <i class="bx bx-message-square-edit fs-5"></i>
        </div>
        <h6 class="mb-0 fw-bold">Manager Review <span class="text-muted fw-normal small">(only visible to managers)</span></h6>
      </div>
      <div class="card-body p-4">
        <form action="{{ route('performance.scorecards.update-review', $scorecard->id) }}" method="POST" class="row g-4">
          @csrf
          <div class="col-md-4">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Manager Comments</label>
            <textarea name="manager_comments" class="form-control" rows="3">{{ $scorecard->manager_comments }}</textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Sales Pitch Score (out of 20)</label>
            <input type="number" name="sales_pitch_score_raw" class="form-control" value="{{ $scorecard->sales_pitch_score_raw }}" max="20" min="0">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Market Share Percentage (%)</label>
            <input type="number" name="market_share_percentage" class="form-control" value="{{ $scorecard->market_share_percentage }}" max="100" min="0" step="0.1">
          </div>
          <div class="col-12">
            <button type="submit" class="btn-hitech shadow-sm rounded-pill px-5"><i class="bx bx-save me-1"></i>Save Review</button>
          </div>
        </form>
      </div>
    </div>
    @endif

  </div>
</div>

<style>
  #scorecardParamsTable thead th {
    background: #0f766e;
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
    border: none;
    padding: .85rem 1rem;
  }
  #scorecardParamsTable tbody td {
    padding: .75rem 1rem;
  }
  #scorecardParamsTable tbody tr:nth-child(odd) {
    background: rgba(15, 118, 110, 0.02);
  }
</style>
@endsection
