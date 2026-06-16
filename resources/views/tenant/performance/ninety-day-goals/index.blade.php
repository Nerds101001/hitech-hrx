@extends('layouts/contentLayoutMaster')

@section('title', '90-Day Goals Tracker')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h4 class="card-title">12-Week 90-Day Goal Tracker</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGoalModal">Add Goal</button>
            </div>
            <div class="card-datatable table-responsive">
                <table class="datatables-users table border-top">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Client Name</th>
                            <th>Product</th>
                            <th>Target Value</th>
                            <th>W0</th><th>W1</th><th>W2</th><th>W3</th><th>W4</th><th>W5</th><th>W6</th><th>W7</th><th>W8</th><th>W9</th><th>W10</th><th>W11</th><th>W12</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($goals as $goal)
                        <tr>
                            <td>{{ $goal->user->first_name ?? '' }} {{ $goal->user->last_name ?? '' }}</td>
                            <td>{{ $goal->client_name }}</td>
                            <td>{{ $goal->product }}</td>
                            <td>{{ number_format($goal->target_value) }}</td>
                            @for($i=0; $i<=12; $i++)
                                @php $w = "w$i"; @endphp
                                <td>
                                    <select class="form-select form-select-sm week-select" data-id="{{ $goal->id }}" data-week="{{ $w }}">
                                        <option value="" @if(!$goal->$w) selected @endif>-</option>
                                        <option value="On Track" @if($goal->$w == 'On Track') selected @endif>On Track</option>
                                        <option value="First Trial Order" @if($goal->$w == 'First Trial Order') selected @endif>First Trial Order</option>
                                        <option value="Second TRial Order" @if($goal->$w == 'Second TRial Order') selected @endif>Second TRial Order</option>
                                        <option value="Regular Order" @if($goal->$w == 'Regular Order') selected @endif>Regular Order</option>
                                        <option value="Dropped" @if($goal->$w == 'Dropped') selected @endif>Dropped</option>
                                        <option value="No Report" @if($goal->$w == 'No Report') selected @endif>No Report</option>
                                    </select>
                                </td>
                            @endfor
                            <td>
                                <form action="{{ route('tenant.performance.ninety-day-goals.destroy', $goal->id) }}" method="POST" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger"><i class="bx bx-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Goal Modal -->
<div class="modal fade" id="addGoalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('tenant.performance.ninety-day-goals.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel1">Add Target Client (90-Day Goal)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col mb-3">
                            <label for="client_name" class="form-label">Client Name</label>
                            <input type="text" id="client_name" name="client_name" class="form-control" placeholder="Enter Client Name" required>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col mb-0">
                            <label for="product" class="form-label">Product</label>
                            <input type="text" id="product" name="product" class="form-control" placeholder="e.g. Rust X">
                        </div>
                        <div class="col mb-0">
                            <label for="goal_type" class="form-label">Goal Type</label>
                            <input type="text" id="goal_type" name="goal_type" class="form-control" value="Order">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col mb-0">
                            <label for="target_value" class="form-label">Target Value</label>
                            <input type="number" id="target_value" name="target_value" class="form-control" placeholder="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Goal</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('page-script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selects = document.querySelectorAll('.week-select');
        selects.forEach(select => {
            select.addEventListener('change', function() {
                const goalId = this.dataset.id;
                const weekField = this.dataset.week;
                const val = this.value;

                fetch(`/performance/ninety-day-goals/${goalId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        [weekField]: val
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        // Toast success
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                });
            });
        });
    });
</script>
@endsection
