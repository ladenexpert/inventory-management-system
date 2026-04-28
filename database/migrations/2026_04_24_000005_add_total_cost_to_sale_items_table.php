<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->bigInteger('total_cost')->default(0)->after('cost_price');
        });

        DB::table('sale_items')->update([
            'total_cost' => DB::raw('quantity * cost_price'),
        ]);
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('total_cost');
        });
    }
};
