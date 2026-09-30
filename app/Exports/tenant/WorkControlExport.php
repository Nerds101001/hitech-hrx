<?php

namespace App\Exports\tenant;

use App\Models\User;
use App\Models\WorkControlRoutine;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WorkControlExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param int[]|null $userIds null = every user (admin/HR), otherwise only
     *                            these users' routines. The caller decides the
     *                            scope; see WorkControlController::visibleUserIds().
     */
    public function __construct(private ?array $userIds = null)
    {
    }

    public function collection()
    {
        return WorkControlRoutine::with('user', 'user.department', 'user.designation.department')
            ->when($this->userIds !== null, fn ($q) => $q->whereIn('user_id', $this->userIds))
            ->get();
    }

    public function headings(): array
    {
        return [
            'Employee Name',
            'Department',
            'Task Title',
            'Frequency',
            'Duration (Minutes)',
            'Duration (Formatted)',
        ];
    }

    public function map($routine): array
    {
        $deptName = $routine->user && $routine->user->department 
            ? $routine->user->department->name 
            : ($routine->user && $routine->user->designation && $routine->user->designation->department ? $routine->user->designation->department->name : 'N/A');

        $userName = $routine->user ? $routine->user->name : 'Unknown';

        $hours = floor($routine->duration_minutes / 60);
        $mins = $routine->duration_minutes % 60;
        $formatted = $hours . 'h ' . sprintf('%02d', $mins) . 'm';

        return [
            $userName,
            $deptName,
            $routine->title,
            ucfirst($routine->frequency),
            $routine->duration_minutes,
            $formatted,
        ];
    }
}
