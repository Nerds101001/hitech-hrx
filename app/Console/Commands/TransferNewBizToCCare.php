<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CrmClientMapping;
use App\Models\Client;
use App\Models\ProductOrder;
use App\Models\CcSalespersonMap;
use App\Models\SalesClient;
use Illuminate\Support\Facades\Log;

class TransferNewBizToCCare extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hrx:transfer-newbiz-ccare';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-transfers customers from New Biz to CCare after 5 orders are completed.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting New Biz to CCare transfer check...");

        // Get all mappings that are currently assigned to New Biz
        $mappings = CrmClientMapping::whereNotNull('new_biz_id')->get();
        $transferredCount = 0;

        foreach ($mappings as $mapping) {
            // Find the HRX local client record using crm_company_code
            $client = Client::where('crm_company_code', $mapping->crm_company_code)->first();

            if (!$client) {
                continue;
            }

            // Count the orders for this client
            $orderCount = ProductOrder::where('client_id', $client->id)->count();

            if ($orderCount >= 5) {
                $this->info("Client '{$mapping->crm_company_name}' has {$orderCount} orders. Transferring from New Biz to CCare...");

                // Find the CCare agent mapped to this client's salesperson
                $ccareId = $this->getMappedCcareId($mapping->salesperson_id);

                if ($ccareId) {
                    // Perform the transfer
                    $mapping->update([
                        'ccare_id' => $ccareId,
                        'new_biz_id' => null, // Remove New Biz
                    ]);

                    // Also update the SalesClient created_by ownership if applicable
                    $salesClient = SalesClient::where('crm_company_code', $mapping->crm_company_code)->first();
                    if ($salesClient) {
                        $salesClient->update([
                            'created_by' => $ccareId
                        ]);
                    }

                    Log::info("Transferred Client {$mapping->crm_company_name} (Code: {$mapping->crm_company_code}) to CCare ID {$ccareId} after 5 orders.");
                    $transferredCount++;
                } else {
                    $this->warn("No CCare agent found linked to Salesperson ID {$mapping->salesperson_id} for Client {$mapping->crm_company_name}.");
                }
            }
        }

        $this->info("Transfer check complete. Transferred {$transferredCount} clients.");
        return Command::SUCCESS;
    }

    /**
     * Get the mapped CCare ID for a given salesperson.
     */
    private function getMappedCcareId($salespersonId)
    {
        if (!$salespersonId) return null;

        $mappedCcMaps = CcSalespersonMap::where('sales_user_id', $salespersonId)
            ->with(['ccUser.department'])
            ->get();

        foreach ($mappedCcMaps as $map) {
            $ccUser = $map->ccUser;
            if ($ccUser && $ccUser->department) {
                $deptName = strtolower($ccUser->department->name);
                if (str_contains($deptName, 'customer care') || str_contains($deptName, 'c-care') || str_contains($deptName, 'ccare')) {
                    return $ccUser->id;
                }
            }
        }

        return null;
    }
}
