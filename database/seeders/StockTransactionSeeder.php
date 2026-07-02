<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class StockTransactionSeeder extends Seeder
{
    /**
     * Seed the historical stock transactions from the legacy database.sql,
     * idempotently keyed on (item_code + reference_number).
     *
     * Foreign keys are resolved by natural key (item_code / username /
     * branch name) rather than the literal ids in database.sql — those ids
     * are not stable across seed runs (e.g. rolled-back test transactions
     * keep advancing the auto-increment counter).
     *
     * These are historical records only — inventory_items.current_stock is
     * NOT touched here; items are already seeded with their final stock
     * figure by InventoryItemSeeder.
     *
     * Source: database.sql (legacy app) — INSERT INTO stock_transactions.
     */
    public function run(): void
    {
        $items = InventoryItem::pluck('id', 'item_code');
        $users = User::pluck('id', 'username');
        $branches = Branch::pluck('id', 'name');

        // [item_code, branch, username, type, quantity, unit_price, reference_number, remarks, transaction_date]
        $transactions = [
            ['NK-ICT-001', 'UIRI Nakawa', 'admin', 'stock_in', 15, 2500000, 'PO-2026-001', 'Initial stock from CompuTech', '2026-01-10'],
            ['NK-ICT-001', 'UIRI Nakawa', 'jssemanda', 'stock_out', 3, 2500000, 'ISS-2026-001', 'Issued to ICT Dept', '2026-02-14'],
            ['NK-ICT-002', 'UIRI Nakawa', 'jssemanda', 'stock_in', 8, 850000, 'PO-2026-002', 'Stock received from supplier', '2026-01-15'],
            ['NK-ICT-002', 'UIRI Nakawa', 'jssemanda', 'stock_out', 2, 850000, 'ISS-2026-002', 'Issued to Admin Office', '2026-03-01'],
            ['NK-OFF-001', 'UIRI Nakawa', 'jssemanda', 'stock_in', 200, 12000, 'PO-2026-003', 'Quarterly paper purchase', '2026-01-05'],
            ['NK-OFF-001', 'UIRI Nakawa', 'jssemanda', 'stock_out', 50, 12000, 'ISS-2026-003', 'General office use', '2026-04-20'],
            ['NM-ICT-001', 'UIRI Namanve', 'gakello', 'stock_in', 10, 1800000, 'PO-2026-004', 'Initial computers for Namanve', '2026-01-20'],
            ['NM-ICT-001', 'UIRI Namanve', 'gakello', 'stock_out', 2, 1800000, 'ISS-2026-004', 'Issued to Research Lab', '2026-03-15'],
            ['NM-OFF-001', 'UIRI Namanve', 'gakello', 'stock_in', 20, 8000, 'PO-2026-005', 'Monthly stationery', '2026-05-01'],
            ['NM-OFF-001', 'UIRI Namanve', 'gakello', 'stock_out', 17, 8000, 'ISS-2026-005', 'Issued to departments', '2026-05-20'],
        ];

        foreach ($transactions as [$itemCode, $branch, $username, $type, $qty, $price, $reference, $remarks, $date]) {
            $itemId = $items[$itemCode] ?? null;
            $userId = $users[$username] ?? null;
            $branchId = $branches[$branch] ?? null;

            if (! $itemId || ! $userId || ! $branchId) {
                continue;
            }

            StockTransaction::updateOrCreate(
                [
                    'item_id' => $itemId,
                    'reference_number' => $reference,
                ],
                [
                    'branch_id' => $branchId,
                    'user_id' => $userId,
                    'transaction_type' => $type,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'remarks' => $remarks,
                    'transaction_date' => $date,
                ],
            );
        }
    }
}
