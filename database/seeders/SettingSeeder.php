<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::set('store_name', 'PT. Cosmax Indonesia');
        Setting::set('store_address', 'Jl. TB Simatupang No.2, RT.13/RW.5, Cilandak Tim., Ps. Minggu, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12560');
        Setting::set('store_phone', '(021) 80682810');
        Setting::set('opening_balance_date', now()->startOfYear()->toDateString());
        Setting::set('opening_balance_amount', '10000000');
        Setting::set('currency_symbol', 'Rp');
        Setting::set('currency_position', 'left');
        Setting::set('currency_fraction_digits', '0');
        Setting::set('currency_thousand_separator', '.');
        Setting::set('currency_decimal_separator', ',');
        Setting::set('batch_near_expiry_days', '30');

        foreach ([
            'rni',
            'sales',
            'purchases',
            'finance',
            'reports',
            'users',
            'materials',
        ] as $module) {
            Setting::set("module_{$module}_enabled", '1');
        }
    }
}
