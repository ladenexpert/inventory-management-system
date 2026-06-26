<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code', 50)->unique();
            $table->string('adjustment_type', 50)->index();
            $table->string('direction', 20)->nullable()->index();
            $table->string('source', 50)->nullable()->index();
            $table->string('reference')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('adjusted_at')->nullable()->index();
            $table->timestamp('imported_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->foreignId('inventory_adjustment_id')
                ->nullable()
                ->after('sale_item_id')
                ->constrained('inventory_adjustments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_adjustment_id');
        });

        Schema::dropIfExists('inventory_adjustments');
    }
};
