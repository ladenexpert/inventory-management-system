<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 100)->unique();
            $table->date('expiry_date')->nullable()->index();
            $table->dateTime('received_at')->nullable()->index();
            $table->bigInteger('unit_cost')->default(0);
            $table->bigInteger('selling_price')->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('available_quantity')->default(0);
            $table->string('source', 50)->default('purchase')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'available_quantity']);
            $table->index(['product_id', 'expiry_date', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
