@extends('layouts/layoutMaster')

@section('title', 'DWM Daily Work Management - Log Your Tasks')

@section('vendor-style')
@vite([
  'resources/assets/vendor/scss/pages/hitech-portal.scss'
])
@endsection

@section('page-style')
<style>
  /* ===================== Hero ===================== */
  .sv-hero {
    background: linear-gradient(135deg, #0d7377 0%, #14a085 40%, #0a9396 70%, #00b4d8 100%);
    border-radius: 18px;
    padding: 2.2rem 2.6rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(13,115,119,0.30);
  }
  .sv-hero::before {
    content:''; position:absolute; top:-50px; right:-50px;
    width:200px; height:200px; background:rgba(255,255,255,0.07); border-radius:50%;
  }
  .sv-hero-title { font-size:1.75rem; font-weight:800; color:#fff; margin-bottom:0.25rem; }
  .sv-hero-subtitle { color:rgba(255,255,255,0.80); font-size:0.95rem; margin-bottom:0; }

  /* ===================== Form Card ===================== */
  .sv-form-card {
    border-radius: 18px;
    box-shadow: 0 4px 28px rgba(0,0,0,0.09);
    border: none;
    overflow: hidden;
  }
  .sv-section-title {
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #0d7377;
    margin-bottom: 0.65rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
  }
  .sv-section-title::after {
    content: '';
    flex: 1;
    height: 2px;
    background: linear-gradient(90deg, #c7eaea, transparent);
    border-radius: 2px;
    margin-left: 0.5rem;
  }
  .sv-field-group {
    background: #f8fffe;
    border-radius: 12px;
    padding: 1.2rem 1.4rem;
    margin-bottom: 1.4rem;
    border: 1px solid #e0f5f5;
  }

  /* ===================== Inputs ===================== */
  .sv-form-card .form-control,
  .sv-form-card .form-select {
    border-radius: 10px !important;
    border-color: #d8dce8 !important;
    font-size: 0.9rem;
    min-height: 42px;
  }
  .sv-form-card .form-control:focus,
  .sv-form-card .form-select:focus {
    border-color: #0d7377 !important;
    box-shadow: 0 0 0 3px rgba(13,115,119,0.12) !important;
  }
  .sv-form-card .form-label {
    font-size: 0.83rem;
    font-weight: 700;
    color: #3d4056;
    margin-bottom: 0.35rem;
  }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y animate__animated animate__fadeIn px-4">
    <div class="sv-hero mb-4">
        <h1 class="sv-hero-title">📝 Log Your Tasks</h1>
        <p class="sv-hero-subtitle">Every team member logs the tasks they perform on a regular basis — daily, weekly, and monthly — with a time limit for each. A live tally shows your daily committed time.</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible d-flex align-items-center" role="alert" style="border-radius: 12px; border-left: 5px solid;">
            <i class="bx bx-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible d-flex align-items-center" role="alert" style="border-radius: 12px; border-left: 5px solid;">
            <i class="bx bx-error-circle me-2" style="font-size: 1.5rem;"></i>
            <div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card sv-form-card mb-4">
        <div class="card-body p-4 p-md-5">
            <form action="{{ route('tenant.work-control.store') }}" method="POST" id="routinesForm">
                @csrf
                
                <h5 class="sv-section-title"><i class="bx bx-user me-1"></i> User Info</h5>
                <div class="sv-field-group">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label text-dark fw-bold">YOUR NAME</label>
                        <input type="text" class="form-control bg-white text-dark" value="{{ $user->name }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-dark fw-bold">DEPARTMENT</label>
                        @php
                            $deptName = $user->department ? $user->department->name : ($user->designation && $user->designation->department ? $user->designation->department->name : 'N/A');
                        @endphp
                        <input type="text" class="form-control bg-white text-dark" value="{{ $deptName }}" readonly>
                    </div>
                </div>
                </div> <!-- /sv-field-group -->

                <div class="mt-4 mb-3 d-flex justify-content-between align-items-center">
                    <h5 class="sv-section-title mb-0"><i class="bx bx-list-check me-1"></i> Tasks</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" style="border-radius: 8px; font-weight: 700;" onclick="addRoutineRow()">
                        <i class="bx bx-plus me-1"></i> Add Task
                    </button>
                </div>
                
                <div class="sv-field-group">
                    <div id="routinesContainer">
                    @foreach($routines as $index => $routine)
                        <div class="row mb-2 routine-row" data-index="{{ $index }}">
                            <input type="hidden" name="routines[{{ $index }}][id]" value="{{ $routine->id }}">
                            <div class="col-md-4">
                                <input type="text" class="form-control text-dark" name="routines[{{ $index }}][title]" value="{{ $routine->title }}" placeholder="Task description" required>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select frequency-select text-dark" name="routines[{{ $index }}][frequency]" required onchange="calculateTotals()">
                                    <option value="daily" {{ $routine->frequency == 'daily' ? 'selected' : '' }}>Every Day</option>
                                    <option value="weekly" {{ $routine->frequency == 'weekly' ? 'selected' : '' }}>Every Week</option>
                                    <option value="fortnightly" {{ $routine->frequency == 'fortnightly' ? 'selected' : '' }}>Every Fortnight</option>
                                    <option value="monthly" {{ $routine->frequency == 'monthly' ? 'selected' : '' }}>Every Month</option>
                                    <option value="quarterly" {{ $routine->frequency == 'quarterly' ? 'selected' : '' }}>Every Quarter</option>
                                    <option value="yearly" {{ $routine->frequency == 'yearly' ? 'selected' : '' }}>Every Year</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <div class="position-relative w-50">
                                    <input type="number" class="form-control duration-hours pe-4 text-dark" name="routines[{{ $index }}][duration_hours]" value="{{ intdiv((int) round($routine->duration_minutes), 60) }}" placeholder="0" min="0" step="1" onkeypress="return event.charCode >= 48 && event.charCode <= 57" required oninput="calculateTotals()">
                                    <span class="position-absolute text-muted fw-bold" style="right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; font-size: 0.9rem;">h</span>
                                </div>
                                <div class="position-relative w-50">
                                    <input type="number" class="form-control duration-mins pe-4 text-dark" name="routines[{{ $index }}][duration_mins]" value="{{ ((int) round($routine->duration_minutes)) % 60 }}" placeholder="0" min="0" max="59" step="1" onkeypress="return event.charCode >= 48 && event.charCode <= 57" required oninput="if(this.value > 59) this.value = 59; calculateTotals()">
                                    <span class="position-absolute text-muted fw-bold" style="right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; font-size: 0.9rem;">m</span>
                                </div>
                            </div>
                            <div class="col-md-1 d-flex align-items-center">
                                <button type="button" class="btn btn-sm btn-danger btn-icon rounded-circle" onclick="removeRow(this)">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                    @if($routines->isEmpty())
                        <div class="row mb-2 routine-row" data-index="0">
                            <div class="col-md-4">
                                <input type="text" class="form-control text-dark" name="routines[0][title]" placeholder="Task description" required>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select frequency-select text-dark" name="routines[0][frequency]" required onchange="calculateTotals()">
                                    <option value="daily">Every Day</option>
                                    <option value="weekly">Every Week</option>
                                    <option value="fortnightly">Every Fortnight</option>
                                    <option value="monthly">Every Month</option>
                                    <option value="quarterly">Every Quarter</option>
                                    <option value="yearly">Every Year</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <div class="position-relative w-50">
                                    <input type="number" class="form-control duration-hours pe-4 text-dark" name="routines[0][duration_hours]" placeholder="0" min="0" step="1" onkeypress="return event.charCode >= 48 && event.charCode <= 57" required oninput="calculateTotals()">
                                    <span class="position-absolute text-muted fw-bold" style="right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; font-size: 0.9rem;">h</span>
                                </div>
                                <div class="position-relative w-50">
                                    <input type="number" class="form-control duration-mins pe-4 text-dark" name="routines[0][duration_mins]" placeholder="0" min="0" max="59" step="1" onkeypress="return event.charCode >= 48 && event.charCode <= 57" required oninput="if(this.value > 59) this.value = 59; calculateTotals()">
                                    <span class="position-absolute text-muted fw-bold" style="right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; font-size: 0.9rem;">m</span>
                                </div>
                            </div>
                            <div class="col-md-1 d-flex align-items-center">
                                <button type="button" class="btn btn-sm btn-danger btn-icon rounded-circle" onclick="removeRow(this)">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
                </div> <!-- /sv-field-group -->

                <div class="mt-4 p-4 rounded" style="background-color: #f8f9fa; border: 1px solid #e9ecef;">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-uppercase fw-bold text-muted" style="letter-spacing: 1px;">Daily Committed</span>
                        <div class="d-flex align-items-center">
                            <h4 class="mb-0 me-3 fw-bold text-dark" id="dailyTotalDisplay">0h 00m</h4>
                            <span class="badge bg-label-success rounded-pill px-3 py-1" id="headroomBadge">HEADROOM</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-4 py-2" style="font-weight: 700; border-radius: 8px;">
                        <i class="bx bx-save me-1"></i> Save Routines
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    let rowIndex = {{ $routines->count() > 0 ? $routines->count() : 1 }};

    function addRoutineRow() {
        const container = document.getElementById('routinesContainer');
        const row = document.createElement('div');
        row.className = 'row mb-2 routine-row';
        row.setAttribute('data-index', rowIndex);
        
        row.innerHTML = `
            <div class="col-md-4">
                <input type="text" class="form-control text-dark" name="routines[${rowIndex}][title]" placeholder="Task description" required>
            </div>
            <div class="col-md-3">
                <select class="form-select frequency-select text-dark" name="routines[${rowIndex}][frequency]" required onchange="calculateTotals()">
                    <option value="daily">Every Day</option>
                    <option value="weekly">Every Week</option>
                    <option value="fortnightly">Every Fortnight</option>
                    <option value="monthly">Every Month</option>
                    <option value="quarterly">Every Quarter</option>
                    <option value="yearly">Every Year</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <div class="position-relative w-50">
                    <input type="number" class="form-control duration-hours pe-4 text-dark" name="routines[${rowIndex}][duration_hours]" placeholder="0" min="0" step="1" onkeypress="return event.charCode >= 48 && event.charCode <= 57" required oninput="calculateTotals()">
                    <span class="position-absolute text-muted fw-bold" style="right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; font-size: 0.9rem;">h</span>
                </div>
                <div class="position-relative w-50">
                    <input type="number" class="form-control duration-mins pe-4 text-dark" name="routines[${rowIndex}][duration_mins]" placeholder="0" min="0" max="59" step="1" onkeypress="return event.charCode >= 48 && event.charCode <= 57" required oninput="if(this.value > 59) this.value = 59; calculateTotals()">
                    <span class="position-absolute text-muted fw-bold" style="right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; font-size: 0.9rem;">m</span>
                </div>
            </div>
            <div class="col-md-1 d-flex align-items-center">
                <button type="button" class="btn btn-sm btn-danger btn-icon rounded-circle" onclick="removeRow(this)">
                    <i class="bx bx-trash"></i>
                </button>
            </div>
        `;
        
        container.appendChild(row);
        rowIndex++;
        calculateTotals();
    }

    function removeRow(btn) {
        btn.closest('.routine-row').remove();
        calculateTotals();
    }

    function calculateTotals() {
        let totalMinutes = 0;
        const rows = document.querySelectorAll('.routine-row');
        
        rows.forEach(row => {
            const frequency = row.querySelector('.frequency-select').value;
            const hoursVal = row.querySelector('.duration-hours').value;
            const minsVal = row.querySelector('.duration-mins').value;
            const minutes = (parseInt(hoursVal) || 0) * 60 + (parseInt(minsVal) || 0);
            
            let dailyEquivalent = 0;
            if (frequency === 'daily') {
                dailyEquivalent = minutes;
            } else if (frequency === 'weekly') {
                dailyEquivalent = minutes / 6;
            } else if (frequency === 'fortnightly') {
                dailyEquivalent = minutes / 13;
            } else if (frequency === 'monthly') {
                dailyEquivalent = minutes / 26;
            } else if (frequency === 'quarterly') {
                dailyEquivalent = minutes / 78;
            } else if (frequency === 'yearly') {
                dailyEquivalent = minutes / 312;
            }
            totalMinutes += dailyEquivalent;
        });
        
        const totalMinsRounded = Math.round(totalMinutes);
        const hours = Math.floor(totalMinsRounded / 60);
        const mins = totalMinsRounded % 60;
        
        document.getElementById('dailyTotalDisplay').textContent = `${hours}h ${mins}m`;
        
        const badge = document.getElementById('headroomBadge');
        if (totalMinsRounded > 480) { // 8 hours
            badge.className = 'badge bg-label-danger rounded-pill px-3 py-1';
            badge.textContent = 'OVER SHIFT';
        } else if (totalMinsRounded >= 420) { // 7 hours
            badge.className = 'badge bg-label-warning rounded-pill px-3 py-1';
            badge.textContent = 'NEAR LIMIT';
        } else {
            badge.className = 'badge bg-label-success rounded-pill px-3 py-1';
            badge.textContent = 'HEADROOM';
        }
    }

    document.addEventListener('DOMContentLoaded', calculateTotals);
</script>
@endsection
