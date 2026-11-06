<table class="table m-0 shadow-none border-0">
  <thead class="text-dark">
    <tr>
      <th class="ps-4">Employee</th>
      <th>Department</th>
      <th>Email</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    @forelse($tableUsers as $u)
      <tr style="border-bottom:1px solid #f1f5f9;">
        <td class="ps-4">
          <div class="d-flex align-items-center">
            <div class="avatar avatar-sm me-3">
              <span class="avatar-initial-hitech" style="background-color:#127464;color:#fff;display:flex;align-items:center;justify-content:center;border-radius:50%;width:100%;height:100%;">{{ strtoupper(substr($u->first_name,0,1)) }}</span>
            </div>
            <span class="fw-medium" style="font-size:0.875rem;color:#1e293b;">{{ $u->first_name }} {{ $u->last_name }}</span>
          </div>
        </td>
        <td><span class="text-body">{{ optional($u->department)->name ?: '—' }}</span></td>
        <td><small class="text-muted">{{ $u->email }}</small></td>
        <td>
          @if(isset($ackMap[$u->id]))
            <span class="badge bg-teal-light text-teal rounded-pill px-3 py-1 fw-semibold" style="font-size:0.75rem;"><i class="bx bx-check me-1"></i>Acknowledged {{ \Illuminate\Support\Carbon::parse($ackMap[$u->id])->format('d M Y') }}</span>
          @else
            <span class="badge bg-label-warning rounded-pill px-3 py-1 fw-semibold" style="font-size:0.75rem;"><i class="bx bx-time me-1"></i>Pending</span>
          @endif
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="4" class="text-center py-5">
          <i class="bx bx-user-x text-muted mb-2" style="font-size:2.5rem;"></i>
          <p class="mb-0 text-muted">{{ $emptyMsg ?? 'No users found.' }}</p>
        </td>
      </tr>
    @endforelse
  </tbody>
</table>
<div class="py-4"></div>
