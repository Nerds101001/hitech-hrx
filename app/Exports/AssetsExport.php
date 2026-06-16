<?php

namespace App\Exports;

use App\Models\Asset;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class AssetsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $searchTerm;
    protected $category;
    protected $status;
    protected $userId;

    public function __construct($searchTerm = null, $category = null, $status = null, $userId = null)
    {
        $this->searchTerm = $searchTerm;
        $this->category = $category;
        $this->status = $status;
        $this->userId = $userId;
    }

    public function collection()
    {
        $query = Asset::with(['category', 'assignedUser']);

        if ($this->searchTerm) {
            $search = $this->searchTerm;
            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if ($this->category) {
            $query->where('category_id', $this->category);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }
        
        if ($this->userId) {
            $query->where('assigned_to', $this->userId);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Asset Code',
            'Name',
            'Category',
            'Assigned User',
            'Status',
            'Brand',
            'Model',
            'Serial Number',
            'Location',
            'Purchase Date',
            'Warranty Expiry',
            'Description',
            'Extra Details'
        ];
    }

    public function map($asset): array
    {
        // Format extra details JSON into a readable string
        $extraDetails = '';
        if (!empty($asset->extra_details) && is_array($asset->extra_details)) {
            foreach ($asset->extra_details as $key => $val) {
                $extraDetails .= ucfirst($key) . ': ' . $val . ' | ';
            }
            $extraDetails = rtrim($extraDetails, ' | ');
        }

        $assignedUserName = $asset->assignedUser ? $asset->assignedUser->first_name . ' ' . $asset->assignedUser->last_name : 'Unassigned';

        return [
            $asset->asset_code,
            $asset->name,
            $asset->category ? $asset->category->name : 'N/A',
            $assignedUserName,
            ucfirst($asset->status),
            $asset->brand,
            $asset->model,
            $asset->serial_number,
            $asset->location,
            $asset->purchase_date ? \Carbon\Carbon::parse($asset->purchase_date)->format('Y-m-d') : 'N/A',
            $asset->warranty_expiry ? \Carbon\Carbon::parse($asset->warranty_expiry)->format('Y-m-d') : 'N/A',
            $asset->description,
            $extraDetails
        ];
    }
}
