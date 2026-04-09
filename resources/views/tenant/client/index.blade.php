@extends('layouts/layoutMaster')

@section('title', __('Clients'))

<!-- Vendor Styles -->
@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
@endsection

@section('content')
  <div class="hitech-card animate__animated animate__fadeInUp">
    <div class="hitech-card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0 text-white">@lang('Client Management')</h5>
      <a href="{{ route('client.create') }}" class="btn btn-primary btn-hitech-glow">
        <i class="bx bx-plus bx-sm me-0 me-sm-2"></i> Create new
      </a>
    </div>
    
    <div class="card-datatable table-responsive">
      <table id="datatable" class="table table-borderless">
        <thead>
        <tr>
          <th>Sl.No</th>
          <th>Name</th>
          <th>Phone Number</th>
          <th>Email</th>
          <th>Address</th>
          <th>City</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($clients as $client)
          <tr>
            <td class="ps-2">
              {{ $loop->iteration }}
            </td>
            <td><span class="fw-bold text-white">{{ $client->name }}</span></td>
            <td>{{ $client->phone }}</td>
            <td>{{ $client->email }}</td>
            <td>{{ $client->address }}</td>
            <td>{{ $client->city ?? 'N/A' }}</td>
            <td>
              <div class="d-flex justify-content-left">
                <label class="switch switch-success mb-0">
                  <input
                    type="checkbox"
                    {{$client->status == 'active' ? 'checked' : ''}}
                    onchange="changeStatus({{ $client->id }})"
                    class="switch-input status-toggle"/>
                  <span class="switch-toggle-slider">
            <span class="switch-on"><i class="bx bx-check"></i></span>
            <span class="switch-off"><i class="bx bx-x"></i></span>
            </span>
                </label>
              </div>
            </td>
            <td>
              <div class="d-flex align-items-center">
                <a href="{{ route('client.show', $client->id) }}" class="btn btn-sm btn-icon btn-label-info me-1">
                  <i class="bx bx-show"></i>
                </a>
                <a href="{{ route('client.edit', $client->id) }}" class="btn btn-icon btn-sm btn-label-warning me-2">
                  <i class="bx bx-pencil"></i>
                </a>
                <form action="{{ route('client.destroy', $client->id) }}" method="POST" class="d-inline">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-icon btn-label-danger"
                          onclick="return confirm('Are you sure you want to delete this client?')">
                    <i class="bx bx-trash"></i>
                  </button>
                </form>
              </div>
            </td>

          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection

@section('page-script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  <script>

    $(function () {
      $('#datatable').dataTable({
         "language": {
            "search": "",
            "searchPlaceholder": "Search clients...",
            "lengthMenu": "_MENU_",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
            "paginate": {
                "next": '<i class="bx bx-chevron-right"></i>',
                "previous": '<i class="bx bx-chevron-left"></i>'
            }
        },
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        "buttons": []
      });
    });

    function changeStatus(id) {
      $.ajax({
        'csrf-token': '{{csrf_token()}}',
        url: "{{route('client.changeStatus')}}",
        type: 'POST',
        dataType: 'json',
        data: {
          id: id,
          _token: "{{ csrf_token() }}"
        },
        success: function (data) {
          console.log(data);
          showSuccessToast(data);
        },
        error: function (data) {
          console.log(data);
        }
      });
    }
    
    function showSuccessToast(message) {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: message.message || 'Updated successfully',
        showConfirmButton: false,
        timer: 3000,
        customClass: {
          popup: 'colored-toast'
        }
      });
    }
  </script>
@endsection
