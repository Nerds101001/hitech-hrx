@extends('layouts/contentLayoutMaster')

@section('title', 'Performance Scorecards')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h4 class="card-title">Automated Sales Appraisals</h4>
                @if(Auth::user()->hasRole(['admin', 'hr', 'manager']))
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateScorecardModal">Generate Scorecard</button>
                @endif
            </div>
            <div class="card-datatable table-responsive">
                <table class="datatables-users table border-top">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Cycle</th>
                            <th>Score</th>
                            <th>Grade</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($scorecards as $sc)
                        <tr>
                            <td>{{ $sc->user->first_name ?? '' }} {{ $sc->user->last_name ?? '' }}</td>
                            <td>{{ $sc->cycle->name ?? 'N/A' }}</td>
                            <td>{{ $sc->total_score }} / 100</td>
                            <td>{{ $sc->grade }}</td>
                            <td>{{ ucfirst($sc->status) }}</td>
                            <td>
                                <a href="{{ route('tenant.performance.scorecards.show', $sc->id) }}" class="btn btn-sm btn-info">View Report</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if(Auth::user()->hasRole(['admin', 'hr', 'manager']))
<!-- Generate Modal -->
<div class="modal fade" id="generateScorecardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('tenant.performance.scorecards.generate') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel1">Generate End-of-Year Scorecard</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        Generating this scorecard will auto-fetch data from Product Orders, Pipeline, MindSpeak (Innovation), and the 90-Day Grid.
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="user_id" class="form-label">Employee</label>
                            <select id="user_id" name="user_id" class="form-select" required>
                                <option value="">Select Employee</option>
                                @foreach(\App\Models\User::where('tenant_id', Auth::user()->tenant_id)->get() as $u)
                                    <option value="{{ $u->id }}">{{ $u->first_name }} {{ $u->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="appraisal_cycle_id" class="form-label">Appraisal Cycle</label>
                            <select id="appraisal_cycle_id" name="appraisal_cycle_id" class="form-select" required>
                                <option value="">Select Cycle</option>
                                @foreach($cycles as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Generate</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
