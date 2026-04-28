<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->string('batch_number', 100)->nullable()->after('product_id');
            $table->date('expiry_date')->nullable()->after('batch_number');

            $table->index('batch_number');
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropIndex(['batch_number']);
            $table->dropIndex(['expiry_date']);
            $table->dropColumn(['batch_number', 'expiry_date']);
        });
    }
};
