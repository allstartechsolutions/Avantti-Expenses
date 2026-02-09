<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'W9',
                'description' => 'IRS Form W-9 (Request for Taxpayer Identification Number)',
                'requires_expiration' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'General Liability Insurance',
                'description' => 'Commercial General Liability (CGL) insurance certificate',
                'requires_expiration' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Workers Compensation Insurance',
                'description' => 'Workers compensation insurance certificate',
                'requires_expiration' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Certificate of Insurance (COI)',
                'description' => 'Certificate of Insurance document',
                'requires_expiration' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Business License',
                'description' => 'Business license or registration',
                'requires_expiration' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Contractor License',
                'description' => 'State or local contractor license',
                'requires_expiration' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Auto Insurance',
                'description' => 'Commercial auto insurance certificate',
                'requires_expiration' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Other',
                'description' => 'Other documents',
                'requires_expiration' => false,
                'sort_order' => 99,
            ],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}
