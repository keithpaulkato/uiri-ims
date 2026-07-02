<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Seed the reference suppliers.
     *
     * Source: database.sql (legacy app) — "INSERT INTO suppliers" block.
     * Idempotent: keyed on the unique company name.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'company_name' => 'CompuTech Uganda Ltd',
                'contact_person' => 'David Mukasa',
                'email' => 'sales@computech.co.ug',
                'phone' => '+256 414 123 456',
                'address' => 'Plot 5, Kampala Road, Kampala',
                'tin_number' => '1001234567',
            ],
            [
                'company_name' => 'Labtech East Africa',
                'contact_person' => 'Sarah Namutebi',
                'email' => 'info@labtech.co.ug',
                'phone' => '+256 772 987 654',
                'address' => 'Industrial Area, Kampala',
                'tin_number' => '1009876543',
            ],
            [
                'company_name' => 'Office World Uganda',
                'contact_person' => 'Peter Ocen',
                'email' => 'orders@officeworld.co.ug',
                'phone' => '+256 756 111 222',
                'address' => 'Nakasero, Kampala',
                'tin_number' => '1005556667',
            ],
            [
                'company_name' => 'National Enterprises Ltd',
                'contact_person' => 'Rose Atim',
                'email' => 'procurement@nel.co.ug',
                'phone' => '+256 414 555 999',
                'address' => 'Namanve Industrial Park',
                'tin_number' => '1007778889',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(
                ['company_name' => $supplier['company_name']],
                [
                    'contact_person' => $supplier['contact_person'],
                    'email' => $supplier['email'],
                    'phone' => $supplier['phone'],
                    'address' => $supplier['address'],
                    'tin_number' => $supplier['tin_number'],
                    'is_active' => true,
                ]
            );
        }
    }
}
