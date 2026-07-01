<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchSeeder extends Seeder
{
    /**
     * Seed the branches table.
     *
     * Source: database.sql (legacy app) — INSERT INTO branches.
     */
    public function run(): void
    {
        $branches = [
            [
                'name' => 'UIRI Nakawa',
                'location' => 'Nakawa, Kampala',
                'address' => 'Plot 2-12, Nakawa Industrial Area, Kampala, Uganda',
                'phone' => '+256 414 287 501',
                'email' => 'nakawa@uiri.go.ug',
                'is_headquarters' => true,
            ],
            [
                'name' => 'UIRI Namanve',
                'location' => 'Namanve, Mukono',
                'address' => 'Namanve Industrial Park, Mukono, Uganda',
                'phone' => '+256 414 287 502',
                'email' => 'namanve@uiri.go.ug',
                'is_headquarters' => false,
            ],
        ];

        foreach ($branches as $branch) {
            DB::table('branches')->updateOrInsert(
                ['name' => $branch['name']],
                $branch + ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
