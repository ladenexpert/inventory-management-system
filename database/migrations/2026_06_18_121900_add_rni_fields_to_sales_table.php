<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('transaction_type')->default('sale')->after('invoice_number')->index();
            $table->dateTime('usage_date')->nullable()->after('sale_date')->index();
            $table->string('purpose')->nullable()->after('payment_method');
            $table->string('formula')->nullable()->after('purpose');
            $table->string('project')->nullable()->after('formula');
            $table->string('requested_by')->nullable()->after('project');
            $table->foreignId('issued_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_by');
            $table->dropColumn([
                'transaction_type',
                'usage_date',
                'purpose',
                'formula',
                'project',
                'requested_by',
            ]);
        });
    }
};
