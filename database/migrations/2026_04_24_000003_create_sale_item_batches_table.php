<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->bigInteger('unit_cost')->default(0);
            $table->timestamps();

            $table->unique(['sale_item_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_item_batches');
    }
};
