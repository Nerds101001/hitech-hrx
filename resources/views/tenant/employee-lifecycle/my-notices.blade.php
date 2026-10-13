@extends('layouts/layoutMaster')

@section('title', __('My Warnings & Complaints'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@php
  $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '—';

  $pill = function ($status) {
    $s = strtolower((string) $status);
    $cls = match (true) {
      in_array($s, ['resolved', 'closed', 'dismissed']) => 'bg-label-success',
      in_array($s, ['active', 'open', 'investigating'])  => 'bg-label-warning',
      default                                            => 'badge-brand-soft',
    };
    return '<span class="badge ' . $cls . ' rounded-pill">'
         . e($s ? ucwords(str_replace('_', ' ', $s)) : '—') . '</span>';
  };
@endphp

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  <div class="px-4">

    <x-hero-banner
      title="My Warnings & Complaints"
      subtitle="Records issued to you by HR/Admin — visible only to you"
      icon="bx-error-circle"
      breadcrumb="My Warnings & Complaints"
    />

    <div class="hitech-card-white overflow-hidden mb-6">
      <div class="card-body p-0">
        <div class="table-toolbar">
          <div class="d-flex align-items-center gap-2">
            <i class="bx bx-error"></i>
            <span class="fw-bold">Warnings</span>
            <span class="badge badge-brand-soft rounded-pill ms-1">{{ $myWarnings->count() }}</span>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr>
              <th>Type</th><th>Severity</th><th>Date</th><th>Reason</th><th>Status</th><th>Attachment</th>
            </tr></thead>
            <tbody>
            @forelse($myWarnings as $w)
              <tr>
                <td class="text-capitalize">{{ str_replace('_', ' ', $w->warning_type ?? '—') }}</td>
                <td class="text-capitalize">{{ $w->severity ?? '—' }}</td>
                <td>{{ $fmt($w->warning_date ?? $w->created_at) }}</td>
                <td><small class="text-muted">{{ \Illuminate\Support\Str::limit($w->description ?: '—', 60) }}</small></td>
                <td>{!! $pill($w->status ?? null) !!}</td>
                <td>
                  @if($w->attachment_path)
                    <a href="{{ route('employee-lifecycle.warnings.attachment', $w->id) }}" target="_blank" class="btn btn-sm btn-hitech-modal-cancel">
                      <i class="bx bx-paperclip"></i>
                    </a>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-5">No warnings on record</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="hitech-card-white overflow-hidden">
      <div class="card-body p-0">
        <div class="table-toolbar">
          <div class="d-flex align-items-center gap-2">
            <i class="bx bx-message-square-dots"></i>
            <span class="fw-bold">Complaints</span>
            <span class="badge badge-brand-soft rounded-pill ms-1">{{ $myComplaints->count() }}</span>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr>
              <th>Type</th><th>Severity</th><th>Date</th><th>Details</th><th>Status</th><th>Attachment</th>
            </tr></thead>
            <tbody>
            @forelse($myComplaints as $c)
              <tr>
                <td class="text-capitalize">{{ str_replace('_', ' ', $c->complaint_type ?? '—') }}</td>
                <td class="text-capitalize">{{ $c->severity ?? '—' }}</td>
                <td>{{ $fmt($c->complaint_date ?? $c->created_at) }}</td>
                <td><small class="text-muted">{{ \Illuminate\Support\Str::limit($c->description ?: '—', 60) }}</small></td>
                <td>{!! $pill($c->status ?? null) !!}</td>
                <td>
                  @if($c->attachment_path)
                    <a href="{{ route('employee-lifecycle.complaints.attachment', $c->id) }}" target="_blank" class="btn btn-sm btn-hitech-modal-cancel">
                      <i class="bx bx-paperclip"></i>
                    </a>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-5">No complaints on record</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
