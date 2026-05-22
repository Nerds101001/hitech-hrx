@extends('layouts.layoutMaster')

@section('title', 'Book a Sales Visit')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/libs/flatpickr/flatpickr.css') }}">
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

  /* ===================== Visit Type Pills ===================== */
  .visit-type-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.8rem;
  }
  @media(min-width:768px) {
    .visit-type-grid { grid-template-columns: repeat(4, 1fr); }
  }
  .visit-type-option { display:none; }
  .visit-type-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.1rem 0.8rem;
    border: 2px solid #e3e6ef;
    border-radius: 14px;
    cursor: pointer;
    text-align: center;
    transition: all 0.22s ease;
    background: #fff;
    gap: 0.5rem;
  }
  .visit-type-label .type-icon {
    font-size: 1.9rem;
    line-height: 1;
  }
  .visit-type-label .type-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: #5a5f71;
    line-height: 1.2;
  }
  .visit-type-option:checked + .visit-type-label {
    border-color: #0d7377;
    background: linear-gradient(135deg, #edf9f9, #f0fefe);
    box-shadow: 0 4px 18px rgba(13,115,119,0.18);
  }
  .visit-type-option:checked + .visit-type-label .type-name {
    color: #0d7377;
  }
  .visit-type-label:hover {
    border-color: #14a085;
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(13,115,119,0.12);
  }

  /* ===================== Inputs ===================== */
  .sv-form-card .form-control,
  .sv-form-card .form-select,
  .sv-form-card .select2-selection {
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

  /* ===================== Add Client Btn ===================== */
  .btn-add-client-inline {
    background: transparent;
    color: #0d7377;
    border: 1.5px dashed #0d7377;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 0.3rem 0.8rem;
    transition: all 0.2s ease;
    text-decoration: none;
  }
  .btn-add-client-inline:hover {
    background: #0d7377;
    color: #fff;
  }

  /* ===================== Submit Button ===================== */
  .btn-book-visit {
    background: linear-gradient(135deg, #0d7377, #14a085);
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 0.85rem 2.5rem;
    font-size: 1rem;
    font-weight: 800;
    letter-spacing: 0.3px;
    box-shadow: 0 6px 22px rgba(13,115,119,0.30);
    transition: all 0.22s ease;
  }
  .btn-book-visit:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(13,115,119,0.40);
    color: #fff;
  }

  /* ===================== Modal ===================== */
  .sv-modal .modal-content {
    border-radius: 18px;
    border: none;
    box-shadow: 0 20px 60px rgba(0,0,0,0.18);
  }
  .sv-modal .modal-header {
    background: linear-gradient(135deg, #0d7377, #14a085);
    color: #fff;
    border-radius: 18px 18px 0 0;
    border-bottom: none;
    padding: 1.2rem 1.6rem;
  }
  .sv-modal .modal-header .btn-close { filter: invert(1) brightness(2); }
  .sv-modal .modal-title { font-weight: 800; font-size: 1rem; }
  .sv-modal .form-label { font-size: 0.82rem; font-weight: 700; color: #3d4056; }
  .sv-modal .form-control, .sv-modal .form-select {
    border-radius: 9px;
    border-color: #d8dce8;
    font-size: 0.88rem;
  }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Validation Errors --}}
  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" style="border-radius:12px;border-left:5px solid #dc3545;" role="alert">
      <i class="bx bx-error-circle me-2"></i>
      <strong>Please fix the following errors:</strong>
      <ul class="mb-0 mt-1 ps-3">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- ===== Hero Header ===== --}}
  <div class="sv-hero mb-4" style="position:relative;">
    <div style="position:relative;z-index:2;">
      <div class="d-flex align-items-center gap-3 mb-1">
        <a href="{{ route('sales-visits.index') }}" class="text-white opacity-75" style="text-decoration:none;font-size:0.9rem;">
          <i class="bx bx-arrow-back me-1"></i> Back to Visits
        </a>
      </div>
      <h1 class="sv-hero-title">📋 Book a Client Visit or Trial</h1>
      <p class="sv-hero-subtitle">Fill in the details below to schedule a new field visit or product trial.</p>
    </div>
  </div>

  {{-- ===== Booking Form ===== --}}
  <div class="row justify-content-center">
    <div class="col-12 col-xl-10">
      <div class="card sv-form-card">
        <div class="card-body p-4">
          <form method="POST" action="{{ route('sales-visits.store') }}" id="bookingForm">
            @csrf

            {{-- CLIENT --}}
            <div class="sv-section-title"><span>👤</span> Client Information</div>
            <div class="sv-field-group">
              <div class="row g-3">
                <div class="col-12">
                  <label for="client_id" class="form-label">Select Client <span class="text-danger">*</span></label>
                  <select name="client_id" id="client_id" class="form-select select2-client" required>
                    <option value="">— Search & select a client —</option>
                    @foreach($clients as $client)
                      <option value="{{ $client->id }}"
                        {{ old('client_id') == $client->id ? 'selected' : '' }}>
                        {{ $client->name }}@if($client->city) – {{ $client->city }}@endif
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="col-12">
                  <button type="button" class="btn-add-client-inline" data-bs-toggle="modal" data-bs-target="#addClientModal">
                    <i class="bx bx-plus me-1"></i> Add New Client
                  </button>
                </div>
              </div>
            </div>

            {{-- SALESPERSON --}}
            <div class="sv-section-title"><span>👔</span> Assigned Salesperson</div>
            <div class="sv-field-group">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="salesperson_id" class="form-label">Salesperson <span class="text-danger">*</span></label>
                  <select name="salesperson_id" id="salesperson_id" class="form-select select2-salesperson" required>
                    <option value="">— Select salesperson —</option>
                    @foreach($salespersons as $sp)
                      <option value="{{ $sp->id }}"
                        {{ old('salesperson_id') == $sp->id ? 'selected' : '' }}>
                        {{ $sp->name }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>

            {{-- VISIT TYPE --}}
            <div class="sv-section-title"><span>🗂️</span> Visit Type</div>
            <div class="sv-field-group">
              <div class="visit-type-grid">
                @php
                  $visitTypes = [
                    ['value'=>'client_visit',     'icon'=>'🤝', 'name'=>'Client Visit'],
                    ['value'=>'product_trial',    'icon'=>'🧪', 'name'=>'Product Trial'],
                    ['value'=>'order_collection', 'icon'=>'📦', 'name'=>'Order Collection'],
                    ['value'=>'service_call',     'icon'=>'🔧', 'name'=>'Service Call'],
                  ];
                @endphp
                @foreach($visitTypes as $vt)
                  <div>
                    <input type="radio" name="visit_type" id="vt_{{ $vt['value'] }}"
                           value="{{ $vt['value'] }}" class="visit-type-option"
                           {{ old('visit_type', 'client_visit') == $vt['value'] ? 'checked' : '' }} required>
                    <label for="vt_{{ $vt['value'] }}" class="visit-type-label">
                      <span class="type-icon">{{ $vt['icon'] }}</span>
                      <span class="type-name">{{ $vt['name'] }}</span>
                    </label>
                  </div>
                @endforeach
              </div>
            </div>

            {{-- SCHEDULE & NOTES --}}
            <div class="sv-section-title"><span>📅</span> Schedule & Notes</div>
            <div class="sv-field-group">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="scheduled_at" class="form-label">Scheduled Date & Time <span class="text-danger">*</span></label>
                  <input type="datetime-local" name="scheduled_at" id="scheduled_at"
                         class="form-control @error('scheduled_at') is-invalid @enderror"
                         value="{{ old('scheduled_at') }}" required>
                  @error('scheduled_at')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                <div class="col-12">
                  <label for="notes" class="form-label">Notes / Instructions</label>
                  <textarea name="notes" id="notes" rows="3"
                            class="form-control @error('notes') is-invalid @enderror"
                            placeholder="Any special notes or instructions for this visit…">{{ old('notes') }}</textarea>
                  @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>
            </div>

            {{-- Submit --}}
            <div class="d-flex justify-content-between align-items-center mt-2">
              <a href="{{ route('sales-visits.index') }}" class="btn btn-outline-secondary" style="border-radius:10px;">
                <i class="bx bx-arrow-back me-1"></i> Cancel
              </a>
              <button type="submit" class="btn btn-book-visit">
                <i class="bx bx-calendar-check me-2"></i> Book Visit & Send Confirmation
              </button>
            </div>

          </form>
        </div>
      </div>
    </div>
  </div>

</div>

{{-- ===== Add New Client Modal ===== --}}
<div class="modal fade sv-modal" id="addClientModal" tabindex="-1" aria-labelledby="addClientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addClientModalLabel">
          <i class="bx bx-user-plus me-2"></i> Add New Client
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div id="clientModalAlert"></div>
        <form id="addClientForm">
          @csrf
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Client / Company Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="nc_name" class="form-control" required placeholder="e.g. ABC Enterprises">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" id="nc_phone" class="form-control" placeholder="+91 98765 43210">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" id="nc_email" class="form-control" placeholder="client@example.com">
            </div>
            <div class="col-md-6">
              <label class="form-label">City</label>
              <input type="text" name="city" id="nc_city" class="form-control" placeholder="e.g. Mumbai">
            </div>
            <div class="col-12">
              <label class="form-label">Address</label>
              <textarea name="address" id="nc_address" rows="2" class="form-control" placeholder="Full address…"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">GST Number</label>
              <input type="text" name="gst_number" id="nc_gst" class="form-control" placeholder="e.g. 27AAAAA0000A1Z5"
                     style="text-transform:uppercase;">
            </div>
            <div class="col-md-6">
              <label class="form-label">Contact Person</label>
              <input type="text" name="contact_person" id="nc_contact_person" class="form-control" placeholder="e.g. Mr. Ramesh Sharma">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:9px;">
          Cancel
        </button>
        <button type="button" id="saveClientBtn" class="btn" style="background:#0d7377;color:#fff;border-radius:9px;font-weight:700;">
          <i class="bx bx-save me-1"></i> Save Client
        </button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('vendor-script')
<script src="{{ asset('vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-script')
<script>
$(function() {
  // Initialize Select2
  $('.select2-client').select2({
    placeholder: '— Search & select a client —',
    allowClear: true,
    dropdownParent: $('#bookingForm')
  });
  $('.select2-salesperson').select2({
    placeholder: '— Select salesperson —',
    allowClear: true,
    dropdownParent: $('#bookingForm')
  });

  // ===== AJAX Save New Client =====
  $('#saveClientBtn').on('click', function() {
    const $btn = $(this);
    const originalHtml = $btn.html();

    // Basic validation
    const name = $('#nc_name').val().trim();
    if (!name) {
      $('#clientModalAlert').html('<div class="alert alert-danger py-2 px-3" style="border-radius:9px;font-size:0.88rem;"><i class="bx bx-error-circle me-1"></i> Client name is required.</div>');
      return;
    }

    $btn.html('<span class="spinner-border spinner-border-sm me-1"></span> Saving…').prop('disabled', true);
    $('#clientModalAlert').html('');

    $.ajax({
      url: '{{ route('sales-visits.clients.store') }}',
      method: 'POST',
      data: {
        _token: $('input[name="_token"]').first().val(),
        name:           $('#nc_name').val(),
        phone:          $('#nc_phone').val(),
        email:          $('#nc_email').val(),
        city:           $('#nc_city').val(),
        address:        $('#nc_address').val(),
        gst_number:     $('#nc_gst').val(),
        contact_person: $('#nc_contact_person').val(),
      },
      success: function(response) {
        if (response.success) {
          // Append new option to client dropdown and select it
          const newOption = new Option(
            response.client.name + (response.client.city ? ' – ' + response.client.city : ''),
            response.client.id, true, true
          );
          $('.select2-client').append(newOption).trigger('change');

          // Reset modal & close
          $('#addClientForm')[0].reset();
          $('#clientModalAlert').html('');
          bootstrap.Modal.getInstance(document.getElementById('addClientModal')).hide();
        } else {
          $('#clientModalAlert').html('<div class="alert alert-danger py-2 px-3" style="border-radius:9px;font-size:0.88rem;">' + (response.message || 'An error occurred.') + '</div>');
        }
      },
      error: function(xhr) {
        let msg = 'Failed to save client.';
        if (xhr.responseJSON && xhr.responseJSON.errors) {
          const errs = Object.values(xhr.responseJSON.errors).flat();
          msg = errs.join('<br>');
        }
        $('#clientModalAlert').html('<div class="alert alert-danger py-2 px-3" style="border-radius:9px;font-size:0.88rem;"><i class="bx bx-error-circle me-1"></i> ' + msg + '</div>');
      },
      complete: function() {
        $btn.html(originalHtml).prop('disabled', false);
      }
    });
  });

  // Clear modal on close
  document.getElementById('addClientModal').addEventListener('hidden.bs.modal', function() {
    $('#addClientForm')[0].reset();
    $('#clientModalAlert').html('');
  });
});
</script>
@endsection
