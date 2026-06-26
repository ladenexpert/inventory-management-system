<?php

namespace Database\Seeders;

use App\Models\PhysicalForm;
use Illuminate\Database\Seeder;

class PhysicalFormSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'liquid', 'name' => 'Liquid'],
            ['code' => 'powder', 'name' => 'Powder'],
            ['code' => 'wax', 'name' => 'Wax'],
            ['code' => 'other', 'name' => 'Others'],
        ] as $record) {
            PhysicalForm::query()->updateOrCreate(
                ['code' => $record['code']],
                ['name' => $record['name'], 'is_active' => true],
            );
        }
    }
}
