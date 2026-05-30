@extends('layouts.layoutMaster')

@section('title', 'Sales Clients')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
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
    width:220px; height:220px; background:rgba(255,255,255,0.07); border-radius:50%;
  }
  .sv-hero::after {
    content:''; position:absolute; bottom:-70px; left:-30px;
    width:180px; height:180px; background:rgba(255,255,255,0.05); border-radius:50%;
  }
  .sv-hero-title { font-size:1.75rem; font-weight:800; color:#fff; margin-bottom:0.25rem; }
  .sv-hero-subtitle { color:rgba(255,255,255,0.80); font-size:0.95rem; margin-bottom:0; }
  .btn-hero-action {
    background: #fff;
    color: #0d7377;
    border: none;
    font-weight: 700;
    border-radius: 10px;
    padding: 0.65rem 1.4rem;
    font-size: 0.92rem;
    box-shadow: 0 4px 18px rgba(0,0,0,0.12);
    transition: all 0.22s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
  }
  .btn-hero-action:hover {
    background: #0d7377;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 6px 22px rgba(13,115,119,0.35);
  }

  /* ===================== Table Card ===================== */
  .sv-clients-card {
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 4px 28px rgba(0,0,0,0.08);
    border: none;
  }
  .sv-clients-card .card-header {
    background: linear-gradient(90deg, #f8fffe, #edf9f9);
    border-bottom: 2px solid #c7eaea;
    padding: 1rem 1.6rem;
    font-weight: 700;
    color: #0d7377;
    font-size: 0.95rem;
  }

  /* DataTable Overrides */
  #clientsTable_wrapper .dataTables_filter input {
    border-radius: 9px;
    border: 1.5px solid #d8dce8;
    padding: 0.4rem 0.8rem;
    font-size: 0.88rem;
  }
  #clientsTable_wrapper .dataTables_filter input:focus {
    border-color: #0d7377;
    outline: none;
    box-shadow: 0 0 0 3px rgba(13,115,119,0.12);
  }
  #clientsTable_wrapper .dataTables_length select {
    border-radius: 9px;
    border: 1.5px solid #d8dce8;
    padding: 0.3rem 0.6rem;
  }
  #clientsTable_wrapper .page-link {
    border-radius: 8px;
    margin: 0 2px;
    color: #0d7377;
  }
  #clientsTable_wrapper .page-item.active .page-link {
    background: #0d7377;
    border-color: #0d7377;
    color: #fff;
  }

  table#clientsTable thead th {
    background: #f3fffe;
    color: #0d7377;
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #c7eaea !important;
    padding: 0.9rem 1rem;
    white-space: nowrap;
  }
  table#clientsTable tbody tr {
    transition: background 0.15s ease;
  }
  table#clientsTable tbody tr:hover {
    background: #f0fffe;
  }
  table#clientsTable td {
    padding: 0.85rem 1rem;
    vertical-align: middle;
    font-size: 0.88rem;
    color: #3d3d3d;
    border-bottom: 1px solid #f0f2f5;
  }
  .cl-name { font-weight:700; color:#1a1a2e; display:block; font-size:0.9rem; }
  .cl-sub  { font-size:0.78rem; color:#8a90a2; }
  .cl-badge-visits {
    display: inline-block;
    background: #edf9f9;
    color: #0d7377;
    border-radius: 20px;
    padding: 0.2em 0.75em;
    font-size: 0.8rem;
    font-weight: 700;
  }
  .btn-del {
    background: #fff2f2;
    color: #d63031;
    border: 1px solid #f5c6c6;
    border-radius: 8px;
    padding: 0.3rem 0.7rem;
    font-size: 0.8rem;
    transition: all 0.2s ease;
    cursor: pointer;
  }
  .btn-del:hover {
    background: #d63031;
    color: #fff;
    border-color: #d63031;
  }

  /* ===================== Alerts ===================== */
  .sv-alert {
    border-radius: 12px;
    border-left: 5px solid;
    padding: 0.9rem 1.2rem;
    margin-bottom: 1.2rem;
    font-size: 0.9rem;
    font-weight: 500;
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
  .sv-modal .form-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #3d4056;
  }
  .sv-modal .form-control, .sv-modal .form-select {
    border-radius: 9px;
    border-color: #d8dce8;
    font-size: 0.88rem;
  }
  .sv-modal .form-control:focus {
    border-color: #0d7377;
    box-shadow: 0 0 0 3px rgba(13,115,119,0.12);
  }
  .btn-save-modal {
    background: linear-gradient(135deg, #0d7377, #14a085);
    color: #fff;
    border: none;
    border-radius: 9px;
    font-weight: 700;
    padding: 0.55rem 1.4rem;
    transition: all 0.2s ease;
  }
  .btn-save-modal:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(13,115,119,0.30);
    color: #fff;
  }

  /* ===================== Empty State ===================== */
  .sv-empty {
    padding: 4rem 2rem;
    text-align: center;
    color: #aab0be;
  }
  .sv-empty .empty-icon { font-size:3.5rem; margin-bottom:1rem; opacity:0.5; }
  .sv-empty p { font-size:0.95rem; font-weight:500; }
</style>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">

  {{-- Flash Messages --}}
  @if(session('success'))
    <div class="sv-alert alert alert-success alert-dismissible fade show" role="alert">
      <i class="bx bx-check-circle me-2"></i> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="sv-alert alert alert-danger alert-dismissible fade show" role="alert">
      <i class="bx bx-error-circle me-2"></i> {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- ===== Hero ===== --}}
  <div class="sv-hero mb-4">
    <div class="d-flex justify-content-between align-items-center" style="position:relative;z-index:2;">
      <div>
        <h1 class="sv-hero-title">🏢 Client Master</h1>
        <p class="sv-hero-subtitle">Manage all customers for field visits and trials.</p>
      </div>
      <div class="d-flex gap-2">
        <a href="{{ route('sales-visits.index') }}" class="btn-hero-action" style="background:rgba(255,255,255,0.18);color:#fff;border:1.5px solid rgba(255,255,255,0.35);">
          <i class="bx bx-arrow-back"></i> Back to Visits
        </a>
        <button type="button" class="btn-hero-action" data-bs-toggle="modal" data-bs-target="#addClientModal">
          <i class="bx bx-plus"></i> Add Client
        </button>
      </div>
    </div>
  </div>

  {{-- AJAX Response Alert --}}
  <div id="pageAlert"></div>

  {{-- ===== Clients Table Card ===== --}}
  <div class="card sv-clients-card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bx bx-group me-2"></i>All Clients
        <span class="badge ms-2" style="background:#0d7377;font-size:0.78rem;">{{ count($clients) }}</span>
      </span>
    </div>
    <div class="card-body p-0">
      @if(count($clients) > 0)
        <div class="table-responsive">
          <table id="clientsTable" class="table mb-0" style="width:100%;">
            <thead>
              <tr>
                <th>#</th>
                <th>Name / Contact</th>
                <th>Phone & Email</th>
                <th>City</th>
                <th>GST Number</th>
                <th class="text-center">Total Visits</th>
                <th class="text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($clients as $i => $client)
              <tr>
                <td class="text-muted" style="font-size:0.8rem;">{{ $i + 1 }}</td>
                <td>
                  <span class="cl-name">{{ $client->name }}</span>
                  @if($client->contact_person)
                    <span class="cl-sub"><i class="bx bx-user" style="font-size:0.74rem;"></i> {{ $client->contact_person }}</span>
                  @endif
                </td>
                <td>
                  @if($client->phone)
                    <span style="font-weight:600;display:block;">{{ $client->phone }}</span>
                  @endif
                  @if($client->email)
                    <span class="cl-sub">{{ $client->email }}</span>
                  @endif
                  @if(!$client->phone && !$client->email)
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  @if($client->city)
                    <span><i class="bx bx-map-pin" style="color:#0d7377;"></i> {{ $client->city }}</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  @if($client->gst_number)
                    <code style="font-size:0.8rem;background:#f0f5f5;padding:0.2em 0.5em;border-radius:5px;">
                      {{ $client->gst_number }}
                    </code>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td class="text-center">
                  <span class="cl-badge-visits">
                    {{ $client->sales_visits_count ?? $client->salesVisits()->count() }}
                  </span>
                </td>
                <td class="text-center">
                  <form method="POST"
                        action="{{ route('sales-visits.clients.destroy', $client->id) }}"
                        onsubmit="return confirm('Delete {{ addslashes($client->name) }}? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-del" title="Delete Client">
                      <i class="bx bx-trash"></i> Delete
                    </button>
                  </form>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="sv-empty">
          <div class="empty-icon">🏢</div>
          <p>No clients added yet.</p>
          <button type="button" class="btn btn-sm mt-2" style="background:#0d7377;color:#fff;border-radius:9px;"
                  data-bs-toggle="modal" data-bs-target="#addClientModal">
            <i class="bx bx-plus me-1"></i> Add Your First Client
          </button>
        </div>
      @endif
    </div>
  </div>

</div>

{{-- ===== Add Client Modal ===== --}}
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
        <div id="modalAlert"></div>
        <form id="addClientForm" method="POST" action="{{ route('sales-visits.clients.store') }}">
          @csrf
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Client / Company Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="ac_name" class="form-control" required
                     placeholder="e.g. ABC Enterprises">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" id="ac_phone" class="form-control"
                     placeholder="+91 98765 43210">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" id="ac_email" class="form-control"
                     placeholder="client@example.com">
            </div>
            <div class="col-md-6">
              <label class="form-label">City</label>
              <input type="text" name="city" id="ac_city" class="form-control"
                     placeholder="e.g. Mumbai">
            </div>
            <div class="col-12">
              <label class="form-label">Address</label>
              <textarea name="address" id="ac_address" rows="2" class="form-control"
                        placeholder="Full address…"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">GST Number</label>
              <input type="text" name="gst_number" id="ac_gst" class="form-control"
                     placeholder="e.g. 27AAAAA0000A1Z5" style="text-transform:uppercase;">
            </div>
            <div class="col-md-6">
              <label class="form-label">Contact Person</label>
              <input type="text" name="contact_person" id="ac_contact_person" class="form-control"
                     placeholder="e.g. Mr. Ramesh Sharma">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"
                style="border-radius:9px;">
          Cancel
        </button>
        <button type="button" id="saveClientBtn" class="btn btn-save-modal">
          <i class="bx bx-save me-1"></i> Save Client
        </button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('vendor-script')
<script src="{{ asset('vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('page-script')
<script>
$(function () {
  // ===== Init DataTable =====
  @if(count($clients) > 0)
  const table = $('#clientsTable').DataTable({
    responsive: true,
    dom: '<"d-flex justify-content-between align-items-center px-3 py-2"lf>t<"d-flex justify-content-between px-3 py-2"ip>',
    language: {
      search: '',
      searchPlaceholder: '🔍  Search clients…',
      lengthMenu: 'Show _MENU_',
    },
    columnDefs: [
      { orderable: false, targets: [6] }
    ],
    pageLength: 15,
  });
  @endif

  // ===== Auto dismiss alerts =====
  document.querySelectorAll('.sv-alert').forEach(el => {
    setTimeout(() => {
      const bsAlert = new bootstrap.Alert(el);
      bsAlert.close();
    }, 4000);
  });

  // ===== AJAX Save Client =====
  $('#saveClientBtn').on('click', function () {
    const $btn = $(this);
    const originalHtml = $btn.html();
    const name = $('#ac_name').val().trim();

    if (!name) {
      $('#modalAlert').html('<div class="alert alert-danger py-2 px-3" style="border-radius:9px;font-size:0.88rem;"><i class="bx bx-error-circle me-1"></i> Client name is required.</div>');
      return;
    }

    $btn.html('<span class="spinner-border spinner-border-sm me-1"></span> Saving…').prop('disabled', true);
    $('#modalAlert').html('');

    $.ajax({
      url: '{{ route('sales-visits.clients.store') }}',
      method: 'POST',
      data: {
        _token:         $('[name="_token"]').first().val(),
        name:           $('#ac_name').val(),
        phone:          $('#ac_phone').val(),
        email:          $('#ac_email').val(),
        city:           $('#ac_city').val(),
        address:        $('#ac_address').val(),
        gst_number:     $('#ac_gst').val(),
        contact_person: $('#ac_contact_person').val(),
      },
      success: function (response) {
        if (response.success) {
          // Close modal
          bootstrap.Modal.getInstance(document.getElementById('addClientModal')).hide();
          // Show page success
          $('#pageAlert').html('<div class="sv-alert alert alert-success alert-dismissible fade show" role="alert"><i class="bx bx-check-circle me-2"></i> Client <strong>' + response.client.name + '</strong> added successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
          // Reload page to refresh table
          setTimeout(() => location.reload(), 1200);
        } else {
          $('#modalAlert').html('<div class="alert alert-danger py-2 px-3" style="border-radius:9px;font-size:0.88rem;">' + (response.message || 'An error occurred.') + '</div>');
        }
      },
      error: function (xhr) {
        let msg = 'Failed to save client.';
        if (xhr.responseJSON && xhr.responseJSON.errors) {
          msg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
        }
        $('#modalAlert').html('<div class="alert alert-danger py-2 px-3" style="border-radius:9px;font-size:0.88rem;"><i class="bx bx-error-circle me-1"></i> ' + msg + '</div>');
      },
      complete: function () {
        $btn.html(originalHtml).prop('disabled', false);
      }
    });
  });

  // Clear modal on close
  document.getElementById('addClientModal').addEventListener('hidden.bs.modal', function () {
    $('#addClientForm')[0].reset();
    $('#modalAlert').html('');
  });
});
</script>
@endsection
