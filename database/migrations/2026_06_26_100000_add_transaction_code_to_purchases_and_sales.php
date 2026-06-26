<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('transaction_code', 50)->nullable()->after('invoice_number');
            $table->unique('transaction_code', 'purchases_transaction_code_unique');
            $table->dropUnique('purchases_invoice_number_unique');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('transaction_code', 50)->nullable()->after('invoice_number');
            $table->unique('transaction_code', 'sales_transaction_code_unique');
            $table->dropUnique('sales_invoice_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique('sales_transaction_code_unique');
            $table->dropColumn('transaction_code');
            $table->unique('invoice_number', 'sales_invoice_number_unique');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique('purchases_transaction_code_unique');
            $table->dropColumn('transaction_code');
            $table->unique('invoice_number', 'purchases_invoice_number_unique');
        });
    }
};
