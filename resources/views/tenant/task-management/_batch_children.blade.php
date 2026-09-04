{{-- Rendered as JSON ('html' key) by TaskManagementController::showBatch()
     and injected into the collapsed batch row's placeholder in index.blade.php.
     Kept deliberately simple (no per-row actions) - editing or deleting one
     person's task out of a batch still goes through the full batch page. --}}
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
    <td><small class="text-muted">{{ optional($t->taskUpdates->first())->comment ?: '—' }}</small></td>
  </tr>
@endforeach
