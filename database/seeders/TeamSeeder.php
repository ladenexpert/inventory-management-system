<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'RNI', 'name' => 'Research & Innovation'],
            ['code' => 'RM-DESK', 'name' => 'RM Desk'],
            ['code' => 'PROD', 'name' => 'Production'],
        ] as $record) {
            Team::query()->updateOrCreate(
                ['code' => $record['code']],
                ['name' => $record['name'], 'is_active' => true],
            );
        }
    }
}
