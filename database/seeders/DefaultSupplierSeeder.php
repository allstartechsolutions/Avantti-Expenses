<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class DefaultSupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default supplier to be used when no specific supplier is assigned
        Supplier::firstOrCreate(
            ['name' => 'General Supplier'],
            [
                'street' => 'N/A',
                'city' => 'N/A',
                'state' => 'N/A',
                'postal_code' => '00000',
                'country' => config('app.country', 'US'),
                'phone' => null,
                'email' => null,
                'description' => 'Default supplier for items without a specific preferred supplier.',
                'created_by' => 1, // Assumes user ID 1 exists (typically admin)
            ]
        );
    }
}
