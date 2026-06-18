<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('entry_context', 50)
                ->default('legacy_purchase')
                ->after('proof_image')
                ->index();
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->foreignId('storage_location_id')
                ->nullable()
                ->after('storage_location')
                ->constrained('storage_locations')
                ->nullOnDelete();
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->foreignId('storage_location_id')
                ->nullable()
                ->after('storage_location')
                ->constrained('storage_locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storage_location_id');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storage_location_id');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['entry_context']);
            $table->dropColumn('entry_context');
        });
    }
};
