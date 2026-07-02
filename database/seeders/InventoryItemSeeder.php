<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventoryItemSeeder extends Seeder
{
    /**
     * Seed the sample inventory items from the legacy database.sql,
     * idempotently keyed on the unique item_code.
     *
     * Foreign keys are resolved by natural key (name / username) rather than
     * literal ids — auto-increment ids are not stable across seed runs
     * (e.g. rolled-back test transactions keep advancing the counter).
     */
    public function run(): void
    {
        $branches = Branch::pluck('id', 'name');
        $categories = Category::pluck('id', 'name');
        $suppliers = Supplier::pluck('id', 'company_name');
        $users = User::pluck('id', 'username');

        // [branch, category, supplier, item_code, name, description, unit, unit_price, current_stock, minimum_stock, creator_username]
        $items = [
            ['UIRI Nakawa', 'ICT Equipment', 'CompuTech Uganda Ltd', 'NK-ICT-001', 'Dell Latitude Laptop', '14" i5 8GB RAM 256GB SSD', 'piece', 2500000, 12, 3, 'admin'],
            ['UIRI Nakawa', 'ICT Equipment', 'CompuTech Uganda Ltd', 'NK-ICT-002', 'HP LaserJet Printer', 'HP LaserJet Pro M404dn', 'piece', 850000, 6, 2, 'admin'],
            ['UIRI Nakawa', 'Laboratory Equipment', 'Labtech East Africa', 'NK-LAB-001', 'Digital pH Meter', 'Professional lab pH meter', 'piece', 350000, 8, 2, 'jssemanda'],
            ['UIRI Nakawa', 'Office Supplies', 'Office World Uganda', 'NK-OFF-001', 'A4 Printing Paper', '80gsm Ream of 500 sheets', 'ream', 12000, 150, 20, 'jssemanda'],
            ['UIRI Nakawa', 'Furniture', 'Office World Uganda', 'NK-FRN-001', 'Executive Office Chair', 'Ergonomic adjustable chair', 'piece', 450000, 25, 5, 'jssemanda'],
            ['UIRI Namanve', 'ICT Equipment', 'CompuTech Uganda Ltd', 'NM-ICT-001', 'Desktop Computer Set', 'Core i5 with monitor, keyboard, mouse', 'set', 1800000, 8, 2, 'gakello'],
            ['UIRI Namanve', 'Laboratory Equipment', 'Labtech East Africa', 'NM-LAB-001', 'Analytical Balance', '0.001g precision digital balance', 'piece', 1200000, 4, 1, 'gakello'],
            ['UIRI Namanve', 'Machinery', 'National Enterprises Ltd', 'NM-MCH-001', 'Industrial Generator', '50KVA Generator', 'piece', 18000000, 2, 1, 'gakello'],
            ['UIRI Namanve', 'Office Supplies', 'Office World Uganda', 'NM-OFF-001', 'Whiteboard Markers', 'Assorted colour markers box of 12', 'box', 8000, 3, 10, 'gakello'],
            ['UIRI Nakawa', 'Safety Equipment', 'Office World Uganda', 'NK-SAF-001', 'Fire Extinguisher', 'CO2 2kg fire extinguisher', 'piece', 180000, 15, 5, 'admin'],
        ];

        foreach ($items as [$branch, $category, $supplier, $code, $name, $desc, $unit, $price, $stock, $min, $creator]) {
            InventoryItem::updateOrCreate(
                ['item_code' => $code],
                [
                    'branch_id' => $branches[$branch],
                    'category_id' => $categories[$category],
                    'supplier_id' => $suppliers[$supplier] ?? null,
                    'name' => $name,
                    'description' => $desc,
                    'unit' => $unit,
                    'unit_price' => $price,
                    'current_stock' => $stock,
                    'minimum_stock' => $min,
                    'created_by' => $users[$creator] ?? null,
                    'is_active' => true,
                ],
            );
        }
    }
}
