<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead class="bg-light">
            <tr>
                <th class="ps-4">Biometric ID</th>
                <th>Employee Name</th>
                <th>Date</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th class="pe-4 text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($previewData as $row)
            <tr>
                <td class="ps-4 font-monospace small text-primary">{{ $row['biometric_id'] }}</td>
                <td>
                    @if($row['user_id'])
                        <span class="fw-bold text-dark">{{ $row['employee_name'] }}</span>
                    @else
                        <span class="text-danger small"><i class="bx bx-error-circle me-1"></i>Row {{ $loop->iteration }}: Match Not Found</span>
                    @endif
                </td>
                <td class="small">{{ $row['date'] }}</td>
                <td><span class="badge bg-label-success">{{ $row['check_in'] }}</span></td>
                <td><span class="badge bg-label-secondary">{{ $row['check_out'] }}</span></td>
                <td class="pe-4 text-center">
                    @if($row['status'] == 'Ready')
                        <span class="badge bg-success-soft text-success rounded-pill"><i class="bx bx-check me-1"></i>Ready</span>
                    @else
                        <span class="badge bg-danger-soft text-danger rounded-pill"><i class="bx bx-x me-1"></i>Skip</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="p-4 border-top bg-light">
    <div class="d-flex align-items-center">
        <i class="bx bx-info-circle fs-4 me-3 text-teal"></i>
        <p class="mb-0 small text-muted">
            <b>Total records:</b> {{ count($previewData) }}. 
            @php 
                $errorCount = collect($previewData)->where('status', 'Error')->count();
            @endphp
            @if($errorCount > 0)
                <span class="text-danger fw-bold ms-2">{{ $errorCount }} records will be skipped (No Biometric ID/Code match).</span>
            @else
                All records matched successfully.
            @endif
        </p>
    </div>
</div>
