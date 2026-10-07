{{-- Rendered as JSON ('html' key) by TaskManagementController::showBatch()
     and injected into the collapsed batch row's placeholder in index.blade.php.
     Each person has a View button (their own details and remarks); editing
     or deleting one person's task still goes through the full batch page. --}}
@foreach($tasks as $t)
  <tr>
    <td>
      <div class="fw-medium">{{ optional($t->user)->getFullName() ?? '—' }}</div>
      <small class="text-muted">{{ optional(optional($t->user)->department)->name ?? '—' }}</small>
    </td>
    <td>
      @if($t->status === 'objection')
        <span class="badge-hitech badge-hitech-danger">Objection Raised</span>
      @elseif($t->is_completed)
        <span class="badge-hitech badge-hitech-success">{{ ucfirst(str_replace('_',' ', $t->status)) }}</span>
      @elseif($t->is_overdue)
        <span class="badge-hitech badge-hitech-danger">Overdue</span>
      @elseif($t->status === 'in_progress')
        <span class="badge-hitech badge-brand-soft">In progress</span>
      @else
        <span class="badge-hitech badge-status-new">New</span>
      @endif
    </td>
    <td><small class="text-muted">{{ $t->completed_at ? $t->completed_at->format('d M Y, h:i A') : '—' }}</small></td>
    <td>
      @php
        $timingBadge = ['early' => 'badge-hitech-success', 'on_time' => 'badge-brand-soft', 'late' => 'badge-hitech-danger'];
        $timingLabel = ['early' => 'Early', 'on_time' => 'On time', 'late' => 'Delayed'];
      @endphp
      @if($t->completion_timing)
        <span class="badge-hitech {{ $timingBadge[$t->completion_timing] }}">{{ $timingLabel[$t->completion_timing] }}</span>
      @else
        <span class="text-muted">—</span>
      @endif
    </td>
    @php $latest = $t->taskUpdates->first(); @endphp
    <td>
      @if($latest && $latest->comment)
        <small class="text-muted"><span class="fw-medium">{{ optional($latest->createdBy)->getFullName() ?? '—' }}:</span> {{ $latest->comment }}</small>
      @else
        <small class="text-muted">—</small>
      @endif
    </td>
    {{-- This person's own task: details, every remark, and reply. --}}
    <td class="text-end">
      <button type="button" class="icon-sophisticated js-view-task" title="View" aria-label="View this person's task"
              data-task="{{ json_encode(\App\Http\Controllers\tenant\TaskManagementController::viewPayload($t)) }}">
        <i class="bx bx-show"></i>
      </button>
    </td>
  </tr>
@endforeach
