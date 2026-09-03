<?php

namespace App\Exports;

use App\Models\KpiEntry;
use App\Services\KpiEntryService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KpiApprovalsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected string $status;
    protected ?int $departmentId;
    protected ?int $userId;

    public function __construct(string $status, ?int $departmentId = null, ?int $userId = null)
    {
        $this->status = $status;
        $this->departmentId = $departmentId;
        $this->userId = $userId;
    }

    public function collection()
    {
        return KpiEntryService::queue($this->departmentId, $this->status, $this->userId);
    }

    public function headings(): array
    {
        return [
            'Employee', 'Department', 'KPI', 'Period Start', 'Period End',
            'Reported', 'Customers', 'Status', 'Reviewed By', 'Reviewed At', 'HR Note',
        ];
    }

    public function map($entry): array
    {
        return [
            optional($entry->user)->getFullName() ?? '—',
            optional(optional($entry->user)->department)->name ?? '—',
            optional($entry->assignment)->title ?? '—',
            \Carbon\Carbon::parse($entry->period_start)->format('Y-m-d'),
            \Carbon\Carbon::parse($entry->period_end)->format('Y-m-d'),
            (float) $entry->value,
            $entry->note ?? '',
            ucfirst($entry->status),
            optional($entry->reviewer)->getFullName() ?? '',
            $entry->reviewed_at ? \Carbon\Carbon::parse($entry->reviewed_at)->format('Y-m-d H:i') : '',
            $entry->review_note ?? '',
        ];
    }
}
