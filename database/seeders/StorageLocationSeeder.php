<?php

namespace Database\Seeders;

use App\Models\StorageLocation;
use Illuminate\Database\Seeder;

class StorageLocationSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = StorageLocation::query()->updateOrCreate(
            ['code' => 'RM-WH-01'],
            [
                'name' => 'Raw Material Warehouse',
                'type' => 'room',
                'parent_id' => null,
                'description' => 'Default raw material warehouse for pilot readiness.',
                'is_active' => true,
            ],
        );

        foreach ([
            [
                'code' => 'RM-RACK-01',
                'name' => 'Receiving Rack 01',
                'type' => 'rack',
                'parent_id' => $warehouse->id,
                'description' => 'Default receiving rack for incoming material batches.',
            ],
            [
                'code' => 'RM-RACK-02',
                'name' => 'General Rack 02',
                'type' => 'rack',
                'parent_id' => $warehouse->id,
                'description' => 'Default storage rack for general material placement.',
            ],
            [
                'code' => 'RM-QA-01',
                'name' => 'Quarantine Area',
                'type' => 'room',
                'parent_id' => null,
                'description' => 'Default quarantine holding area for pilot operations.',
            ],
        ] as $record) {
            StorageLocation::query()->updateOrCreate(
                ['code' => $record['code']],
                [
                    'name' => $record['name'],
                    'type' => $record['type'],
                    'parent_id' => $record['parent_id'],
                    'description' => $record['description'],
                    'is_active' => true,
                ],
            );
        }
    }
}
