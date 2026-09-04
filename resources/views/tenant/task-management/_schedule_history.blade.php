{{-- Rendered as JSON ('html' key) by TaskManagementController::scheduleHistory()
     and injected into the collapsed schedule row's placeholder in index.blade.php. --}}
@forelse($rows as $r)
  <tr>
    <td>{{ \Carbon\Carbon::parse($r->fire_date)->format('d M Y (D)') }}</td>
    <td>
      <span class="badge-hitech {{ $r->done == $r->total ? 'badge-hitech-success' : 'badge-brand-soft' }}">
        {{ (int) $r->done }}/{{ $r->total }} done
      </span>
    </td>
    <td>
      <a href="{{ route('task-management.batch', $r->batch_id) }}" class="small text-decoration-none">
        <i class="bx bx-link-external"></i> View everyone
      </a>
    </td>
  </tr>
@empty
  <tr><td colspan="3" class="text-center text-muted py-3">Hasn't fired yet.</td></tr>
@endforelse
