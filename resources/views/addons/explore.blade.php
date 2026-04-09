@extends('layouts/layoutMaster')

@section('title', 'Addons - Explore')

@section('content')
  <div class="container">
    <div class="row mb-4 align-items-center">
      <div class="col-md-6">
        <h4 class="fw-bold">Explore Addons</h4>
      </div>
      <div class="col-md-6 text-end">
        <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#uploadSection">
          <i class="bx bx-plus me-2"></i> Add New Addon
        </button>
        <a class="btn btn-secondary" href="{{ route('addons.explore') }}">
          <i class="bx bx-compass me-2"></i> Explore
        </a>
      </div>
    </div>

    {{-- Addons Grid --}}
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
      @foreach($addonsInfo as $addon)
        <div class="col">
          <div class="card shadow-lg border-0 rounded-4 h-100">
            {{-- Addon Image --}}
            <div class="position-relative">
              <img src="https://placehold.co/600x400" alt="Addon Image" class="card-img-top rounded-top-4"
                   style="height: 180px; object-fit: cover;">
            </div>

            {{-- Addon Header --}}
            <div class="card-body">
              <h5 class="fw-bold text-primary">{{ $addon->name }}</h5>
              <span class="text-muted small">{{ $addon->code }}</span>

              {{-- Description --}}
              <p class="text-muted mt-3">{{ \Illuminate\Support\Str::limit($addon->description, 80) }}</p>

              {{-- Addon Details --}}
              <ul class="list-unstyled mt-3">
                <li><i class="bi bi-currency-dollar"></i> <strong>Price:</strong> ${{ number_format($addon->price, 2) }}
                </li>
                <li><i class="bi bi-clock-history"></i> <strong>Version:</strong> {{ $addon->currentVersion }}</li>
                <li><i class="bi bi-check-circle-fill"></i> <strong>Status:</strong>
                  @if($addon->status === 'active')
                    <span class="badge bg-success">Active</span>
                  @else
                    <span class="badge bg-danger">Inactive</span>
                  @endif
                </li>
              </ul>
            </div>

            {{-- Conditional Footer --}}
            <div class="card-footer bg-transparent border-0 d-flex justify-content-between align-items-center">
              @if(false)
                {{-- If Addon is already installed --}}
                <span class="badge bg-info">Already Installed</span>
              @else
                {{-- If Addon is not installed --}}
                <a href="{{ $addon->purchaseLink }}" target="_blank" class="btn btn-outline-primary">
                  <i class="bx bx-cart me-1"></i> Purchase Now
                </a>
                <a href="#" class="btn btn-primary">
                  <i class="bx bx-download me-1"></i> Install Now
                </a>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
@endsection
