<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            RolePermissionSeeder::class,
            CustomerSeeder::class,
            SupplierSeeder::class,
            UnitSeeder::class,
            CategorySeeder::class,
            PhysicalFormSeeder::class,
            TeamSeeder::class,
            ProductSeeder::class,
            FinanceCategorySeeder::class,
            SettingSeeder::class,
        ]);
    }
}
