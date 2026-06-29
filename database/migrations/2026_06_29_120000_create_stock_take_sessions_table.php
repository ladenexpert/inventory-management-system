<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_take_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_code', 50)->unique();
            $table->string('status', 30)->default('imported')->index();
            $table->string('reference')->nullable()->index();
            $table->text('notes')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->timestamp('posted_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_take_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_take_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status', 30)->default('imported')->index();
            $table->text('error_message')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_adjustment_id')->nullable()->constrained('inventory_adjustments')->nullOnDelete();
            $table->string('sku', 100)->nullable()->index();
            $table->string('item_code', 100)->nullable()->index();
            $table->string('material_name')->nullable();
            $table->string('batch_number', 100)->nullable()->index();
            $table->date('expiry_date')->nullable();
            $table->string('storage_location')->nullable();
            $table->integer('system_qty')->nullable();
            $table->integer('counted_qty')->nullable();
            $table->integer('variance_qty')->nullable();
            $table->string('reference')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['stock_take_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_rows');
        Schema::dropIfExists('stock_take_sessions');
    }
};
