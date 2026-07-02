<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed the reference categories.
     *
     * Source: database.sql (legacy app) — "INSERT INTO categories" block.
     * Idempotent: keyed on the unique category name.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'ICT Equipment', 'description' => 'Computers, printers, networking devices and accessories'],
            ['name' => 'Laboratory Equipment', 'description' => 'Scientific instruments, lab tools and research equipment'],
            ['name' => 'Office Supplies', 'description' => 'Stationery, paper, pens and everyday office consumables'],
            ['name' => 'Furniture', 'description' => 'Desks, chairs, cabinets and office furniture'],
            ['name' => 'Machinery', 'description' => 'Industrial machines, generators and heavy equipment'],
            ['name' => 'Vehicles', 'description' => 'Cars, trucks and transport equipment'],
            ['name' => 'Safety Equipment', 'description' => 'PPE, fire extinguishers and safety gear'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                ['description' => $category['description']]
            );
        }
    }
}
