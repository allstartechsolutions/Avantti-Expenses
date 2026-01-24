<?php

namespace Database\Seeders;

use App\Models\CatalogCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CatalogCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Product Categories
            [
                'name' => 'Hardware & Fasteners',
                'applicable_types' => ['product'],
                'display_order' => 1,
            ],
            [
                'name' => 'Lumber & Building Materials',
                'applicable_types' => ['product'],
                'display_order' => 2,
            ],
            [
                'name' => 'Electrical Supplies',
                'applicable_types' => ['product'],
                'display_order' => 3,
            ],
            [
                'name' => 'Plumbing Supplies',
                'applicable_types' => ['product'],
                'display_order' => 4,
            ],
            [
                'name' => 'Paint & Finishing',
                'applicable_types' => ['product'],
                'display_order' => 5,
            ],
            [
                'name' => 'Tools & Equipment',
                'applicable_types' => ['product'],
                'display_order' => 6,
            ],
            [
                'name' => 'Safety Equipment',
                'applicable_types' => ['product'],
                'display_order' => 7,
            ],

            // Service Categories
            [
                'name' => 'General Labor',
                'applicable_types' => ['service'],
                'display_order' => 8,
            ],
            [
                'name' => 'Skilled Labor',
                'applicable_types' => ['service'],
                'display_order' => 9,
            ],
            [
                'name' => 'Specialized Services',
                'applicable_types' => ['service'],
                'display_order' => 10,
            ],
            [
                'name' => 'Subcontractor Services',
                'applicable_types' => ['service'],
                'display_order' => 11,
            ],
            [
                'name' => 'Professional Services',
                'applicable_types' => ['service'],
                'display_order' => 12,
            ],

            // Rental Categories
            [
                'name' => 'Heavy Equipment',
                'applicable_types' => ['rental'],
                'display_order' => 13,
            ],
            [
                'name' => 'Power Tools',
                'applicable_types' => ['rental'],
                'display_order' => 14,
            ],
            [
                'name' => 'Scaffolding & Ladders',
                'applicable_types' => ['rental'],
                'display_order' => 15,
            ],
            [
                'name' => 'Vehicles',
                'applicable_types' => ['rental'],
                'display_order' => 16,
            ],
        ];

        foreach ($categories as $category) {
            CatalogCategory::firstOrCreate(
                ['name' => $category['name']],
                [
                    'applicable_types' => $category['applicable_types'],
                    'display_order' => $category['display_order'],
                ]
            );
        }
    }
}
